<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Service\OfficialStaffAccounts;
use Symfony\Component\BrowserKit\Cookie;
use PHPUnit\Framework\Attributes\DataProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityLoginTest extends WebTestCase
{
    private const TAB_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

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
        $this->client->submitForm('Log in', ['_username' => $email, '_password' => 'PASSWORD123!', '_browser_session' => self::TAB_KEY]);
        $this->client->setServerParameter('HTTP_X_BROWSER_SESSION', self::TAB_KEY);
        self::assertResponseRedirects('http://localhost/');
        $this->client->followRedirect();
        self::assertResponseRedirects($dashboard);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.user-chip', $this->manager->getRepository(User::class)->findOneBy(['email' => $email])->getFullName());
    }

    public function testLoginUsesOnlyABrowserSessionAndIgnoresRememberMeRequests(): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $crawler = $this->client->request('GET', '/login');
        self::assertSelectorNotExists('input[name="_remember_me"]');
        $values = $crawler->selectButton('Log in')->form()->getPhpValues();
        $this->client->request('POST', '/login', array_replace($values, [
            '_username' => 'studentgovernment@norsu.edu.ph',
            '_password' => 'PASSWORD123!',
            '_remember_me' => '1',
            '_browser_session' => self::TAB_KEY,
        ]));
        self::assertResponseRedirects('http://localhost/');
        self::assertNull($this->client->getCookieJar()->get('REMEMBERME'));
        $session = $this->client->getCookieJar()->get($this->client->getRequest()->getSession()->getName());
        self::assertNotNull($session);
        self::assertNull($session->getExpiresTime());
        self::assertTrue($session->isHttpOnly());

        $this->client->setServerParameter('HTTP_X_BROWSER_SESSION', self::TAB_KEY);

        // Navigation and refresh within the same browser session stay signed in.
        for ($i = 0; $i < 2; ++$i) {
            $this->client->request('GET', '/');
            self::assertResponseRedirects('/staff');
        }

        // Browsers discard session cookies when their session ends.
        $this->client->getCookieJar()->expire($session->getName());
        $this->client->request('GET', '/staff');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testPreviousLoginCookiesCannotRestoreAuthentication(): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'studentgovernment@norsu.edu.ph',
            '_password' => 'PASSWORD123!',
            '_browser_session' => self::TAB_KEY,
        ]);
        self::assertResponseRedirects('http://localhost/');
        $session = $this->client->getCookieJar()->get($this->client->getRequest()->getSession()->getName());
        self::assertNotNull($session);
        $this->client->getCookieJar()->expire($session->getName());
        $this->client->getCookieJar()->set(new Cookie('PHPSESSID', $session->getValue()));
        $this->client->getCookieJar()->set(new Cookie('REMEMBERME', 'previous-login', time() + 604800));

        $this->client->request('GET', '/staff');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testLandingPageDoesNotOfferPersistentLogin(): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name="_remember_me"]');
    }

    public function testClosedTabCannotReadProtectedPagesWithOnlyTheSharedCookie(): void
    {
        $this->signIn();
        $this->client->request('GET', '/staff');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-browser-session-bridge][data-target="/staff"]');
        self::assertSelectorNotExists('.user-chip');
        self::assertStringNotContainsString(self::TAB_KEY, $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');

        // The empty-tab client uses Symfony's normal logout before displaying anything private.
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/staff', server: ['HTTP_X_BROWSER_SESSION' => self::TAB_KEY]);
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testReloadContinuesWithASingleUseTicketAndKeepsItsQuery(): void
    {
        $this->signIn();
        $this->client->request('GET', '/staff?view=reload');
        self::assertSelectorExists('[data-browser-session-bridge][data-target="/staff?view=reload"]');
        $url = $this->continueTo('/staff?view=reload');
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.user-chip');
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');

        $this->client->request('GET', $url);
        self::assertSelectorExists('[data-browser-session-bridge]');
        self::assertSelectorNotExists('.user-chip');
    }

    public function testContinuationCannotBeUsedAtADifferentTargetOrForAPost(): void
    {
        $this->signIn();
        $url = $this->continueTo('/staff?view=one');
        $this->client->request('GET', str_replace('view=one', 'view=two', $url));
        self::assertSelectorExists('[data-browser-session-bridge]');
        self::assertSelectorNotExists('.user-chip');

        $url = $this->continueTo('/profile/edit');
        $this->client->request('POST', $url, ['profile' => ['firstName' => 'Forged', 'lastName' => 'Change']]);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('X-Browser-Session-Expired', '1');
        self::assertNotSame('Forged', $this->manager->getRepository(User::class)->findOneBy(['email' => 'studentgovernment@norsu.edu.ph'])->getFirstName());
    }

    public function testMissingProofBlocksAnOtherwiseValidProfileUpdate(): void
    {
        $this->signIn();
        $crawler = $this->client->request('GET', '/profile/edit', server: ['HTTP_X_BROWSER_SESSION' => self::TAB_KEY]);
        $values = $crawler->selectButton('Save changes')->form()->getPhpValues();
        $values['profile']['firstName'] = 'Forged';
        $this->client->request('POST', '/profile/edit', $values);
        self::assertResponseStatusCodeSame(401);
        self::assertNotSame('Forged', $this->manager->getRepository(User::class)->findOneBy(['email' => 'studentgovernment@norsu.edu.ph'])->getFirstName());
    }

    public function testNativeFormProofPreservesCsrfValidation(): void
    {
        $this->signIn();
        $crawler = $this->client->request('GET', '/profile/edit', server: ['HTTP_X_BROWSER_SESSION' => self::TAB_KEY]);
        $values = $crawler->selectButton('Save changes')->form()->getPhpValues();
        $values['_browser_session'] = self::TAB_KEY;
        $values['profile']['firstName'] = 'Updated';
        $invalid = $values;
        $invalid['profile']['_token'] = 'invalid';
        $this->client->request('POST', '/profile/edit', $invalid);
        self::assertResponseStatusCodeSame(422);
        self::assertNotSame('Updated', $this->manager->getRepository(User::class)->findOneBy(['email' => 'studentgovernment@norsu.edu.ph'])->getFirstName());
        $this->client->request('POST', '/profile/edit', $values);
        self::assertResponseIsSuccessful();
        self::assertSame('Updated', $this->manager->getRepository(User::class)->findOneBy(['email' => 'studentgovernment@norsu.edu.ph'])->getFirstName());
    }

    public function testPrintAndPdfDownloadsAcceptAuthorizedNativeNavigation(): void
    {
        $this->signIn();
        $url = $this->continueTo('/staff/document-records/print');
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table');
        self::assertSelectorNotExists('[data-browser-session-bridge]');
        $url = $this->continueTo('/staff/document-records/pdf');
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', $this->client->getResponse()->getContent());
    }

    public static function invalidContinuationTargets(): iterable
    {
        yield 'external URL' => ['https://example.com'];
        yield 'network path' => ['//example.com'];
        yield 'backslash' => ['/\\example.com'];
        yield 'control character' => ["/staff\n"];
        yield 'fragment' => ['/staff#section'];
    }

    #[DataProvider('invalidContinuationTargets')]
    public function testContinuationRejectsExternalAndMalformedTargets(string $target): void
    {
        $this->signIn();
        $this->client->jsonRequest('POST', '/session/continue', ['target' => $target], ['HTTP_X_BROWSER_SESSION' => self::TAB_KEY]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMalformedLoginProofNeverLeavesAnUnguardedLogin(): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $crawler = $this->client->request('GET', '/login');
        $values = $crawler->selectButton('Log in')->form()->getPhpValues();
        $this->client->request('POST', '/login', array_replace($values, [
            '_username' => 'studentgovernment@norsu.edu.ph',
            '_password' => 'PASSWORD123!',
            '_browser_session' => ['malformed'],
        ]));
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/staff');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testSessionChecksPreservePrivateFlashesAndDoNotCreateActivityEntries(): void
    {
        $this->signIn();
        $session = $this->client->getRequest()->getSession();
        $session->getFlashBag()->add('success', 'Private appointment reference SAS-12345');
        $session->save();
        $before = $this->manager->getConnection()->fetchOne('SELECT COUNT(*) FROM office_activity');
        $this->client->request('GET', '/staff');
        self::assertSelectorExists('[data-browser-session-bridge]');
        self::assertStringNotContainsString('SAS-12345', $this->client->getResponse()->getContent());
        $url = $this->continueTo('/staff');
        self::assertSame($before, $this->manager->getConnection()->fetchOne('SELECT COUNT(*) FROM office_activity'));
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Private appointment reference SAS-12345', $this->client->getResponse()->getContent());
    }

    public function testSigningInAgainInvalidatesOldProofAndNavigationTickets(): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $crawler = $this->client->request('GET', '/login');
        $values = $crawler->selectButton('Log in')->form()->getPhpValues();
        $values['_username'] = 'studentgovernment@norsu.edu.ph';
        $values['_password'] = 'PASSWORD123!';
        $values['_browser_session'] = self::TAB_KEY;
        $this->client->request('POST', '/login', $values);
        self::assertResponseRedirects('http://localhost/');
        $oldUrl = $this->continueTo('/staff');

        $values['_browser_session'] = str_repeat('f', 64);
        $this->client->request('POST', '/login', $values);
        self::assertResponseRedirects('http://localhost/');
        $this->client->request('GET', $oldUrl);
        self::assertSelectorExists('[data-browser-session-bridge]');
        self::assertSelectorNotExists('.user-chip');
        $this->client->request('GET', '/staff', server: ['HTTP_X_BROWSER_SESSION' => $values['_browser_session']]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.user-chip');
        $this->client->request('GET', '/staff', server: ['HTTP_X_BROWSER_SESSION' => self::TAB_KEY]);
        self::assertResponseStatusCodeSame(401);
    }

    private function signIn(): void
    {
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $this->client->request('GET', '/login');
        $this->client->submitForm('Log in', [
            '_username' => 'studentgovernment@norsu.edu.ph',
            '_password' => 'PASSWORD123!',
            '_browser_session' => self::TAB_KEY,
        ]);
        self::assertResponseRedirects('http://localhost/');
    }

    private function continueTo(string $target): string
    {
        $this->client->jsonRequest('POST', '/session/continue', ['target' => $target], ['HTTP_X_BROWSER_SESSION' => self::TAB_KEY]);
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['url'];
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
