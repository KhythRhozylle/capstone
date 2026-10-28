<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\DocumentRequest;
use App\Entity\StatusHistory;
use App\Entity\User;
use App\Service\AppNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OfficeProfileControllerTest extends WebTestCase
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

    public function testSignOutRequiresConfirmation(): void
    {
        $this->client->request('GET', '/vpsas');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[aria-label="Sign out"][data-action="logout-confirmation#open"]');
        self::assertSelectorNotExists('a[aria-label="Sign out"]');
        self::assertSelectorTextContains('dialog h2', 'Are you sure you want to log out?');
        self::assertSelectorTextContains('dialog a[href="/logout"]', 'Yes');
        self::assertSelectorTextContains('dialog button[data-action="logout-confirmation#close"]', 'No');
        self::assertSelectorNotExists('dialog[open]');
    }

    public function testMenuSwitchesProfilesWithoutCreatingAccounts(): void
    {
        $this->client->request('GET', '/vpsas');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.office-avatar-link[href="/vpsas/profile"]');
        self::assertSelectorTextContains('summary.user-chip-name', 'Cesar Estrope');
        self::assertSelectorTextContains('.office-profile-dropdown', 'Dianne Futalan');
        self::assertSelectorTextContains('.office-profile-dropdown', 'Mark Raymond P. Baldado');
        $this->client->submitForm('Dianne Futalan');
        self::assertResponseRedirects('/vpsas/profile', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Dianne Gutual-Futalan');
        self::assertSelectorTextContains('.office-person-position', 'Secretary');
        self::assertSelectorExists('.office-photo-add[data-action="office-photo#choose"]');
        self::assertCount(2, $this->client->getCrawler()->filter('.office-other-profiles button'));
        self::assertSelectorTextContains('summary.user-chip-name', 'Dianne Futalan');
        $this->client->submitForm('Mark Raymond P. Baldado');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Mark Raymond P. Baldado');
        self::assertSelectorTextContains('.office-person-position', 'Office Staff');
        self::assertSame(16, $this->manager->getRepository(User::class)->count([]));
        self::assertSame('vpsas@norsu.edu.ph', $this->user()->getEmail());
    }

    public function testActionsKeepTheSelectedNameAfterAnotherSwitch(): void
    {
        $this->switchTo('Dianne Futalan');
        $this->client->request('GET', '/documentation/new');
        $this->client->submitForm('Publish guide', ['documentation_guide[title]' => 'Dianne guide', 'documentation_guide[body]' => 'Instructions.']);
        self::assertResponseStatusCodeSame(303);
        $this->switchTo('Mark Raymond P. Baldado');
        $this->client->request('GET', '/documentation/new');
        $this->client->submitForm('Publish guide', ['documentation_guide[title]' => 'Mark guide', 'documentation_guide[body]' => 'Instructions.']);
        self::assertResponseStatusCodeSame(303);
        $rows = $this->manager->getConnection()->fetchAllAssociative('SELECT actor_name FROM office_activity WHERE route = ? AND method = ? ORDER BY id', ['documentation_new', 'POST']);
        self::assertSame(['Dianne Futalan', 'Mark Raymond P. Baldado'], array_column($rows, 'actor_name'));
        $this->client->request('GET', '/vpsas/profile/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Ma’am Dianne');
        self::assertSelectorTextContains('table', 'Sir Mark');
    }

    public function testStatusHistorySnapshotsTheSelectedPerson(): void
    {
        $this->switchTo('Dianne Futalan');
        self::getContainer()->set(AppNotifier::class, $this->createStub(AppNotifier::class));
        $service = (new DocumentRequest())->setReferenceCode('DOC-PROFILE')->setPurpose('Profile test')
            ->setStudent($this->user())
            ->setCampus($this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']))
            ->setUnit($this->manager->getRepository(SasUnit::class)->findOneBy(['code' => 'OSAS']));
        $this->manager->persist($service);
        $this->manager->flush();
        $this->client->request('GET', '/staff/documents/'.$service->getId());
        $this->client->submitForm('Update status', ['status' => 'processing']);
        self::assertResponseRedirects('/staff/documents/'.$service->getId());
        $this->switchTo('Mark Raymond P. Baldado');
        $this->manager->clear();
        $history = $this->manager->getRepository(StatusHistory::class)->findOneBy([]);
        self::assertSame('Dianne Futalan', $history->getActorDisplayName());
        self::assertSame($this->user()->getId(), $history->getActor()->getId());
        $this->client->request('GET', '/staff/documents/'.$service->getId());
        self::assertSelectorTextContains('.timeline', 'Dianne Futalan');
    }

    public function testPhotosAreSeparateAndReplacementsRemoveTheOldFile(): void
    {
        $this->switchTo('Dianne Futalan');
        $first = $this->uploadPng('dianne');
        $second = $this->uploadPng('dianne');
        self::assertNotSame($first, $second);
        self::assertFileDoesNotExist($first);
        $this->switchTo('Mark Raymond P. Baldado');
        $mark = $this->uploadPng('mark');
        self::assertFileExists($second);
        self::assertFileExists($mark);
        self::assertNull($this->user()->getProfilePhoto('cesar'));
        $this->client->request('GET', '/vpsas/profile');
        self::assertSelectorExists('a.office-avatar-link img');
        self::assertSelectorExists('.office-profile-picture img');
        $this->switchTo('Dianne Futalan');
        self::assertSelectorExists('a.office-avatar-link img[src$="'.$this->user()->getProfilePhoto('dianne').'"]');
    }

    public function testNonImagesAndStaleProfileFormsAreRejected(): void
    {
        $fake = tempnam(sys_get_temp_dir(), 'office-photo-');
        $this->files[] = $fake;
        file_put_contents($fake, '<script>alert(1)</script>');
        $this->client->request('GET', '/vpsas/profile');
        $this->client->submitForm('Upload photo', ['office_profile_photo[photo]' => $fake]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->user()->getProfilePhoto('cesar'));
        $crawler = $this->client->request('GET', '/vpsas/profile');
        $oldToken = $crawler->filter('input[name="office_profile_photo[_token]"]')->attr('value');
        $this->switchTo('Dianne Futalan');
        $this->client->request('POST', '/vpsas/profile', ['office_profile_photo' => ['_token' => $oldToken]]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->user()->getProfilePhoto('dianne'));
    }

    public function testProfileSwitchRequiresCsrfAndKnownChoice(): void
    {
        $this->client->request('POST', '/vpsas/profile/switch', ['office_profile_switch' => ['profile' => 'mark', '_token' => 'invalid']]);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/vpsas/profile');
        $token = $crawler->filter('input[name="office_profile_switch[_token]"]')->first()->attr('value');
        $this->client->request('POST', '/vpsas/profile/switch', ['office_profile_switch' => ['profile' => 'unknown', '_token' => $token]]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/vpsas/profile');
        self::assertSelectorTextContains('h1', 'Dr. Cesar P. Estrope');
    }

    public function testLogoutRecordsTheSelectedPersonAndNewLoginStartsWithCesar(): void
    {
        $this->switchTo('Dianne Futalan');
        $this->client->request('GET', '/logout');
        $actor = $this->manager->getConnection()->fetchOne('SELECT actor_name FROM office_activity WHERE route = ?', ['app_logout']);
        self::assertSame('Dianne Futalan', $actor);
        $this->client->request('GET', '/login');
        $tabKey = str_repeat('c', 64);
        $this->client->submitForm('Log in', ['_username' => 'vpsas@norsu.edu.ph', '_password' => 'PASSWORD123!', '_browser_session' => $tabKey]);
        $this->client->setServerParameter('HTTP_X_BROWSER_SESSION', $tabKey);
        self::assertResponseRedirects('http://localhost/');
        $this->client->request('GET', '/vpsas/profile');
        self::assertSelectorTextContains('h1', 'Dr. Cesar P. Estrope');
    }

    public function testProfileAndActivityReplaceTheDropdownWithFrameResponses(): void
    {
        $this->client->request('GET', '/vpsas');
        self::assertSelectorExists('.office-profile-dropdown turbo-frame#office-profile-panel');
        self::assertSelectorExists('a.office-avatar-link[data-turbo-frame="office-profile-panel"]');
        $this->client->request('GET', '/vpsas/profile', server: ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#office-profile-panel .office-person-card');
        self::assertSelectorNotExists('.app-shell');
        self::assertSelectorExists('a[href="/vpsas/profile/menu"]');
        $this->client->submitForm('Dianne Gutual-Futalan', serverParameters: ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertResponseRedirects('/vpsas/profile', 303);
        $this->client->request('GET', '/vpsas/profile', server: ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertSelectorTextContains('.office-person-position', 'Secretary');
        self::assertSelectorTextContains('turbo-stream[target="office-profile-name"] template', 'Dianne Futalan');
        $this->client->request('GET', '/vpsas/profile/activity', server: ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertSelectorExists('turbo-frame#office-profile-panel table');
        self::assertSelectorNotExists('.app-shell');
        $this->client->request('GET', '/vpsas/profile/menu');
        self::assertSelectorTextContains('turbo-frame#office-profile-panel', 'Who is using this account?');
        self::assertSelectorNotExists('.office-person-card');
    }

    public function testDropdownPhotoUploadPostsToProfileAndRefreshesBothPictures(): void
    {
        $this->client->request('GET', '/vpsas');
        $this->client->request('GET', '/vpsas/profile', server: ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertSelectorExists('form.office-photo-form[action="/vpsas/profile"][enctype="multipart/form-data"]');
        $file = tempnam(sys_get_temp_dir(), 'office-frame-photo-');
        $this->files[] = $file;
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
        $this->client->submitForm('Upload photo', ['office_profile_photo[photo]' => $file], serverParameters: ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertResponseRedirects('/vpsas/profile', 303);
        $this->manager->clear();
        $photo = $this->user()->getProfilePhoto('cesar');
        self::assertNotNull($photo);
        $path = dirname(__DIR__, 2).'/public/uploads/profile-photos/'.$photo;
        $this->files[] = $path;
        self::assertFileExists($path);
        $this->client->request('GET', '/vpsas/profile', server: ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#office-profile-panel .office-profile-picture img[src$="'.$photo.'"]');
        self::assertSelectorExists('turbo-stream[target="office-profile-avatar"] template img[src$="'.$photo.'"]');
        self::assertSelectorNotExists('.app-shell');
    }

    public function testOtherAccountsCannotUseOrSeeTheSelector(): void
    {
        $other = (new User())->setEmail('other@example.test')->setRoles(['ROLE_VPSAS'])->setFirstName('Other')->setLastName('User')->setPassword('unused');
        $this->manager->persist($other);
        $this->manager->flush();
        $this->client->loginUser($other);
        $this->client->request('GET', '/vpsas');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.account-profile-menu');
        self::assertSelectorNotExists('.office-profile-choices');
        self::assertSelectorNotExists('.office-other-profiles');
        foreach (['/vpsas/profile', '/vpsas/profile/activity'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/vpsas/profile/switch', ['office_profile_switch' => ['profile' => 'mark']]);
        self::assertResponseStatusCodeSame(403);
    }

    private function user(): User
    {
        return $this->manager->getRepository(User::class)->findOneBy(['email' => 'vpsas@norsu.edu.ph']);
    }

    private function switchTo(string $name): void
    {
        $this->client->request('GET', '/vpsas/profile');
        $this->client->submitForm($name);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
    }

    private function uploadPng(string $key): string
    {
        $file = tempnam(sys_get_temp_dir(), 'office-photo-');
        $this->files[] = $file;
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
        $this->client->request('GET', '/vpsas/profile');
        $this->client->submitForm('Upload photo', ['office_profile_photo[photo]' => $file]);
        self::assertResponseRedirects('/vpsas/profile', 303);
        $photo = $this->user()->getProfilePhoto($key);
        self::assertNotNull($photo);
        $path = dirname(__DIR__, 2).'/public/uploads/profile-photos/'.$photo;
        $this->files[] = $path;
        self::assertFileExists($path);

        return $path;
    }
}
