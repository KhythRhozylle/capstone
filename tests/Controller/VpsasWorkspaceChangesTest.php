<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Announcement;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\OfficeMessage;
use App\Entity\OfficeUnavailable;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Service\AppNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;

class VpsasWorkspaceChangesTest extends WebTestCase
{
    private array $databaseEnvironment;
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private array $files = [];

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
        $this->client->loginUser($this->user());
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
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

    public function testVpsasCanSetUnavailableFromCalendar(): void
    {
        $this->client->request('GET', '/staff/appointments/calendar');
        self::assertSelectorExists('[data-appointment-calendar-editable-value="true"]');
        $token = $this->client->getCrawler()->filter('[data-appointment-calendar-csrf-value]')->attr('data-appointment-calendar-csrf-value');
        $day = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->client->jsonRequest('POST', '/staff/appointments/calendar/availability', ['date' => $day, 'start' => 540, 'unavailable' => true], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'VPSAS']);
        $entry = $this->manager->getRepository(OfficeUnavailable::class)->findOneBy(['unit' => $unit]);
        self::assertNotNull($entry);
        self::assertSame($day, $entry->getDay()->format('Y-m-d'));
        self::assertSame(540, $entry->getStartMinute());
        self::assertSame(600, $entry->getEndMinute());
        $this->client->request('GET', '/staff/appointments/calendar');
        self::assertSelectorExists('[data-appointment-calendar-periods-value*="'.$day.'"]');
    }

    public function testDayHeaderTogglesTheWholeDayUnavailable(): void
    {
        $this->client->request('GET', '/staff/appointments/calendar');
        $token = $this->client->getCrawler()->filter('[data-appointment-calendar-csrf-value]')->attr('data-appointment-calendar-csrf-value');
        $day = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $url = '/staff/appointments/calendar/availability';
        $this->client->jsonRequest('POST', $url, ['date' => $day, 'start' => 540, 'unavailable' => true], ['HTTP_X_CSRF_TOKEN' => $token]);
        $this->client->jsonRequest('POST', $url, ['date' => $day, 'start' => 0, 'unavailable' => true, 'wholeDay' => true], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame([['date' => $day, 'start' => 0, 'end' => 1440]], json_decode($this->client->getResponse()->getContent(), true)['periods']);
        $this->client->jsonRequest('POST', $url, ['date' => $day, 'start' => 0, 'unavailable' => false, 'wholeDay' => true], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true)['periods']);
    }

    public function testPopupUndoRestoresOnlyOneHourAndRequiresOwnerAndCsrf(): void
    {
        $owner = $this->user();
        $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'VPSAS']);
        $day = (new \DateTimeImmutable('next monday'))->modify('+7 days');
        $entry = new OfficeUnavailable($unit, $owner);
        $entry->setDay($day);
        $this->manager->persist($entry);
        $this->manager->flush();
        $this->client->request('GET', '/staff/appointments/calendar');
        $token = $this->client->getCrawler()->filter('[data-appointment-calendar-csrf-value]')->attr('data-appointment-calendar-csrf-value');
        $payload = ['date' => $day->format('Y-m-d'), 'start' => 540, 'unavailable' => false];
        $url = '/staff/appointments/calendar/availability';
        $this->client->jsonRequest('POST', $url, $payload, ['HTTP_X_CSRF_TOKEN' => 'fake']);
        self::assertResponseStatusCodeSame(403);
        $this->client->jsonRequest('POST', $url, $payload, ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame([['date' => $day->format('Y-m-d'), 'start' => 0, 'end' => 540], ['date' => $day->format('Y-m-d'), 'start' => 600, 'end' => 1440]], json_decode($this->client->getResponse()->getContent(), true)['periods']);
        $this->client->jsonRequest('POST', $url, array_replace($payload, ['start' => 545]), ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(422);
        $this->client->jsonRequest('POST', $url, array_replace($payload, ['date' => '2020-01-01']), ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(422);
        $this->client->loginUser($this->person('popup-student@example.test', ['ROLE_STUDENT']));
        $this->client->jsonRequest('POST', $url, $payload, ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(2, $this->manager->getRepository(OfficeUnavailable::class)->count(['unit' => $unit]));
    }

    public function testNavigationStaysCompleteAcrossTheWorkspace(): void
    {
        foreach (['/vpsas', '/staff', '/documentation', '/staff/appointments', '/staff/office-hours', '/staff/announcements', '/vpsas/feedback', '/vpsas/profile/activity'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $nav = $this->client->getCrawler()->filter('.side-nav');
            foreach (['Appointments', 'Concerns', 'Documents', 'Announcements', 'Documentation guide', 'Feedbacks', 'Activity logs'] as $label) {
                self::assertStringContainsString($label, $nav->text(), $path);
            }
            foreach (['Service requests', 'Walk-in queue', 'Office hours', 'Calendar', 'VPSAS dashboard', 'CARE desk', 'Specialist desks'] as $label) {
                self::assertStringNotContainsString($label, $nav->text(), $path);
            }
            self::assertSelectorExists('a.topbar-icon[aria-label="Chat"]');
        }
        $this->client->request('GET', '/staff/appointments');
        self::assertSelectorExists('a[href="/staff/office-hours"]');
        self::assertSelectorExists('a[href="/staff/appointments/calendar"]');
        foreach (['/staff/services', '/staff/queue'] as $removed) {
            $this->client->request('GET', $removed);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testVpsasAppointmentsOnlyListPeopleWhoBookedThatOffice(): void
    {
        foreach (['VPSAS' => 'VpsasVisitor', 'OSAS' => 'OtherOfficeVisitor'] as $code => $name) {
            $student = $this->person(strtolower($name).'@example.test', ['ROLE_STUDENT']);
            $student->setFirstName($name);
            $appointment = (new Appointment())->setReferenceCode('APT-'.$code)->setStudent($student)
                ->setUnit($this->manager->getRepository(SasUnit::class)->findOneBy(['code' => $code]))
                ->setCampus($student->getCampus())->setPurpose('Appointment test')->setStatus(\App\Enum\AppointmentStatus::Approved);
            $this->manager->persist($appointment);
        }
        $this->manager->flush();
        $this->client->request('GET', '/staff/appointments');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.people-table', 'VpsasVisitor');
        self::assertStringNotContainsString('OtherOfficeVisitor', $this->client->getCrawler()->filter('table.people-table')->text());
        $this->client->request('GET', '/staff/appointments/calendar');
        self::assertResponseIsSuccessful();
        $events = $this->client->getCrawler()->filter('[data-appointment-calendar-events-value]')->attr('data-appointment-calendar-events-value');
        self::assertStringContainsString('VpsasVisitor', $events);
        self::assertStringNotContainsString('OtherOfficeVisitor', $events);
        $this->client->loginUser($this->person('director-list@example.test', ['ROLE_DIRECTOR']));
        $this->client->request('GET', '/staff/appointments');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.people-table', 'VpsasVisitor');
        self::assertSelectorTextContains('table.people-table', 'OtherOfficeVisitor');
    }

    public function testOtherStaffKeepTheirNavigationAndFunctions(): void
    {
        $staff = $this->person('staff-keep@example.test', ['ROLE_STAFF']);
        $staff->setStaffUnit($this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'OSAS']));
        $this->manager->flush();
        $this->client->loginUser($staff);
        foreach (['/staff/services', '/staff/queue'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.side-nav', 'Service requests');
            self::assertSelectorTextContains('.side-nav', 'Walk-in queue');
            self::assertSelectorTextContains('.side-nav', 'Office hours');
            self::assertSelectorTextContains('.side-nav', 'Calendar');
            self::assertSelectorNotExists('a.topbar-icon[aria-label="Chat"]');
        }
        foreach (['/vpsas/feedback', '/staff/announcements/archive'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testAnnouncementsMoveIntoArchiveAfterSevenDays(): void
    {
        $now = self::getContainer()->get(ClockInterface::class)->now();
        foreach (['Old announcement' => '-8 days', 'Boundary announcement' => '-7 days', 'Recent announcement' => '-6 days'] as $title => $age) {
            $announcement = (new Announcement())->setTitle($title)->setBody('Announcement content')->setPublishedBy($this->user());
            (new \ReflectionProperty(Announcement::class, 'publishedAt'))->setValue($announcement, $now->modify($age));
            $this->manager->persist($announcement);
        }
        $this->manager->flush();
        $this->client->request('GET', '/staff/announcements');
        self::assertSelectorTextContains('.announcement-grid', 'Recent announcement');
        self::assertStringNotContainsString('Old announcement', $this->client->getCrawler()->filter('.announcement-grid')->text());
        self::assertSelectorExists('a[aria-label="Announcement archive"]');
        $this->client->request('GET', '/staff/announcements/archive');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.announcement-grid', 'Old announcement');
        self::assertSelectorTextContains('.announcement-grid', 'Boundary announcement');
        self::assertStringNotContainsString('Recent announcement', $this->client->getCrawler()->filter('.announcement-grid')->text());
        $this->client->loginUser($this->person('archive-staff@example.test', ['ROLE_STAFF']));
        $this->client->request('GET', '/staff/announcements');
        self::assertSelectorTextContains('.announcement-grid', 'Old announcement');
        self::assertSelectorTextContains('.announcement-grid', 'Boundary announcement');
        self::assertSelectorTextContains('.announcement-grid', 'Recent announcement');
    }

    public function testUnavailableDatesCannotBeBookedOrChangedByStudents(): void
    {
        self::getContainer()->set(AppNotifier::class, $this->createStub(AppNotifier::class));
        $day = self::getContainer()->get(ClockInterface::class)->now()->setTimezone(new \DateTimeZone('Asia/Manila'))->modify('next monday')->modify('+7 days');
        $this->client->request('GET', '/appointments/vpsas-availability');
        $this->client->submitForm('Mark unavailable', ['office_unavailable[day]' => $day->format('Y-m-d')]);
        self::assertResponseRedirects('/appointments/vpsas-availability', 303);
        $entry = $this->manager->getRepository(OfficeUnavailable::class)->findOneBy([]);
        self::assertNotNull($entry);
        $student = $this->person('booking-student@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($student);
        $this->client->request('GET', '/appointments/vpsas-availability', ['year' => $day->format('Y'), 'month' => $day->format('n')]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.office-availability-day.is-unavailable[aria-disabled="true"]');
        self::assertSelectorNotExists('form[name="office_unavailable"]');
        self::assertSelectorNotExists('.office-availability-grid .is-unavailable button');
        $this->client->request('POST', '/appointments/vpsas-availability', ['office_unavailable' => ['day' => $day->format('Y-m-d')]]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/appointments/vpsas-availability/'.$entry->getId().'/delete', ['_token' => 'fake']);
        self::assertResponseStatusCodeSame(403);
        $unit = $entry->getUnit();
        $this->client->request('GET', '/appointment-slots', ['unit' => $unit->getId(), 'date' => $day->format('Y-m-d')]);
        self::assertResponseIsSuccessful();
        $slots = json_decode($this->client->getResponse()->getContent(), true)['slots'];
        self::assertNotEmpty($slots);
        self::assertSame([false], array_values(array_unique(array_column($slots, 'available'))));
        self::assertSame(['VPSAS unavailable'], array_values(array_unique(array_column($slots, 'reason'))));
        $this->client->request('GET', '/portal/appointments/new');
        $this->client->submitForm('Submit request', ['appointment[unit]' => $unit->getId(), 'appointment[mode]' => 'physical', 'appointment[scheduledAt]' => $day->format('Y-m-d').'T09:00', 'appointment[purpose]' => 'Unavailable booking attempt']);
        self::assertSelectorTextContains('body', 'VPSAS unavailable');
        self::assertSame(0, $this->manager->getRepository(Appointment::class)->count([]));
        $otherUnit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'OSAS']);
        $this->client->request('GET', '/appointment-slots', ['unit' => $otherUnit->getId(), 'date' => $day->format('Y-m-d')]);
        $otherSlots = json_decode($this->client->getResponse()->getContent(), true)['slots'];
        self::assertTrue($otherSlots[0]['available']);
    }

    public function testOwnerCanRestoreAvailabilityAndOfficeHoursOnlyBelongToVpsas(): void
    {
        $this->client->request('GET', '/staff/office-hours');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Office of the VPSAS');
        $entry = new OfficeUnavailable($this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'VPSAS']), $this->user());
        $this->manager->persist($entry);
        $this->manager->flush();
        $this->client->request('GET', '/appointments/vpsas-availability');
        $this->client->submitForm('Make available');
        self::assertResponseRedirects('/appointments/vpsas-availability', 303);
        self::assertSame(0, $this->manager->getRepository(OfficeUnavailable::class)->count([]));
    }

    public function testStudentCanOpenChatFromTheHeader(): void
    {
        $this->client->loginUser($this->person('header-chat-student@example.test', ['ROLE_STUDENT']));
        $this->client->request('GET', '/portal');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.topbar-icon[aria-label="Chat"][href="/messages"]');
        $this->client->click($this->client->getCrawler()->filter('a.topbar-icon[aria-label="Chat"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body.portal');
        self::assertSelectorTextContains('h1', 'Chat');
        self::assertSelectorExists('input[type="search"][name="q"]');
    }

    public function testChatMessagesKeepTheSelectedIdentityAndCannotLeakOtherThreads(): void
    {
        $student = $this->person('chat-student@example.test', ['ROLE_STUDENT']);
        $other = $this->person('other-chat-student@example.test', ['ROLE_STUDENT']);
        $this->client->request('GET', '/vpsas/profile');
        $this->client->submitForm('Dianne Futalan');
        $this->client->request('GET', '/messages/'.$student->getId());
        $this->client->submitForm('Send', ['office_message[body]' => 'Hello from Dianne']);
        self::assertResponseRedirects('/messages/'.$student->getId(), 303);
        $message = $this->manager->getRepository(OfficeMessage::class)->findOneBy([]);
        self::assertSame('Dianne Futalan', $message->getSenderName());
        $this->client->loginUser($student);
        $this->client->request('GET', '/messages/'.$this->user()->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.office-chat-messages', 'Hello from Dianne');
        $this->client->submitForm('Send', ['office_message[body]' => 'Student reply']);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/messages/'.$other->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('.office-chat-messages', 'Hello from Dianne');
        $this->client->loginUser($other);
        $this->client->request('GET', '/messages/'.$this->user()->getId());
        self::assertStringNotContainsString('Hello from Dianne', $this->client->getResponse()->getContent());
        self::assertSame(2, $this->manager->getRepository(OfficeMessage::class)->count([]));
    }

    public function testEveryAccountCanSendAndReceiveMessages(): void
    {
        $student = $this->person('all-chat-student@example.test', ['ROLE_STUDENT']);
        $accounts = [
            $this->person('all-chat-peer@example.test', ['ROLE_STUDENT']),
            $this->person('all-chat-staff@example.test', ['ROLE_STAFF']),
            $this->person('all-chat-user@example.test', ['ROLE_USER']),
            $this->user(),
        ];
        foreach ($accounts as $index => $account) {
            $this->client->loginUser($student);
            $this->client->request('GET', '/messages?view=people');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('a.office-chat-contact[href="/messages/'.$account->getId().'?view=people"]');
            self::assertSelectorNotExists('a.office-chat-contact[href="/messages/'.$student->getId().'?view=people"]');
            $this->client->click($this->client->getCrawler()->filter('a.office-chat-contact[href="/messages/'.$account->getId().'?view=people"]')->link());
            $this->client->submitForm('Send', ['office_message[body]' => 'Student message '.$index]);
            self::assertResponseRedirects('/messages/'.$account->getId(), 303);
            $this->client->loginUser($account);
            $this->client->request('GET', '/messages');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('a.topbar-icon[aria-label="Chat"]');
            $this->client->request('GET', '/messages/'.$student->getId());
            self::assertSelectorTextContains('.office-chat-messages', 'Student message '.$index);
            $this->client->submitForm('Send', ['office_message[body]' => 'Reply '.$index]);
            self::assertResponseRedirects('/messages/'.$student->getId(), 303);
            $this->client->loginUser($student);
            $this->client->request('GET', '/messages/'.$account->getId());
            self::assertSelectorTextContains('.office-chat-messages', 'Reply '.$index);
        }
        self::assertSame(8, $this->manager->getRepository(OfficeMessage::class)->count([]));
    }

    public function testRememberedStudentCanOpenInboxAndSendMessage(): void
    {
        $student = $this->person('remembered-chat@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($student);
        $this->client->request('GET', '/portal');
        $token = new \Symfony\Component\Security\Core\Authentication\Token\RememberMeToken($student, 'main');
        $session = $this->client->getRequest()->getSession();
        $session->set('_security_main', serialize($token));
        $session->save();
        self::getContainer()->get('security.untracked_token_storage')->setToken($token);
        $this->client->click($this->client->getCrawler()->filter('a[aria-label="Chat"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.office-chat-layout h1', 'Chats');
        $this->client->request('GET', '/messages/'.$this->user()->getId());
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Send', ['office_message[body]' => 'Remembered student message']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->manager->getRepository(OfficeMessage::class)->count([]));
    }

    public function testChatRejectsAnonymousSelfMissingRecipientAndInvalidMessages(): void
    {
        $student = $this->person('invalid-chat@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($student);
        $this->client->request('GET', '/messages/'.$student->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/messages/2147483647');
        self::assertResponseStatusCodeSame(404);
        $url = '/messages/'.$this->user()->getId();
        $this->client->request('POST', $url, ['office_message' => ['body' => 'Missing CSRF token']]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', $url);
        $this->client->submitForm('Send', ['office_message[body]' => '   ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->manager->getRepository(OfficeMessage::class)->count([]));
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/messages');
        self::assertResponseRedirects('/login');
    }

    public function testInboxSearchUsesRegisteredAccountsAndEscapesResults(): void
    {
        $student = $this->person('search-owner@example.test', ['ROLE_STUDENT']);
        $contact = $this->person('lily.jr@example.test', ['ROLE_STUDENT']);
        $contact->setFirstName('Lily')->setLastName('Rivera');
        $contact->setProfilePhoto('account', 'lily.jpg');
        $this->person('literal%name@example.test', ['ROLE_USER'])->setFirstName('<script>alert(1)</script>');
        $this->manager->flush();
        $this->client->loginUser($student);
        $count = $this->manager->getRepository(User::class)->count([]);
        foreach (['lily rivera', 'LILY.JR', 'lily.jr@example.test'] as $query) {
            $this->client->request('GET', '/messages/search', ['q' => $query]);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.chat-contact', 'Lily Rivera');
            self::assertSelectorTextContains('.chat-contact', 'lily.jr@example.test');
            self::assertSelectorExists('.chat-contact img[src$="/uploads/profile-photos/lily.jpg"]');
            self::assertCount(1, $this->client->getCrawler()->filter('.chat-contact'));
        }
        $this->client->click($this->client->getCrawler()->filter('.chat-contact')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('textarea[name="office_message[body]"]');
        $this->client->submitForm('Send', ['office_message[body]' => 'Hello Lily']);
        $this->client->followRedirect();
        $this->client->request('GET', '/messages/search', ['q' => 'lily']);
        $this->client->click($this->client->getCrawler()->filter('.chat-contact')->link());
        self::assertSelectorTextContains('.chat-messages', 'Hello Lily');
        $this->client->request('GET', '/messages/search', ['q' => 'no-such-account']);
        self::assertSelectorTextContains('.chat-list-empty', 'No matching accounts found.');
        $this->client->request('GET', '/messages/search', ['q' => '%']);
        self::assertCount(1, $this->client->getCrawler()->filter('.chat-contact'));
        self::assertSelectorNotExists('script');
        self::assertStringContainsString('&lt;script&gt;', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/messages/search', ['q' => 'search-owner@example.test']);
        self::assertCount(0, $this->client->getCrawler()->filter('.chat-contact'));
        self::assertSame($count, $this->manager->getRepository(User::class)->count([]));
        $this->client->request('GET', '/messages/search', ['q' => str_repeat('a', 121)]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testInboxListsLatestPreviewsAndPrivateArchives(): void
    {
        $student = $this->person('inbox-owner@example.test', ['ROLE_STUDENT']);
        $other = $this->person('inbox-other@example.test', ['ROLE_STUDENT']);
        $stranger = $this->person('inbox-stranger@example.test', ['ROLE_STUDENT']);
        foreach ([[$other, $student, 'First hello'], [$other, $student, 'Latest preview'], [$stranger, $this->user(), 'Unrelated private text']] as [$sender, $recipient, $body]) {
            $message = new OfficeMessage($sender, $recipient, $sender->getFullName());
            $message->setBody($body);
            $this->manager->persist($message);
            $this->manager->flush();
        }
        $this->client->loginUser($student);
        $this->client->request('GET', '/messages');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->client->getCrawler()->filter('.chat-contact'));
        self::assertSelectorTextContains('.chat-contact-preview', 'Latest preview');
        self::assertSelectorTextContains('.chat-received-count', '2');
        self::assertSelectorExists('.chat-received-count[aria-label="2 unread messages"]');
        self::assertStringNotContainsString('Unrelated private text', $this->client->getResponse()->getContent());
        self::assertCount(3, $this->client->getCrawler()->filter('.chat-rail-link'));
        self::assertSelectorNotExists('.chat-rail-link[href*="view=request"]');
        $this->client->click($this->client->getCrawler()->filter('.chat-contact')->link());
        self::assertSelectorExists('.chat-contact.is-selected[aria-current="page"]');
        $this->client->submitForm('Send', ['office_message[body]' => 'My reply']);
        $this->client->request('GET', '/messages');
        self::assertCount(1, $this->client->getCrawler()->filter('.chat-contact'));
        $this->client->request('GET', '/messages/'.$other->getId());
        $this->client->submitForm('Archive');
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/messages');
        self::assertCount(0, $this->client->getCrawler()->filter('.chat-contact'));
        $this->client->request('GET', '/messages?view=archive');
        self::assertSelectorTextContains('.chat-contact-preview', 'You: My reply');
        self::assertSelectorNotExists('.chat-received-count');
        $this->client->request('POST', '/messages/'.$other->getId().'/archive');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($other);
        $this->client->request('GET', '/messages?view=archive');
        self::assertCount(0, $this->client->getCrawler()->filter('.chat-contact'));
        $this->client->request('GET', '/messages');
        self::assertCount(1, $this->client->getCrawler()->filter('.chat-contact'));
        $this->client->loginUser($student);
        $this->client->request('GET', '/messages/'.$other->getId());
        $this->client->submitForm('Unarchive');
        $this->client->request('GET', '/messages');
        self::assertCount(1, $this->client->getCrawler()->filter('.chat-contact'));
        $this->client->request('GET', '/messages?view=request');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/messages/search?view=request');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnreadBadgesClearWhenViewedOrRepliedAndReturnForNewMessages(): void
    {
        $student = $this->person('unread-owner@example.test', ['ROLE_STUDENT']);
        $contact = $this->person('unread-contact@example.test', ['ROLE_STUDENT']);
        $other = $this->person('unread-other@example.test', ['ROLE_STUDENT']);
        foreach ([$contact, $contact, $other] as $sender) {
            $message = new OfficeMessage($sender, $student, $sender->getFullName());
            $message->setBody('Unread message');
            $this->manager->persist($message);
            $this->manager->flush();
        }
        $this->client->loginUser($student);
        $this->client->request('GET', '/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('a[href="/messages/'.$contact->getId().'?view=chat"] .chat-received-count', '2');
        self::assertSelectorTextSame('a[href="/messages/'.$other->getId().'?view=chat"] .chat-received-count', '1');
        $this->client->request('GET', '/messages/search', ['q' => 'unread-contact@example.test']);
        self::assertSelectorTextSame('.chat-received-count', '2');
        $this->client->request('GET', '/messages/'.$contact->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.chat-contact.is-selected .chat-received-count');
        $reply = $this->client->getCrawler()->selectButton('Send')->form();
        $reply['office_message[body]'] = 'Quick reply';
        $this->client->request('GET', '/messages');
        self::assertSelectorNotExists('a[href="/messages/'.$contact->getId().'?view=chat"] .chat-received-count');
        self::assertSelectorTextSame('a[href="/messages/'.$other->getId().'?view=chat"] .chat-received-count', '1');
        $this->client->request('GET', '/messages/search', ['q' => 'unread-contact@example.test']);
        self::assertSelectorNotExists('.chat-received-count');
        $message = new OfficeMessage($this->manager->find(User::class, $contact->getId()), $this->manager->find(User::class, $student->getId()), $contact->getFullName());
        $message->setBody('A new message after reading');
        $this->manager->persist($message);
        $this->manager->flush();
        $this->client->request('GET', '/messages');
        self::assertSelectorTextSame('a[href="/messages/'.$contact->getId().'?view=chat"] .chat-received-count', '1');
        self::assertNull($message->getReadAt());
        $this->client->submit($reply);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/messages');
        self::assertSelectorNotExists('a[href="/messages/'.$contact->getId().'?view=chat"] .chat-received-count');
        self::assertSelectorTextSame('a[href="/messages/'.$other->getId().'?view=chat"] .chat-received-count', '1');
        $this->client->loginUser($contact);
        $this->client->request('GET', '/messages/search', ['q' => 'unread-owner@example.test']);
        self::assertSelectorTextSame('.chat-received-count', '1');
        self::assertSelectorExists('.chat-received-count[aria-label="1 unread message"]');
    }

    public function testOldRepliesAlreadyClearEarlierMessagesButNotNewIncomingMessages(): void
    {
        $student = $this->person('old-reply-owner@example.test', ['ROLE_STUDENT']);
        $contact = $this->person('old-reply-contact@example.test', ['ROLE_STUDENT']);
        foreach ([[$contact, $student], [$student, $contact], [$contact, $student]] as [$sender, $recipient]) {
            $message = new OfficeMessage($sender, $recipient, $sender->getFullName());
            $message->setBody('Existing conversation');
            $this->manager->persist($message);
            $this->manager->flush();
        }
        $this->client->loginUser($student);
        $this->client->request('GET', '/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.chat-received-count', '1');
        $this->client->loginUser($contact);
        $this->client->request('GET', '/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.chat-received-count');
    }

    public function testChatPhotosCanBeSentAloneOrWithCaptionsAndRemainPrivate(): void
    {
        $student = $this->person('photo-sender@example.test', ['ROLE_STUDENT']);
        $staff = $this->person('photo-recipient@example.test', ['ROLE_STAFF']);
        $stranger = $this->person('photo-stranger@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($student);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFh0AAAAASUVORK5CYII=');
        $image = tempnam(sys_get_temp_dir(), 'chat-image-');
        $this->files[] = $image;
        file_put_contents($image, $bytes);
        $url = '/messages/'.$staff->getId();
        foreach (['Photo caption', ''] as $caption) {
            $this->client->request('GET', $url);
            self::assertSelectorExists('form.chat-compose-form[enctype="multipart/form-data"]');
            self::assertSelectorExists('input[type="file"][accept="image/jpeg,image/png,image/webp"]');
            $form = $this->client->getCrawler()->selectButton('Send')->form();
            $form['office_message[body]'] = $caption;
            $form['office_message[photo]']->upload($image);
            $this->client->submit($form);
            self::assertResponseRedirects($url, 303);
            $message = $this->manager->getRepository(OfficeMessage::class)->findOneBy([], ['id' => 'DESC']);
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $message->getPhotoFilename());
            $stored = self::getContainer()->getParameter('kernel.project_dir').'/var/chat-photos/'.$message->getPhotoFilename();
            $this->files[] = $stored;
            self::assertFileExists($stored);
            $photoUrl = '/messages/photos/'.$message->getId();
            $this->client->followRedirect();
            self::assertSelectorExists('.chat-message-photo img[src="'.$photoUrl.'"]');
            $this->client->request('GET', $photoUrl);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', 'image/png');
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            self::assertSame($bytes, file_get_contents($this->client->getResponse()->getFile()->getPathname()));
        }
        $this->client->request('GET', '/messages');
        self::assertSelectorTextContains('.chat-contact-preview', 'You: Photo');
        self::assertSame(2, $this->manager->getRepository(OfficeMessage::class)->count([]));
        $this->client->loginUser($staff);
        $this->client->request('GET', $photoUrl);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/messages/'.$student->getId());
        self::assertSelectorTextContains('.chat-messages', 'Photo caption');
        self::assertCount(2, $this->client->getCrawler()->filter('.chat-message-photo'));
        $this->client->loginUser($stranger);
        $this->client->request('GET', $photoUrl);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/logout');
        $this->client->request('GET', $photoUrl);
        self::assertResponseRedirects('/login');
    }

    public function testChatRejectsInvalidOversizedAndForgedPhotoUploads(): void
    {
        $student = $this->person('invalid-photo-sender@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($student);
        $url = '/messages/'.$this->user()->getId();
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFh0AAAAASUVORK5CYII=');
        foreach (['not an image', $bytes.str_repeat('x', 2_000_001)] as $content) {
            $file = tempnam(sys_get_temp_dir(), 'bad-chat-photo-');
            $this->files[] = $file;
            file_put_contents($file, $content);
            $this->client->request('GET', $url);
            $form = $this->client->getCrawler()->selectButton('Send')->form();
            $form['office_message[photo]']->upload($file);
            $this->client->submit($form);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->manager->getRepository(OfficeMessage::class)->count([]));
        }
        $file = tempnam(sys_get_temp_dir(), 'forged-chat-photo-');
        $this->files[] = $file;
        file_put_contents($file, $bytes);
        $this->client->request('GET', $url);
        $form = $this->client->getCrawler()->selectButton('Send')->form();
        $form['office_message[photo]']->upload($file);
        $form['office_message[_token]'] = 'invalid-csrf-token';
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->manager->getRepository(OfficeMessage::class)->count([]));
    }

    public function testChatAcceptsEveryFileTypeAndDownloadsRemainPrivate(): void
    {
        $student = $this->person('file-sender@example.test', ['ROLE_STUDENT']);
        $staff = $this->person('file-recipient@example.test', ['ROLE_STAFF']);
        $stranger = $this->person('file-stranger@example.test', ['ROLE_STUDENT']);
        $url = '/messages/'.$staff->getId();
        $samples = [
            'résumé.pdf' => "%PDF-1.7\nFile contents",
            'document.docx' => "PK\x03\x04Document contents",
            'archive.zip' => "PK\x03\x04Archive contents",
            'notes.csv' => "Name,Value\nSample,1",
            'code.php' => '<?php echo "Do not execute downloads";',
            'page.html' => '<script>alert("download only")</script>',
            'picture.svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
            'unknown.custom' => "\x00\x01\xffCustom binary data",
            'README' => 'No extension required.',
        ];
        foreach ($samples as $name => $contents) {
            $this->client->loginUser($student);
            $this->client->request('GET', $url);
            self::assertSelectorExists('input[type="file"][name="office_message[file]"]');
            self::assertSelectorNotExists('input[name="office_message[file]"][accept]');
            $path = tempnam(sys_get_temp_dir(), 'chat-file-');
            $this->files[] = $path;
            file_put_contents($path, $contents);
            $form = $this->client->getCrawler()->selectButton('Send')->form();
            $form['office_message[body]'] = 'notes.csv' === $name ? 'File with a caption' : '';
            $this->client->request('POST', $url, $form->getPhpValues(), ['office_message' => ['file' => new \Symfony\Component\HttpFoundation\File\UploadedFile($path, $name, null, null, true)]]);
            self::assertResponseRedirects($url, 303);
            $message = $this->manager->getRepository(OfficeMessage::class)->findOneBy([], ['id' => 'DESC']);
            self::assertSame($name, $message->getAttachmentName());
            self::assertSame(strlen($contents), $message->getAttachmentSize());
            self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.bin$/', $message->getAttachmentFilename());
            $this->files[] = self::getContainer()->getParameter('kernel.project_dir').'/var/chat-files/'.$message->getAttachmentFilename();
            $download = '/messages/files/'.$message->getId();
            $this->client->followRedirect();
            self::assertSelectorExists('.chat-message-file[href="'.$download.'"]');
            self::assertSelectorTextContains('.chat-message-file[href="'.$download.'"]', $name);
            $this->client->request('GET', $download);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', 'application/octet-stream');
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            self::assertStringStartsWith('attachment;', $this->client->getResponse()->headers->get('Content-Disposition'));
            self::assertSame($contents, file_get_contents($this->client->getResponse()->getFile()->getPathname()));
            $this->client->loginUser($staff);
            $this->client->request('GET', $download);
            self::assertResponseIsSuccessful();
        }
        self::assertSame(count($samples), $this->manager->getRepository(OfficeMessage::class)->count([]));
        $this->client->request('GET', '/messages');
        self::assertSelectorTextContains('.chat-contact-preview', 'File: README');
        $this->client->loginUser($stranger);
        $this->client->request('GET', $download);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/logout');
        $this->client->request('GET', $download);
        self::assertResponseRedirects('/login');
    }

    public function testChatRejectsOversizedAndForgedFileUploads(): void
    {
        $student = $this->person('invalid-file-sender@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($student);
        $url = '/messages/'.$this->user()->getId();
        foreach ([str_repeat('x', 2_000_001), 'Valid content but invalid CSRF'] as $index => $contents) {
            $path = tempnam(sys_get_temp_dir(), 'invalid-chat-file-');
            $this->files[] = $path;
            file_put_contents($path, $contents);
            $this->client->request('GET', $url);
            $form = $this->client->getCrawler()->selectButton('Send')->form();
            $form['office_message[file]']->upload($path);
            if (1 === $index) {
                $form['office_message[_token]'] = 'invalid-csrf-token';
            }
            $this->client->submit($form);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->manager->getRepository(OfficeMessage::class)->count([]));
        }
    }

    public function testDocumentTrackingAndConcernWordingIsConsistent(): void
    {
        $this->client->request('GET', '/staff/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Document tracking and monitoring');
        self::assertSelectorTextContains('.side-nav', 'Document tracking and monitoring');
        $this->client->request('GET', '/staff/concerns');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Concerns');
        self::assertStringNotContainsString('grievance', strtolower($this->client->getResponse()->getContent()));
        $this->client->loginUser($this->person('wording-student@example.test', ['ROLE_STUDENT']));
        $this->client->request('GET', '/portal/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Document tracking and monitoring');
        $this->client->request('GET', '/portal/concerns');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Concerns');
        $this->client->request('GET', '/portal/concerns/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('select[name="concern[type]"]', 'Complaint');
        self::assertStringNotContainsString('grievance', strtolower($this->client->getResponse()->getContent()));
    }

    public function testActivityLogHasOlderPagesAndHumanReadableActions(): void
    {
        $db = $this->manager->getConnection();
        for ($i = 0; $i < 55; ++$i) {
            $db->insert('office_activity', ['account_id' => $this->user()->getId(), 'profile_key' => 'mark', 'actor_name' => 'Mark Raymond P. Baldado', 'method' => 'POST', 'path' => '/vpsas/profile', 'route' => 'vpsas_profile', 'status_code' => 303, 'occurred_at' => '2026-10-21 01:00:00']);
        }
        $this->client->request('GET', '/vpsas/profile/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Uploaded profile picture');
        self::assertSelectorExists('a[href="/vpsas/profile/activity?page=2"]');
        $this->client->request('GET', '/vpsas/profile/activity?page=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Sir Mark');
    }

    public function testActivityLogMatchesTheRequestedColumnsAndLabels(): void
    {
        $rows = [
            ['cesar', 'Cesar Estrope', 'POST', 'staff_document_status', 'Updated', 'Updated document request'],
            ['mark', 'Mark Raymond P. Baldado', 'POST', 'staff_announcement_new', 'Created', 'Added a new announcement'],
            ['dianne', 'Dianne Futalan', 'POST', 'documentation_delete', 'Deleted', 'Deleted a documentation guide'],
            ['mark', 'Mark Raymond P. Baldado', 'POST', 'app_login', 'Logged In', 'Signed in to the system'],
        ];
        foreach ($rows as [$key, $name, $method, $route]) {
            $this->manager->getConnection()->insert('office_activity', ['account_id' => $this->user()->getId(), 'profile_key' => $key, 'actor_name' => $name, 'method' => $method, 'path' => '/test', 'route' => $route, 'status_code' => 303, 'occurred_at' => '2026-10-21 01:15:00']);
        }
        $this->client->request('GET', '/vpsas/profile/activity');
        self::assertResponseIsSuccessful();
        self::assertSame(['User', 'Action', 'Description', 'Date & Time'], $this->client->getCrawler()->filter('table thead th')->each(static fn ($cell) => $cell->text()));
        foreach (['Dr. Estrope', 'Sir Mark', 'Ma’am Dianne', 'Oct. 21, 2026, 9:15 AM'] as $expected) {
            self::assertSelectorTextContains('table', $expected);
        }
        foreach ($rows as [, , , , $action, $description]) {
            self::assertSelectorTextContains('table', $action);
            self::assertSelectorTextContains('table', $description);
        }
    }

    private function user(): User
    {
        return $this->manager->getRepository(User::class)->findOneBy(['email' => 'vpsas@norsu.edu.ph']);
    }

    private function person(string $email, array $roles): User
    {
        $user = (new User())->setEmail($email)->setFirstName('Test')->setLastName('User')->setRoles($roles)->setPassword('unused')->setCampus($this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']));
        $this->manager->persist($user);
        $this->manager->flush();
        return $user;
    }
}
