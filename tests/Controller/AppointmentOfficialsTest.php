<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AppointmentOfficialsTest extends WebTestCase
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

        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $campus = $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
        $student = (new User())->setEmail('officials-test@example.test')->setFirstName('Booking')->setLastName('Student')->setRoles(['ROLE_STUDENT'])->setPassword('unused')->setCampus($campus);
        $this->manager->persist($student);
        $this->manager->flush();
        $this->client->loginUser($student);
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

    public function testStudentWalkInQueueIsRemoved(): void
    {
        $this->client->request('GET', '/portal/appointments');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.side-nav a[href="/portal/queue"]');
        self::assertStringNotContainsString('Walk-in queue', $this->client->getCrawler()->filter('.side-nav')->text());
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/portal/queue');
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testOfficialsAreListedInOrganizationalChartOrder(): void
    {
        $crawler = $this->client->request('GET', '/portal/appointments/officials');

        self::assertResponseIsSuccessful();
        $cards = $crawler->filter('.official-card');
        self::assertCount(16, $cards);

        // Vice President first, then the three directors, then the unit heads grouped by directorate.
        $codes = $cards->each(static function (Crawler $card): string {
            preg_match('/unit=([A-Z_]+)/', (string) $card->attr('href'), $match);

            return $match[1] ?? '';
        });
        self::assertSame(
            ['VPSAS', 'SDS_OFFICE', 'SWS_OFFICE', 'ISPS_OFFICE', 'GOV', 'DISC', 'PUB', 'INTL', 'CARE', 'ENT', 'SPORT', 'CLINIC', 'SCHOL', 'FAITH', 'HOUS', 'SSN'],
            $codes,
        );
        self::assertStringContainsString('Estrope', $cards->first()->text());
        self::assertSelectorTextContains('.officials-tier-top', 'Office of the Vice President');
        self::assertSelectorNotExists('.officials [data-controller]');
    }

    public function testBookingPageShowsTheOfficialsCalendarAndDetailsForm(): void
    {
        $this->client->request('GET', '/portal/appointments/new?unit=GOV');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.booking[data-controller="booking-calendar"][data-booking-calendar-url-value="/appointment-slots"]');
        self::assertSelectorTextContains('.booking-info h2', 'Roche Cabanlit');
        self::assertSelectorExists('.booking-calendar [data-booking-calendar-target="days"]');
        self::assertSelectorExists('.booking-times');
        self::assertSelectorExists('select[name="appointment[topic]"]');
        self::assertSelectorExists('input[name="appointment[contactNumber]"]');
        self::assertSelectorExists('textarea[name="appointment[purpose]"]');
        self::assertSelectorExists('textarea[name="appointment[accommodationNote]"]');
        // The official is fixed by the chosen profile: only that unit can be submitted.
        self::assertCount(1, $this->client->getCrawler()->filter('select[name="appointment[unit]"] option'));
    }

    public function testStudentBooksTheChosenOfficial(): void
    {
        $this->client->request('GET', '/portal/appointments/new?unit=GOV');
        $when = $this->nextWeekday()->format('Y-m-d').'T10:00';
        $this->client->submitForm('Submit request', [
            'appointment[mode]' => 'physical',
            'appointment[scheduledAt]' => $when,
            'appointment[topic]' => 'Student organization or activity',
            'appointment[purpose]' => 'Plan the student council assembly.',
            'appointment[contactNumber]' => '0917 123 4567',
            'appointment[accommodationNote]' => 'Need a ground floor room.',
        ]);

        self::assertResponseStatusCodeSame(302);
        $saved = $this->manager->getRepository(Appointment::class)->findOneBy([]);
        self::assertSame('GOV', $saved->getUnit()->getCode());
        self::assertSame('Student organization or activity', $saved->getTopic());
        self::assertSame('0917 123 4567', $saved->getContactNumber());
        self::assertSame('Need a ground floor room.', $saved->getAccommodationNote());

        $official = $this->manager->getRepository(User::class)->findOneBy(['email' => 'studentgovernment@norsu.edu.ph']);
        $this->client->loginUser($official);
        $crawler = $this->client->request('GET', '/staff/appointments/calendar');
        self::assertResponseIsSuccessful();
        $events = json_decode($crawler->filter('[data-appointment-calendar-events-value]')->attr('data-appointment-calendar-events-value'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $events);
        $crawler = $this->client->request('GET', '/staff/appointments');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.people-table', $saved->getReferenceCode());
        $crawler = $this->client->request('GET', '/staff/appointments/'.$saved->getId());
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action$="/status"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/staff/appointments/'.$saved->getId().'/status', ['_token' => $token, 'status' => 'approved']);
        self::assertResponseRedirects();
        $crawler = $this->client->request('GET', '/staff/appointments/calendar');
        self::assertResponseIsSuccessful();
        $events = json_decode($crawler->filter('[data-appointment-calendar-events-value]')->attr('data-appointment-calendar-events-value'), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $events);
        self::assertSame($saved->getId(), $events[0]['id']);
        self::assertSame(substr($when, 0, 10), $events[0]['date']);
        self::assertSame('10:00', $events[0]['time']);
        self::assertSame('approved', $events[0]['status']);
        $this->client->request('GET', $events[0]['url']);
        self::assertResponseIsSuccessful();
    }

    public function testTopicIsRequiredWhenBookingAnOfficial(): void
    {
        $this->client->request('GET', '/portal/appointments/new?unit=GOV');
        $this->client->submitForm('Submit request', [
            'appointment[mode]' => 'physical',
            'appointment[scheduledAt]' => $this->nextWeekday()->format('Y-m-d').'T10:00',
            'appointment[purpose]' => 'Plan the student council assembly.',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.booking', 'Please choose what your visit is about.');
        self::assertSelectorExists('.booking-card');
        self::assertSame(0, $this->manager->getRepository(Appointment::class)->count([]));
    }

    public function testUnknownOfficeSendsTheStudentBackToTheOfficials(): void
    {
        $this->client->request('GET', '/portal/appointments/new?unit=NOPE');

        self::assertResponseRedirects('/portal/appointments/officials');
    }

    public function testPlainFormStillWorksWithoutAChosenOfficial(): void
    {
        $this->client->request('GET', '/portal/appointments/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[data-slot-picker-target="unit"]');
        self::assertSelectorNotExists('.booking-card');
    }

    private function nextWeekday(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today +1 weekday', new \DateTimeZone('Asia/Manila'));
    }
}
