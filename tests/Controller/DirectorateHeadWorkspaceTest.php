<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Announcement;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\Concern;
use App\Entity\DocumentRequest;
use App\Entity\Feedback;
use App\Entity\OfficeMessage;
use App\Entity\OfficeUnavailable;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\FeedbackKind;
use App\Service\OfficialStaffAccounts;
use App\Service\StaffWorkspace;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;

class DirectorateHeadWorkspaceTest extends WebTestCase
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

    public static function officeAccounts(): iterable
    {
        foreach (OfficialStaffAccounts::ACCOUNTS as [$email]) {
            if ('vpsas@norsu.edu.ph' !== $email) {
                yield $email => [$email];
            }
        }
    }

    #[DataProvider('officeAccounts')]
    public function testEveryDirectorAndHeadHasAConsistentOfficeWorkspace(string $email): void
    {
        $account = $this->account($email);
        $this->client->loginUser($account);
        $workspace = self::getContainer()->get(StaffWorkspace::class);
        $unit = $workspace->personalUnit($account);
        self::assertNotNull($unit, $email);
        foreach (['/staff', '/staff/appointments', '/documentation', '/staff/specialist', '/staff/feedback', '/staff/announcements', '/staff/announcements/archive', '/messages', '/staff/activity', '/staff/appointments/calendar', '/staff/office-hours'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            $nav = $this->client->getCrawler()->filter('.side-nav')->text();
            foreach (['Appointments', 'Documentation guide', 'Feedbacks', 'Specialist desk', 'Activity log'] as $label) {
                self::assertStringContainsString($label, $nav, $email.' '.$path);
            }
            foreach (['Service requests', 'Walk-in queue', 'Calendar', 'Office hours', 'VPSAS dashboard', 'CARE desk'] as $removed) {
                self::assertStringNotContainsString($removed, $nav, $email.' '.$path);
            }
            self::assertSelectorExists('a.topbar-icon[aria-label="Chat"]');
        }
        $this->client->request('GET', '/staff/specialist');
        $specialist = StaffWorkspace::directorateCode($account) ? StaffWorkspace::directorateCode($account).' Directorate' : $unit->getName();
        self::assertSelectorTextContains('h1', $specialist);
        if ('CARE' === $unit->getCode()) {
            self::assertSelectorTextContains('.side-nav', 'Counseling and follow-ups');
            self::assertSelectorTextContains('main', 'Counseling, assessments');
        } else {
            self::assertStringNotContainsString('Counseling and follow-ups', $this->client->getCrawler()->filter('.side-nav')->text());
        }
        foreach (['/staff/services', '/staff/queue'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(16, $this->manager->getRepository(User::class)->count([]));
    }

    #[DataProvider('officeAccounts')]
    public function testOnlyTheOwnerCanChangeTheirUnavailableDates(string $email): void
    {
        $owner = $this->account($email);
        $unit = self::getContainer()->get(StaffWorkspace::class)->personalUnit($owner);
        $day = self::getContainer()->get(ClockInterface::class)->now()->setTimezone(new \DateTimeZone('Asia/Manila'))->modify('next monday')->modify('+7 days');
        $path = '/appointments/availability/'.$unit->getCode();
        $this->client->loginUser($owner);
        $this->client->request('GET', $path);
        $this->client->submitForm('Mark unavailable', ['office_unavailable[day]' => $day->format('Y-m-d')]);
        self::assertResponseRedirects($path, 303);
        $entry = $this->manager->getRepository(OfficeUnavailable::class)->findOneBy(['owner' => $owner]);
        self::assertNotNull($entry);
        $this->client->request('GET', $path);
        $deleteToken = $this->client->getCrawler()->filter('form.availability-entry input[name="_token"]')->attr('value');
        $other = $this->account('sds@norsu.edu.ph' === $email ? 'sws@norsu.edu.ph' : 'sds@norsu.edu.ph');
        $this->client->loginUser($other);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[name="office_unavailable"]');
        $this->client->request('POST', $path, ['office_unavailable' => ['day' => $day->format('Y-m-d')]]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/appointments/availability/'.$entry->getId().'/delete', ['_token' => $deleteToken]);
        self::assertResponseStatusCodeSame(403);
        $student = $this->student('availability@example.test');
        $this->client->loginUser($student);
        $this->client->request('GET', $path, ['year' => $day->format('Y'), 'month' => $day->format('n')]);
        self::assertSelectorExists('.office-availability-day.is-unavailable[aria-disabled="true"]');
        self::assertSelectorNotExists('form[name="office_unavailable"]');
        $this->client->request('GET', '/appointment-slots', ['unit' => $unit->getId(), 'date' => $day->format('Y-m-d')]);
        self::assertResponseIsSuccessful();
        $slots = json_decode($this->client->getResponse()->getContent(), true)['slots'];
        self::assertNotEmpty($slots);
        self::assertSame([false], array_values(array_unique(array_column($slots, 'available'))));
        $this->client->request('GET', '/portal/appointments/new');
        $this->client->submitForm('Submit request', ['appointment[unit]' => $unit->getId(), 'appointment[mode]' => 'physical', 'appointment[scheduledAt]' => $day->format('Y-m-d').'T09:00', 'appointment[purpose]' => 'Unavailable slot']);
        self::assertSelectorTextContains('form', 'unavailable');
        self::assertSame(0, $this->manager->getRepository(Appointment::class)->count([]));
        $this->client->loginUser($owner);
        $this->client->request('POST', '/appointments/availability/'.$entry->getId().'/delete', ['_token' => $deleteToken]);
        self::assertResponseRedirects($path, 303);
        self::assertSame(0, $this->manager->getRepository(OfficeUnavailable::class)->count([]));
    }

    #[DataProvider('officeAccounts')]
    public function testCalendarPopupMarksAndRestoresTheClickedHour(string $email): void
    {
        $account = $this->account($email);
        $this->client->loginUser($account);
        $this->client->request('GET', '/staff/appointments/calendar');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[popover="auto"] button');
        self::assertSelectorNotExists('dialog, select[name^="office_unavailable"], input[name^="office_unavailable"]');
        $token = $this->client->getCrawler()->filter('[data-appointment-calendar-csrf-value]')->attr('data-appointment-calendar-csrf-value');
        $day = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $payload = ['date' => $day, 'start' => 540, 'unavailable' => true];
        $url = '/staff/appointments/calendar/availability';
        $this->client->jsonRequest('POST', $url, $payload, ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        $unit = self::getContainer()->get(StaffWorkspace::class)->personalUnit($account);
        $entry = $this->manager->getRepository(OfficeUnavailable::class)->findOneBy(['unit' => $unit]);
        self::assertNotNull($entry);
        self::assertSame(540, $entry->getStartMinute());
        self::assertSame(600, $entry->getEndMinute());
        self::assertSame($account->getId(), $entry->getOwner()->getId());
        self::assertSame([['date' => $day, 'start' => 540, 'end' => 600]], json_decode($this->client->getResponse()->getContent(), true)['periods']);
        $this->client->jsonRequest('POST', $url, $payload, ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->manager->getRepository(OfficeUnavailable::class)->count([]));
        $payload['unavailable'] = false;
        $this->client->jsonRequest('POST', $url, $payload, ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true)['periods']);
        self::assertSame(0, $this->manager->getRepository(OfficeUnavailable::class)->count([]));
    }

    #[DataProvider('officeAccounts')]
    public function testCalendarUnavailablePeriodBlocksOnlyOverlappingSlots(string $email): void
    {
        $owner = $this->account($email);
        $this->client->loginUser($owner);
        $unit = self::getContainer()->get(StaffWorkspace::class)->personalUnit($owner);
        $day = self::getContainer()->get(ClockInterface::class)->now()->setTimezone(new \DateTimeZone('Asia/Manila'))->modify('next monday')->modify('+7 days')->format('Y-m-d');
        $this->client->request('GET', '/staff/appointments/calendar');
        self::assertSelectorExists('[popover] button');
        self::assertSelectorNotExists('.page-header a[href*="availability"]');
        $this->client->request('GET', '/appointments/availability/'.$unit->getCode());
        $this->client->submitForm('Mark unavailable', ['office_unavailable[day]' => $day, 'office_unavailable[startMinute]' => '570', 'office_unavailable[endMinute]' => '630']);
        self::assertResponseRedirects('/appointments/availability/'.$unit->getCode(), 303);
        $entry = $this->manager->getRepository(OfficeUnavailable::class)->findOneBy(['unit' => $unit]);
        self::assertSame(570, $entry->getStartMinute());
        self::assertSame(630, $entry->getEndMinute());
        $this->client->followRedirect();

        $this->client->submitForm('Mark unavailable', ['office_unavailable[day]' => $day, 'office_unavailable[startMinute]' => '600', 'office_unavailable[endMinute]' => '660']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'already unavailable');
        $this->client->submitForm('Mark unavailable', ['office_unavailable[day]' => $day, 'office_unavailable[startMinute]' => '660', 'office_unavailable[endMinute]' => '600']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'end time must be after');
        $student = $this->student('time-slots@example.test');
        $this->client->loginUser($student);
        $this->client->request('GET', '/appointment-slots', ['unit' => $unit->getId(), 'date' => $day]);
        self::assertResponseIsSuccessful();
        $slots = array_column(json_decode($this->client->getResponse()->getContent(), true)['slots'], null, 'time');
        self::assertTrue($slots['08:00']['available']);
        self::assertFalse($slots['09:00']['available']);
        self::assertFalse($slots['10:00']['available']);
        self::assertTrue($slots['11:00']['available']);
        $this->client->request('GET', '/appointments/availability/'.$unit->getCode());
        self::assertSelectorTextContains('main', '9:30 AM');
        self::assertSelectorNotExists('button[type="submit"]');
        self::assertSelectorNotExists('.office-availability-day.is-unavailable');
        $this->client->request('GET', '/portal/appointments/new');
        $this->client->submitForm('Submit request', ['appointment[unit]' => $unit->getId(), 'appointment[mode]' => 'physical', 'appointment[scheduledAt]' => $day.'T09:00', 'appointment[purpose]' => 'Blocked timed slot']);
        self::assertSelectorTextContains('form', 'unavailable');
        self::assertSame(0, $this->manager->getRepository(Appointment::class)->count([]));
    }

    public function testDirectoratesAndHeadsCannotReadOrUpdateOtherOfficesRecords(): void
    {
        $student = $this->student('records@example.test');
        $records = [];
        foreach (['GOV', 'ENT', 'CLINIC', 'VPSAS'] as $code) {
            $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => $code]);
            $appointment = (new Appointment())->setReferenceCode('APT-'.$code)->setPurpose('Purpose '.$code)->setStudent($student)->setCampus($student->getCampus())->setUnit($unit);
            $document = (new DocumentRequest())->setReferenceCode('DOC-'.$code)->setPurpose('Purpose '.$code)->setStudent($student)->setCampus($student->getCampus())->setUnit($unit);
            $concern = (new Concern())->setReferenceCode('CON-'.$code)->setSubject('Concern '.$code)->setDescription('Description')->setStudent($student)->setCampus($student->getCampus())->setAssignedUnit($unit);
            foreach ([$appointment, $document, $concern] as $record) { $this->manager->persist($record); }
            $records[$code] = [$appointment, $document, $concern];
        }
        $this->manager->flush();
        foreach (['sds@norsu.edu.ph' => 'GOV', 'sws@norsu.edu.ph' => 'ENT', 'isps@norsu.edu.ph' => 'CLINIC', 'studentgovernment@norsu.edu.ph' => 'GOV'] as $email => $allowed) {
            $this->client->loginUser($this->account($email));
            foreach (['appointments' => [0, 'APT-'], 'documents' => [1, 'DOC-'], 'concerns' => [2, 'CON-']] as $path => [$index, $marker]) {
                $this->client->request('GET', '/staff/'.$path);
                self::assertResponseIsSuccessful();
                self::assertSelectorTextContains('main', $marker.$allowed);
                $listText = $this->client->getCrawler()->filter('main')->text();
                foreach ($records as $code => $recordSet) {
                    if ($code === $allowed) { continue; }
                    self::assertStringNotContainsString($marker.$code, $listText);
                    $this->client->request('GET', '/staff/'.$path.'/'.$recordSet[$index]->getId());
                    self::assertResponseStatusCodeSame(403);
                }
            }
            $foreign = $records['GOV' === $allowed ? 'ENT' : 'GOV'][0];
            $this->client->request('POST', '/staff/appointments/'.$foreign->getId().'/status', ['status' => 'approved']);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testFeedbackAndPrivateChatFollowAccountAssignments(): void
    {
        $student = $this->student('feedback-chat@example.test');
        foreach (['GOV' => 'SDS feedback', 'ENT' => 'SWS feedback'] as $code => $comment) {
            $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => $code]);
            $entry = (new Feedback($student, $unit, FeedbackKind::Appointment))->setRating(5)->setComment($comment);
            $this->manager->persist($entry);
        }
        $this->manager->flush();
        foreach (['sds@norsu.edu.ph' => 'SDS', 'sws@norsu.edu.ph' => 'SWS', 'studentgovernment@norsu.edu.ph' => 'SDS'] as $email => $code) {
            $this->client->loginUser($this->account($email));
            $this->client->request('GET', '/staff/feedback');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('table', $code.' feedback');
            self::assertStringNotContainsString(('SDS' === $code ? 'SWS' : 'SDS').' feedback', $this->client->getCrawler()->filter('table')->text());
        }
        $head = $this->account('studentgovernment@norsu.edu.ph');
        $this->client->request('GET', '/messages/'.$student->getId());
        $this->client->submitForm('Send', ['office_message[body]' => 'Message from the student government head']);
        self::assertResponseStatusCodeSame(303);
        $message = $this->manager->getRepository(OfficeMessage::class)->findOneBy([]);
        self::assertSame($head->getFullName(), $message->getSenderName());
        $this->client->loginUser($student);
        $this->client->request('GET', '/messages/'.$head->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.office-chat-messages', 'Message from the student government head');
        $this->client->submitForm('Send', ['office_message[body]' => 'Reply from the student']);
        self::assertResponseStatusCodeSame(303);
        $other = $this->student('other-student@example.test');
        $this->client->request('GET', '/messages/'.$other->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('.office-chat-messages', 'Message from the student government head');
    }

    public function testHeadAnnouncementsArchiveAndActivityStayAccessible(): void
    {
        $head = $this->account('universityclinic@norsu.edu.ph');
        $announcement = (new Announcement())->setTitle('Older clinic notice')->setBody('Clinic notice')->setPublishedBy($head);
        (new \ReflectionProperty(Announcement::class, 'publishedAt'))->setValue($announcement, self::getContainer()->get(ClockInterface::class)->now()->modify('-8 days'));
        $this->manager->persist($announcement);
        $this->manager->flush();
        $this->client->loginUser($head);
        $this->client->request('GET', '/staff/announcements');
        self::assertSelectorExists('a[aria-label="Announcement archive"]');
        self::assertStringNotContainsString('Older clinic notice', $this->client->getCrawler()->filter('main')->text());
        $this->client->request('GET', '/staff/announcements/archive');
        self::assertSelectorTextContains('main', 'Older clinic notice');
        $this->client->request('GET', '/staff/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', $head->getFullName());
    }

    public function testMissingUnitAssignmentsDoNotExposeAllRecords(): void
    {
        $head = (new User())->setEmail('unassigned@example.test')->setFirstName('Unassigned')->setLastName('Head')->setRoles(['ROLE_UNIT_HEAD', 'ROLE_STAFF'])->setPassword('unused');
        $this->manager->persist($head);
        $this->manager->flush();
        $this->client->loginUser($head);
        $this->client->request('GET', '/staff/appointments');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href^="/appointments/availability/"]');
        $this->client->request('GET', '/staff/feedback');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'No feedback');
    }

    public function testVpsasCanCreateEditAndDeleteDocuments(): void
    {
        $this->testOfficialsCanCreateEditAndDeleteDocuments('vpsas@norsu.edu.ph');
    }

    #[DataProvider('officeAccounts')]
    public function testOfficialsCanCreateEditAndDeleteDocuments(string $email): void
    {
        $official = $this->account($email);
        $student = $this->student('crud-student@example.test');
        $unit = self::getContainer()->get(StaffWorkspace::class)->personalUnit($official);
        $this->client->loginUser($official);
        $this->client->request('GET', '/staff/documents/new');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Create document', [
            'staff_document[student]' => $student->getId(),
            'staff_document[unit]' => $unit->getId(),
            'staff_document[documentType]' => 'sas_certification',
            'staff_document[purpose]' => 'Official created record',
            'staff_document[status]' => 'submitted',
        ]);
        self::assertResponseStatusCodeSame(303);
        $document = $this->manager->getRepository(DocumentRequest::class)->findOneBy(['purpose' => 'Official created record']);
        self::assertNotNull($document);
        $id = $document->getId();
        self::assertStringStartsWith('DOC-', $document->getReferenceCode());
        self::assertSame($student->getCampus()->getId(), $document->getCampus()->getId());
        $this->client->request('GET', '/staff/documents/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Save changes', ['staff_document[purpose]' => 'Updated document purpose', 'staff_document[status]' => 'released']);
        self::assertResponseStatusCodeSame(303);
        $document = $this->manager->find(DocumentRequest::class, $id);
        self::assertSame('Updated document purpose', $document->getPurpose());
        self::assertNotNull($document->getReleasedAt());
        $this->client->loginUser($student);
        $this->client->request('GET', '/portal/documents/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Updated document purpose');
        $this->client->request('GET', '/staff/documents/new');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($official);
        $this->client->request('POST', '/staff/documents/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->manager->find(DocumentRequest::class, $id));
        $crawler = $this->client->request('GET', '/staff/documents/'.$id);
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/staff/documents/'.$id.'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/staff/documents', 303);
        self::assertNull($this->manager->find(DocumentRequest::class, $id));
        $this->client->request('GET', '/staff/documents/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDocumentCrudRejectsOtherOfficesAndInvalidInput(): void
    {
        $student = $this->student('crud-access@example.test');
        $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'GOV']);
        $foreign = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'ENT']);
        $document = (new DocumentRequest())->setReferenceCode('DOC-FOREIGN')->setStudent($student)->setCampus($student->getCampus())->setUnit($foreign)->setPurpose('Foreign record');
        $this->manager->persist($document);
        $this->manager->flush();
        $this->client->loginUser($this->account('studentgovernment@norsu.edu.ph'));
        $this->client->request('GET', '/staff/documents/'.$document->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/staff/documents/'.$document->getId().'/delete');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/staff/documents/new');
        $this->client->submitForm('Create document', ['staff_document[student]' => $student->getId(), 'staff_document[unit]' => $unit->getId(), 'staff_document[purpose]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->manager->getRepository(DocumentRequest::class)->count([]));
        $crawler = $this->client->request('GET', '/staff/documents/new');
        $token = $crawler->filter('input[name="staff_document[_token]"]')->attr('value');
        $this->client->request('POST', '/staff/documents/new', ['staff_document' => ['student' => $student->getId(), 'unit' => $foreign->getId(), 'documentType' => 'sas_certification', 'status' => 'submitted', 'purpose' => 'Tampered office', '_token' => $token]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->manager->getRepository(DocumentRequest::class)->count([]));
        $crawler = $this->client->request('GET', '/staff/documents/new');
        $token = $crawler->filter('input[name="staff_document[_token]"]')->attr('value');
        $this->client->request('POST', '/staff/documents/new', ['staff_document' => ['student' => '', 'unit' => '', 'documentType' => '', 'status' => '', 'purpose' => 'Missing required fields', '_token' => $token]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->manager->getRepository(DocumentRequest::class)->count([]));
    }

    private function user(): User
    {
        return $this->account('vpsas@norsu.edu.ph');
    }

    private function account(string $email): User
    {
        return $this->manager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function student(string $email): User
    {
        $user = (new User())->setEmail($email)->setFirstName('Student')->setLastName('Visitor')->setRoles(['ROLE_STUDENT'])->setPassword('unused')->setCampus($this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']));
        $this->manager->persist($user);
        $this->manager->flush();
        return $user;
    }
}
