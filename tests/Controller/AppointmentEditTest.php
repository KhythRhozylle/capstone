<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\AppointmentMode;
use App\Enum\AppointmentStatus;
use App\Service\TrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AppointmentEditTest extends WebTestCase
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

    public function testOwnerCanEditAndResubmitAnApprovedAppointment(): void
    {
        [$appointment, $student] = $this->appointment(AppointmentMode::Virtual, AppointmentStatus::Approved);
        $id = $appointment->getId();
        $appointment->setMeetingUrl('https://example.org/old-meeting');
        $this->manager->flush();
        $reference = $appointment->getReferenceCode();
        $this->client->loginUser($student);
        $this->client->request('GET', '/portal/appointments/'.$id);
        self::assertSelectorExists('a[href="/portal/appointments/'.$id.'/edit"]');
        $this->client->clickLink('Edit appointment');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="appointment[meetingUrl]"]');
        $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'CARE']);
        $date = (new \DateTimeImmutable('+2 weekdays', new \DateTimeZone('Asia/Manila')))->format('Y-m-d').'T14:00';
        $this->client->submitForm('Save changes', [
            'appointment[unit]' => $unit->getId(),
            'appointment[mode]' => 'physical',
            'appointment[scheduledAt]' => $date,
            'appointment[purpose]' => 'Updated consultation purpose.',
        ]);
        self::assertResponseStatusCodeSame(303);
        $this->manager->clear();
        $saved = $this->manager->find(Appointment::class, $id);
        self::assertSame($reference, $saved->getReferenceCode());
        self::assertSame($student->getId(), $saved->getStudent()->getId());
        self::assertSame($unit->getId(), $saved->getUnit()->getId());
        self::assertSame(AppointmentMode::Physical, $saved->getMode());
        self::assertSame(AppointmentStatus::Pending, $saved->getStatus());
        self::assertNull($saved->getMeetingUrl());
        self::assertSame('Updated consultation purpose.', $saved->getPurpose());
        self::assertSame($date, $saved->getScheduledAt()->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d\TH:i'));
        self::assertCount(1, self::getContainer()->get(TrackingService::class)->history($saved));
        self::assertSame(1, $this->manager->getRepository(Appointment::class)->count([]));
    }

    #[DataProvider('closedStatuses')]
    public function testClosedAppointmentsCannotBeEdited(AppointmentStatus $status): void
    {
        [$appointment, $student] = $this->appointment(AppointmentMode::Virtual, $status);
        $this->client->loginUser($student);
        $path = '/portal/appointments/'.$appointment->getId();
        $this->client->request('GET', $path);
        self::assertSelectorNotExists('a[href="'.$path.'/edit"]');
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, $path.'/edit');
            self::assertResponseStatusCodeSame(403);
        }
    }

    public static function closedStatuses(): iterable
    {
        yield [AppointmentStatus::Completed];
        yield [AppointmentStatus::Cancelled];
        yield [AppointmentStatus::Rejected];
        yield [AppointmentStatus::CheckedIn];
        yield [AppointmentStatus::NoShow];
    }

    public function testOtherStudentsCannotReadOrSubmitTheEditForm(): void
    {
        [$appointment, $student] = $this->appointment();
        $other = $this->student('another@example.test', $student->getCampus());
        $this->client->loginUser($other);
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/portal/appointments/'.$appointment->getId().'/edit');
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testPastDatesAndInvalidCsrfDoNotChangeTheAppointment(): void
    {
        [$appointment, $student] = $this->appointment();
        $id = $appointment->getId();
        $original = $appointment->getScheduledAt()->format('c');
        $this->client->loginUser($student);
        $path = '/portal/appointments/'.$id.'/edit';
        $this->client->request('GET', $path);
        $this->client->submitForm('Save changes', ['appointment[scheduledAt]' => (new \DateTimeImmutable('yesterday', new \DateTimeZone('Asia/Manila')))->format('Y-m-d').'T10:00']);
        self::assertResponseStatusCodeSame(422);
        $this->manager->clear();
        self::assertSame($original, $this->manager->find(Appointment::class, $id)->getScheduledAt()->format('c'));
        $this->client->request('GET', $path);
        $this->client->submitForm('Save changes', ['appointment[purpose]' => 'Untrusted edit', 'appointment[_token]' => 'invalid']);
        self::assertResponseStatusCodeSame(422);
        $this->manager->clear();
        self::assertSame('Virtual consultation', $this->manager->find(Appointment::class, $id)->getPurpose());
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
