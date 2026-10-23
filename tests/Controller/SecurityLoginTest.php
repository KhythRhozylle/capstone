<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Service\OfficialStaffAccounts;
use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityLoginTest extends WebTestCase
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

    public static function officialAccounts(): iterable
    {
        foreach (OfficialStaffAccounts::ACCOUNTS as [$email, , , $role, $compatibilityRole]) {
            yield $email => [$email, 'ROLE_VPSAS' === $role ? '/vpsas' : ('ROLE_GUIDANCE' === $compatibilityRole ? '/care' : '/staff')];
        }
    }

    #[DataProvider('officialAccounts')]
    public function testOfficialAccountCanLogInThroughTheForm(string $email, string $dashboard): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="password"][data-password-visibility-target="input"]');
        self::assertSelectorExists('button[type="button"][aria-label="Show password"][aria-controls="password"]');
        $this->client->submitForm('Log in', ['_username' => $email, '_password' => 'PASSWORD123!']);
        self::assertResponseRedirects('http://localhost/');
        $this->client->followRedirect();
        self::assertResponseRedirects($dashboard);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.user-chip', $this->manager->getRepository(User::class)->findOneBy(['email' => $email])->getFullName());
    }

    public function testRegistrationPasswordAndConfirmationBothHaveEyeButtons(): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.password-field input[type="password"]'));
        self::assertCount(2, $crawler->filter('.password-field button[aria-label="Show password"]'));
        foreach ($crawler->filter('.password-field') as $field) {
            $input = $field->getElementsByTagName('input')->item(0);
            $button = $field->getElementsByTagName('button')->item(0);
            self::assertSame($input->getAttribute('id'), $button->getAttribute('aria-controls'));
        }
    }
}
