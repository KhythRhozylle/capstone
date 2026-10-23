<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\Feedback;
use App\Entity\Notification;
use App\Entity\OfficeHour;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\AppointmentMode;
use App\Enum\AppointmentStatus;
use App\Service\TrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Office hours, double-booking, reschedule/cancel and the satisfaction survey. */
class AppointmentSchedulingTest extends WebTestCase
{
    private array $databaseEnvironment = [];
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private Campus $campus;
    private SasUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseEnvironment = [$_ENV['DATABASE_URL'] ?? null, $_SERVER['DATABASE_URL'] ?? null];
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->manager = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $this->campus = $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
        $this->unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'OSAS']);
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

    public function testASlotCannotBeBookedTwice(): void
    {
        $first = $this->student('first@example.test');
        $second = $this->student('second@example.test');
        $when = $this->slot('10:00');

        $this->client->loginUser($first);
        $this->book($when);
        self::assertResponseStatusCodeSame(302);

        $this->client->loginUser($second);
        $this->book($when);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'That slot has already been booked');
        self::assertSame(1, $this->manager->getRepository(Appointment::class)->count([]));
    }

    public function testBookingAndRescheduleFormsLoadTheSlotPicker(): void
    {
        $student = $this->student('picker@example.test');
        $mine = $this->appointment($student, $this->slot('09:00'), AppointmentStatus::Approved);
        $this->client->loginUser($student);

        $this->client->request('GET', '/portal/appointments/new');
        self::assertSelectorExists('form.stack-form[data-controller="slot-picker"][data-slot-picker-url-value="/appointment-slots"]');
        self::assertSelectorExists('select[data-slot-picker-target="unit"]');
        self::assertSelectorExists('input[data-slot-picker-target="when"]');

        $this->client->request('GET', '/portal/appointments/'.$mine->getId());
        self::assertSelectorExists('form[data-controller="slot-picker"][data-slot-picker-exclude-value="'.$mine->getId().'"][data-slot-picker-unit-value="'.$this->unit->getId().'"]');
    }

    public function testTimesOutsideOfficeHoursAreRejected(): void
    {
        $this->client->loginUser($this->student('lunch@example.test'));
        $this->book($this->slot('12:00'));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'is not open at that time');
        self::assertSame(0, $this->manager->getRepository(Appointment::class)->count([]));
    }

    public function testSlotEndpointShowsWhichSlotsAreTaken(): void
    {
        $student = $this->student('slots@example.test');
        $this->appointment($student, $this->slot('10:00'));
        $this->client->loginUser($student);

        $this->client->request('GET', '/appointment-slots', ['unit' => $this->unit->getId(), 'date' => $this->day()->format('Y-m-d')]);
        self::assertResponseIsSuccessful();
        $slots = array_column(json_decode($this->client->getResponse()->getContent(), true)['slots'], null, 'time');
        self::assertCount(8, $slots);
        self::assertFalse($slots['10:00']['available']);
        self::assertTrue($slots['09:00']['available']);

        $this->client->request('GET', '/appointment-slots', ['unit' => $this->unit->getId()]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testStudentCanRescheduleToAnotherOpenSlot(): void
    {
        $student = $this->student('move@example.test');
        $appointment = $this->appointment($student, $this->slot('09:00'), AppointmentStatus::Approved);
        $id = $appointment->getId();
        $this->client->loginUser($student);

        $this->client->request('GET', '/portal/appointments/'.$id);
        self::assertSelectorExists('#reschedule');
        $this->client->submitForm('Reschedule', ['appointment_reschedule[scheduledAt]' => $this->slot('14:00'), 'appointment_reschedule[note]' => 'Class conflict']);
        self::assertResponseRedirects('/portal/appointments/'.$id);

        $this->manager->clear();
        $saved = $this->manager->find(Appointment::class, $id);
        self::assertSame(AppointmentStatus::Pending, $saved->getStatus());
        self::assertSame($this->slot('14:00'), $saved->getScheduledAt()->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d\TH:i'));
        $history = self::getContainer()->get(TrackingService::class)->history($saved);
        self::assertCount(1, $history);
        self::assertStringContainsString('Class conflict', (string) $history[0]->getNote());
    }

    public function testRescheduleIntoATakenSlotIsRefused(): void
    {
        $student = $this->student('clash@example.test');
        $this->appointment($this->student('other@example.test'), $this->slot('15:00'));
        $mine = $this->appointment($student, $this->slot('09:00'), AppointmentStatus::Approved);
        $id = $mine->getId();
        $this->client->loginUser($student);

        $this->client->request('GET', '/portal/appointments/'.$id);
        $this->client->submitForm('Reschedule', ['appointment_reschedule[scheduledAt]' => $this->slot('15:00')]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'already been booked');

        $this->manager->clear();
        $saved = $this->manager->find(Appointment::class, $id);
        self::assertSame(AppointmentStatus::Approved, $saved->getStatus());
        self::assertSame($this->slot('09:00'), $saved->getScheduledAt()->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d\TH:i'));
    }

    public function testKeepingTheSameSlotIsNotReportedAsDoubleBooking(): void
    {
        $student = $this->student('same@example.test');
        $mine = $this->appointment($student, $this->slot('09:00'), AppointmentStatus::Approved);
        $this->client->loginUser($student);

        $this->client->request('GET', '/portal/appointments/'.$mine->getId().'/edit');
        $this->client->submitForm('Save changes', ['appointment[purpose]' => 'Updated purpose only.']);
        self::assertResponseStatusCodeSame(303);
    }

    public function testStudentCanCancelAndTheSlotIsFreedUp(): void
    {
        $student = $this->student('cancel@example.test');
        $mine = $this->appointment($student, $this->slot('11:00'), AppointmentStatus::Approved);
        $id = $mine->getId();
        $this->client->loginUser($student);

        $this->client->request('GET', '/portal/appointments/'.$id);
        $this->client->submitForm('Cancel appointment');
        self::assertResponseRedirects('/portal/appointments/'.$id);

        $this->manager->clear();
        self::assertSame(AppointmentStatus::Cancelled, $this->manager->find(Appointment::class, $id)->getStatus());

        $this->client->request('GET', '/appointment-slots', ['unit' => $this->unit->getId(), 'date' => $this->day()->format('Y-m-d')]);
        $slots = array_column(json_decode($this->client->getResponse()->getContent(), true)['slots'], null, 'time');
        self::assertTrue($slots['11:00']['available']);
    }

    public function testStaffCanRescheduleAndTheStudentIsNotified(): void
    {
        $student = $this->student('notify@example.test');
        $mine = $this->appointment($student, $this->slot('09:00'), AppointmentStatus::Approved);
        $id = $mine->getId();
        $this->client->loginUser($this->staff());

        $this->client->request('GET', '/staff/appointments/'.$id);
        $this->client->submitForm('Reschedule', ['appointment_reschedule[scheduledAt]' => $this->slot('16:00')]);
        self::assertResponseRedirects('/staff/appointments/'.$id);

        $this->manager->clear();
        $saved = $this->manager->find(Appointment::class, $id);
        self::assertSame(AppointmentStatus::Rescheduled, $saved->getStatus());
        self::assertSame($this->slot('16:00'), $saved->getScheduledAt()->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d\TH:i'));
        self::assertSame(1, $this->manager->getRepository(Notification::class)->count(['recipient' => $saved->getStudent()]));
    }

    public function testStaffSetOfficeHoursControlTheBookableSlots(): void
    {
        $this->client->loginUser($this->staff());
        $tuesday = $this->nextWeekday(2);

        $this->client->request('GET', '/staff/office-hours');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash', 'no office hours yet');

        $this->client->submitForm('Add hours', ['office_hour[dayOfWeek]' => '2', 'office_hour[opensAt]' => '09:00', 'office_hour[closesAt]' => '11:00']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->manager->getRepository(OfficeHour::class)->count([]));

        $this->client->request('GET', '/appointment-slots', ['unit' => $this->unit->getId(), 'date' => $tuesday->format('Y-m-d')]);
        self::assertSame(['09:00', '10:00'], array_column(json_decode($this->client->getResponse()->getContent(), true)['slots'], 'time'));
        $this->client->request('GET', '/appointment-slots', ['unit' => $this->unit->getId(), 'date' => $this->nextWeekday(3)->format('Y-m-d')]);
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true)['slots']);

        $this->client->request('GET', '/staff/office-hours');
        $this->client->submitForm('Add hours', ['office_hour[dayOfWeek]' => '2', 'office_hour[opensAt]' => '15:00', 'office_hour[closesAt]' => '14:00']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Closing time must be later than opening time.');

        $crawler = $this->client->request('GET', '/staff/office-hours');
        $this->client->submit($crawler->filter('form.office-window')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertSame(0, $this->manager->getRepository(OfficeHour::class)->count([]));
    }

    public function testRatingIsOnlyOfferedForFinishedWorkAndStoredOnce(): void
    {
        $student = $this->student('rate@example.test');
        $open = $this->appointment($student, $this->slot('09:00'), AppointmentStatus::Approved);
        $done = $this->appointment($student, $this->slot('10:00'), AppointmentStatus::Completed);
        $this->client->loginUser($student);

        $this->client->request('GET', '/portal/appointments/'.$open->getId());
        self::assertSelectorNotExists('.feedback-panel');
        $this->client->request('POST', '/portal/feedback/appointment/'.$open->getId(), ['feedback' => ['rating' => '5']]);
        self::assertSame(0, $this->manager->getRepository(Feedback::class)->count([]));

        $this->client->request('GET', '/portal/appointments/'.$done->getId());
        self::assertSelectorExists('.feedback-panel form');
        $this->client->submitForm('Send feedback', ['feedback[rating]' => '4', 'feedback[comment]' => 'Quick and helpful.']);
        self::assertResponseRedirects('/portal/appointments/'.$done->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.feedback-panel', 'Thanks for your feedback');
        self::assertSelectorTextContains('.feedback-panel', 'Quick and helpful.');

        $this->client->request('POST', '/portal/feedback/appointment/'.$done->getId(), ['feedback' => ['rating' => '1']]);
        self::assertSame(1, $this->manager->getRepository(Feedback::class)->count([]));
        self::assertSame(4, $this->manager->getRepository(Feedback::class)->findOneBy([])->getRating());
    }

    public function testOtherStudentsCannotRateSomeoneElsesAppointment(): void
    {
        $done = $this->appointment($this->student('owner@example.test'), $this->slot('10:00'), AppointmentStatus::Completed);
        $this->client->loginUser($this->student('intruder@example.test'));

        $this->client->request('POST', '/portal/feedback/appointment/'.$done->getId(), ['feedback' => ['rating' => '5']]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testVpsasDashboardSummarisesSatisfactionPerUnit(): void
    {
        $student = $this->student('score@example.test');
        foreach ([5, 4] as $i => $rating) {
            $done = $this->appointment($student, $this->slot(['09:00', '10:00'][$i]), AppointmentStatus::Completed);
            $this->manager->persist(Feedback::forSubject($student, $done)->setRating($rating));
        }
        $this->manager->flush();

        $vpsas = $this->manager->getRepository(User::class)->findOneBy(['email' => 'vp.sas@norsu.edu.ph']);
        $this->client->loginUser($vpsas);
        $this->client->request('GET', '/vpsas');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.satisfaction-panel', '4.50');
        self::assertSelectorTextContains('.satisfaction-panel', 'University OSAS');
        self::assertSelectorTextContains('.satisfaction-panel', '2 responses');
    }

    private function day(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('+1 weekday', new \DateTimeZone('Asia/Manila'));
    }

    private function nextWeekday(int $isoDay): \DateTimeImmutable
    {
        $day = new \DateTimeImmutable('tomorrow', new \DateTimeZone('Asia/Manila'));
        while ((int) $day->format('N') !== $isoDay) {
            $day = $day->modify('+1 day');
        }

        return $day;
    }

    /** A bookable local time on the next weekday, in the format of the datetime-local field. */
    private function slot(string $time): string
    {
        return $this->day()->format('Y-m-d').'T'.$time;
    }

    private function book(string $when): void
    {
        $this->client->request('GET', '/portal/appointments/new');
        $this->client->submitForm('Submit request', [
            'appointment[unit]' => $this->unit->getId(),
            'appointment[mode]' => 'physical',
            'appointment[scheduledAt]' => $when,
            'appointment[purpose]' => 'Discuss student organization registration.',
        ]);
    }

    private function appointment(User $student, string $localTime, AppointmentStatus $status = AppointmentStatus::Pending): Appointment
    {
        $when = (new \DateTimeImmutable($localTime, new \DateTimeZone('Asia/Manila')))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $appointment = (new Appointment())->setStudent($student)->setCampus($this->campus)->setUnit($this->unit)
            ->setMode(AppointmentMode::Physical)->setStatus($status)->setScheduledAt($when)->setPurpose('Test visit')
            ->setReferenceCode('APT-T-'.bin2hex(random_bytes(4)));
        $this->manager->persist($appointment);
        $this->manager->flush();

        return $appointment;
    }

    private function student(string $email): User
    {
        $student = (new User())->setEmail($email)->setFirstName('Test')->setLastName('Student')->setRoles(['ROLE_STUDENT'])->setPassword('unused')->setCampus($this->campus);
        $this->manager->persist($student);
        $this->manager->flush();

        return $student;
    }

    private function staff(): User
    {
        return $this->manager->getRepository(User::class)->findOneBy(['email' => 'osas@norsu.edu.ph']);
    }
}
