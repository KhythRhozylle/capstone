<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\AppointmentMode;
use App\Enum\AppointmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AppointmentMeetingTest extends WebTestCase
{
    private array $databaseEnvironment = [];
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseEnvironment = [$_ENV['DATABASE_URL'] ?? null, $_SERVER['DATABASE_URL'] ?? null];
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->manager = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        [$env, $server] = $this->databaseEnvironment;
        if (null === $env) {
            unset($_ENV['DATABASE_URL']);
        } else {
            $_ENV['DATABASE_URL'] = $env;
        }
        if (null === $server) {
            unset($_SERVER['DATABASE_URL']);
        } else {
            $_SERVER['DATABASE_URL'] = $server;
        }
    }

    public function testStaffPublishesLinkAfterApprovalAndOnlyItsStudentCanViewIt(): void
    {
        [$appointment, $student, $staff] = $this->appointment();
        $id = $appointment->getId();
        $staffPath = '/staff/appointments/'.$id;
        $studentPath = '/portal/appointments/'.$id;
        $this->client->loginUser($staff);
        $this->client->request('GET', $staffPath);
        self::assertSelectorNotExists('input[name="appointment_meeting[meetingUrl]"]');
        $this->client->submitForm('Update appointment', ['status' => 'approved']);
        $this->client->followRedirect();
        self::assertSelectorExists('input[name="appointment_meeting[meetingUrl]"]');
        $url = 'https://meet.google.com/abc-defg-hij';
        $this->client->submitForm('Save meeting URL', ['appointment_meeting[meetingUrl]' => $url]);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertInputValueSame('appointment_meeting[meetingUrl]', $url);

        $this->client->loginUser($student);
        $this->client->request('GET', $studentPath);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="'.$url.'"]');
        self::assertSelectorNotExists('input[type="url"]');
        $this->client->request('POST', $staffPath, ['appointment_meeting' => ['meetingUrl' => 'https://example.org/forged']]);
        self::assertResponseStatusCodeSame(403);

        $other = $this->student('other@example.test', $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']));
        $this->client->loginUser($other);
        $this->client->request('GET', $studentPath);
        self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('unavailableAppointments')]
    public function testMeetingFieldIsUnavailableAndForgedPostsAreRejected(AppointmentMode $mode, AppointmentStatus $status): void
    {
        [$appointment, $student, $staff] = $this->appointment($mode, $status);
        $appointment->setMeetingUrl('https://example.org/old-link');
        $this->manager->flush();
        $this->client->loginUser($staff);
        $path = '/staff/appointments/'.$appointment->getId();
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="appointment_meeting[meetingUrl]"]');
        $this->client->request('POST', $path, ['appointment_meeting' => ['meetingUrl' => 'https://example.org/forged']]);
        self::assertResponseStatusCodeSame(403);
        $this->manager->clear();
        self::assertSame('https://example.org/old-link', $this->manager->find(Appointment::class, $appointment->getId())->getMeetingUrl());
        $this->client->loginUser($this->manager->find(User::class, $student->getId()));
        $this->client->request('GET', '/portal/appointments/'.$appointment->getId());
        self::assertSelectorNotExists('a[href="https://example.org/old-link"]');
    }

    public static function unavailableAppointments(): iterable
    {
        yield 'pending virtual' => [AppointmentMode::Virtual, AppointmentStatus::Pending];
        yield 'rejected virtual' => [AppointmentMode::Virtual, AppointmentStatus::Rejected];
        yield 'cancelled virtual' => [AppointmentMode::Virtual, AppointmentStatus::Cancelled];
        yield 'approved physical' => [AppointmentMode::Physical, AppointmentStatus::Approved];
    }

    public function testInvalidUrlsAndCsrfCannotBeSaved(): void
    {
        [$appointment, , $staff] = $this->appointment(AppointmentMode::Virtual, AppointmentStatus::Approved);
        $this->client->loginUser($staff);
        $path = '/staff/appointments/'.$appointment->getId();
        foreach (['not-a-url', 'javascript:alert(1)', 'ftp://example.org/meeting', ''] as $invalid) {
            $this->client->request('GET', $path);
            $this->client->submitForm('Save meeting URL', ['appointment_meeting[meetingUrl]' => $invalid]);
            self::assertResponseStatusCodeSame(422);
            $this->manager->clear();
            self::assertNull($this->manager->find(Appointment::class, $appointment->getId())->getMeetingUrl());
        }
        $this->client->request('GET', $path);
        $this->client->submitForm('Save meeting URL', ['appointment_meeting[meetingUrl]' => 'https://example.org/meeting', 'appointment_meeting[_token]' => 'invalid']);
        self::assertResponseStatusCodeSame(422);
        $this->manager->clear();
        self::assertNull($this->manager->find(Appointment::class, $appointment->getId())->getMeetingUrl());
    }

    public function testStaffFromAnotherUnitCannotManageTheLink(): void
    {
        [$appointment] = $this->appointment(AppointmentMode::Virtual, AppointmentStatus::Approved);
        $staff = $this->manager->getRepository(User::class)->findOneBy(['email' => 'discipline@norsu.edu.ph']);
        $this->client->loginUser($staff);
        $path = '/staff/appointments/'.$appointment->getId();
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $path, ['appointment_meeting' => ['meetingUrl' => 'https://example.org/forged']]);
        self::assertResponseStatusCodeSame(403);
    }

    private function appointment(AppointmentMode $mode = AppointmentMode::Virtual, AppointmentStatus $status = AppointmentStatus::Pending): array
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $campus = $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
        $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'OSAS']);
        $student = $this->student('meeting-student@example.test', $campus);
        $appointment = (new Appointment())->setStudent($student)->setCampus($campus)->setUnit($unit)->setMode($mode)->setStatus($status)->setPurpose('Virtual consultation')->setReferenceCode('APT-TEST-MEETING');
        $this->manager->persist($appointment);
        $this->manager->flush();

        return [$appointment, $student, $this->manager->getRepository(User::class)->findOneBy(['email' => 'osas@norsu.edu.ph'])];
    }

    private function student(string $email, Campus $campus): User
    {
        $student = (new User())->setEmail($email)->setFirstName('Meeting')->setLastName('Student')->setRoles(['ROLE_STUDENT'])->setPassword('unused')->setCampus($campus);
        $this->manager->persist($student);
        $this->manager->flush();

        return $student;
    }
}
