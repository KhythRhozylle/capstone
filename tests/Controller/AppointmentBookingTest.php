<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AppointmentBookingTest extends WebTestCase
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

    #[DataProvider('bookingDates')]
    public function testBookingAllowsOnlyFutureOfficeDays(string $relativeDay, bool $allowed): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $campus = $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
        $unit = $this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'OSAS']);
        $student = (new User())->setEmail('booking-test@example.test')->setFirstName('Booking')->setLastName('Student')->setRoles(['ROLE_STUDENT'])->setPassword('unused')->setCampus($campus);
        $this->manager->persist($student);
        $this->manager->flush();
        $this->client->loginUser($student);
        $crawler = $this->client->request('GET', '/portal/appointments/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="appointment[meetingUrl]"]');
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));
        self::assertSame($today->format('Y-m-d').'T00:00', $crawler->filter('input[type="datetime-local"]')->attr('min'));

        // Submit directly to also exercise validation when browser restrictions are bypassed.
        $this->client->submitForm('Submit request', [
            'appointment[unit]' => $unit->getId(),
            'appointment[mode]' => 'physical',
            'appointment[scheduledAt]' => $today->modify($relativeDay)->format('Y-m-d').'T10:00',
            'appointment[purpose]' => 'Discuss student organization registration.',
        ]);
        if ($allowed) {
            self::assertResponseStatusCodeSame(302);
            self::assertSame(1, $this->manager->getRepository(Appointment::class)->count([]));
            $saved = $this->manager->getRepository(Appointment::class)->findOneBy([]);
            self::assertSame($today->modify($relativeDay)->format('Y-m-d').'T10:00', $saved->getScheduledAt()->setTimezone(new \DateTimeZone('Asia/Manila'))->format('Y-m-d\TH:i'));
        } else {
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('form', 'Please choose today or a future date for your appointment.');
            self::assertSame(0, $this->manager->getRepository(Appointment::class)->count([]));
        }
    }

    public static function bookingDates(): iterable
    {
        yield 'yesterday blocked' => ['-1 day', false];
        yield 'next weekday allowed' => ['+1 weekday', true];
        yield 'three weekdays ahead allowed' => ['+3 weekdays', true];
    }
}
