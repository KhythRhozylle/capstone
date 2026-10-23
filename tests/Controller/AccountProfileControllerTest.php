<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Campus;
use App\Entity\StudentProfile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;

class AccountProfileControllerTest extends WebTestCase
{
    private array $environment;
    private KernelBrowser $client;
    private EntityManagerInterface $manager;
    private array $files = [];

    protected function setUp(): void
    {
        $this->environment = [$_ENV['DATABASE_URL'] ?? null, $_SERVER['DATABASE_URL'] ?? null];
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->manager = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) { unlink($file); }
        }
        parent::tearDown();
        [$env, $server] = $this->environment;
        if (null === $env) { unset($_ENV['DATABASE_URL']); } else { $_ENV['DATABASE_URL'] = $env; }
        if (null === $server) { unset($_SERVER['DATABASE_URL']); } else { $_SERVER['DATABASE_URL'] = $server; }
    }

    public function testNewAccountsOfEveryRoleHaveTheSameProfileCard(): void
    {
        foreach (['ROLE_STUDENT', 'ROLE_STAFF', 'ROLE_GUIDANCE', 'ROLE_USER'] as $index => $role) {
            $user = $this->person('new-profile-'.$index.'@example.test', [$role]);
            $this->client->loginUser($user);
            $this->client->request('GET', '/profile');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('.account-person-name', 'New User');
            self::assertSelectorExists('.office-person-card .office-profile-picture');
            self::assertSelectorExists('.office-photo-add[data-action="office-photo#choose"]');
            $this->client->request('GET', '/profile', [], [], ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('turbo-frame#office-profile-panel .office-person-card');
            self::assertSelectorExists('input[name="account_photo_inline[photo]"]');
            self::assertStringNotContainsString('Other profiles', $this->client->getResponse()->getContent());
        }
        foreach (['sds@norsu.edu.ph', 'studentgovernment@norsu.edu.ph'] as $email) {
            $this->client->loginUser($this->manager->getRepository(User::class)->findOneBy(['email' => $email]));
            $this->client->request('GET', '/profile');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.office-person-card .office-photo-add');
        }
        $this->client->loginUser($this->manager->getRepository(User::class)->findOneBy(['email' => 'vpsas@norsu.edu.ph']));
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/vpsas/profile');
    }

    public function testPersonalPhotosReplaceOldFilesAndAppearInHeaderAndChat(): void
    {
        foreach (['ROLE_STUDENT', 'ROLE_STAFF'] as $index => $role) {
            $user = $this->person('photo-profile-'.$index.'@example.test', [$role]);
            $other = $this->person('photo-profile-other-'.$index.'@example.test', ['ROLE_STUDENT']);
            $this->client->loginUser($user);
            $first = $this->upload($user);
            $second = $this->upload($user);
            self::assertFileDoesNotExist($first);
            self::assertFileExists($second);
            self::assertNull($this->manager->find(User::class, $other->getId())->getProfilePhoto('account'));
            $this->client->request('GET', '/profile');
            self::assertSelectorExists('turbo-stream[target="account-profile-avatar"] template img[src$="'.basename($second).'"]');
            self::assertSelectorExists('.office-profile-picture img[src$="'.basename($second).'"]');
            $this->client->loginUser($other);
            $this->client->request('GET', '/messages/search', ['q' => $user->getEmail()]);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.chat-avatar img[src$="'.basename($second).'"]');
        }
    }

    public function testInlinePhotoUploadUpdatesTheAccountChip(): void
    {
        $user = $this->person('inline-profile@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($user);
        $this->client->setServerParameter('HTTP_TURBO_FRAME', 'office-profile-panel');
        $this->client->request('GET', '/profile');
        $this->client->submitForm('Upload photo', ['account_photo_inline[photo]' => $this->png()]);
        self::assertResponseRedirects('/profile', 303);
        $filename = $this->manager->find(User::class, $user->getId())->getProfilePhoto('account');
        $this->files[] = $this->photoPath($filename);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-stream[target="account-profile-avatar"] template img[src$="'.$filename.'"]');
        self::assertSelectorExists('.office-profile-picture img[src$="'.$filename.'"]');
    }

    public function testInvalidPhotosAndForgedRequestsCannotChangeAnotherAccount(): void
    {
        $user = $this->person('protected-profile@example.test', ['ROLE_STUDENT']);
        $other = $this->person('protected-other@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($user);
        foreach (['<script>not a photo</script>', str_repeat('x', 2_000_001)] as $contents) {
            $path = tempnam(sys_get_temp_dir(), 'bad-account-photo-');
            $this->files[] = $path;
            file_put_contents($path, $contents);
            $this->client->request('GET', '/profile');
            $this->client->submitForm('Upload photo', ['account_photo[photo]' => $path]);
            self::assertResponseStatusCodeSame(422);
            self::assertNull($this->manager->find(User::class, $user->getId())->getProfilePhoto('account'));
        }
        $this->client->request('GET', '/profile?user='.$other->getId());
        $this->client->submitForm('Upload photo', ['account_photo[photo]' => $this->png(), 'account_photo[_token]' => 'invalid']);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->manager->find(User::class, $other->getId())->getProfilePhoto('account'));
        $this->client->request('GET', '/profile?user='.$other->getId());
        $this->client->submitForm('Upload photo', ['account_photo[photo]' => $this->png()]);
        self::assertResponseStatusCodeSame(303);
        $filename = $this->manager->find(User::class, $user->getId())->getProfilePhoto('account');
        $this->files[] = $this->photoPath($filename);
        self::assertNotNull($filename);
        self::assertNull($this->manager->find(User::class, $other->getId())->getProfilePhoto('account'));
    }

    public function testStudentDetailsRemainEditableAlongsideThePhotoCard(): void
    {
        $user = $this->person('edit-profile@example.test', ['ROLE_STUDENT']);
        $profile = (new StudentProfile())->setUser($user)->setStudentNumber('PROFILE-001')->setProgram('BSIT')->setCollege('CCS')->setYearLevel('4')->setContactNumber('09123456789');
        $user->setStudentProfile($profile);
        $this->manager->persist($profile);
        $this->manager->flush();
        $this->client->loginUser($user);
        $photo = $this->upload($user);
        $this->client->request('GET', '/portal');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/portal/profile"]');
        self::assertSelectorExists('summary.account-profile-trigger');
        $this->client->request('GET', '/profile', [], [], ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertSelectorExists('a.account-profile-edit[href="/profile/edit"][data-turbo-frame="office-profile-panel"]');
        self::assertStringNotContainsString('Open full profile', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/profile/edit', [], [], ['HTTP_TURBO_FRAME' => 'office-profile-panel']);
        self::assertSelectorExists('a[href="/profile"][data-turbo-frame="office-profile-panel"]');
        $this->client->submitForm('Save changes', ['profile[firstName]' => 'Updated', 'profile[lastName]' => 'Student', 'profile[contactNumber]' => '09999999999', 'profile[program]' => 'BSCS', 'profile[college]' => 'CCS', 'profile[yearLevel]' => '4']);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->client->getResponse()->headers->has('Location'));
        self::assertSelectorTextSame('turbo-stream[target="account-profile-name"] template', 'Updated Student');
        self::assertSelectorTextContains('.account-person-name', 'Updated Student');
        self::assertSelectorTextContains('.office-person-contact', '09999999999');
        self::assertSelectorExists('.office-profile-picture img[src$="'.basename($photo).'"]');
    }

    public function testAllRolesCanEditInThePopupAndInvalidOrForgedChangesAreRejected(): void
    {
        foreach (['ROLE_STUDENT', 'ROLE_STAFF', 'ROLE_GUIDANCE', 'ROLE_USER'] as $index => $role) {
            $user = $this->person('popup-edit-'.$index.'@example.test', [$role]);
            $other = $this->person('popup-other-'.$index.'@example.test', ['ROLE_STUDENT']);
            $this->client->loginUser($user);
            $this->client->request('GET', '/profile/edit?user='.$other->getId());
            self::assertSelectorNotExists('input[name="profile[email]"]');
            $this->client->submitForm('Save changes', ['profile[firstName]' => '', 'profile[lastName]' => 'User']);
            self::assertResponseStatusCodeSame(422);
            self::assertSame('New', $user->getFirstName());
            $this->client->request('GET', '/profile/edit');
            $this->client->submitForm('Save changes', ['profile[firstName]' => 'Changed', 'profile[lastName]' => 'Name', 'profile[_token]' => 'invalid']);
            self::assertResponseStatusCodeSame(422);
            self::assertSame('New', $user->getFirstName());
            $this->client->request('GET', '/profile/edit?user='.$other->getId());
            $this->client->submitForm('Save changes', ['profile[firstName]' => 'Changed', 'profile[lastName]' => 'Name']);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('.account-person-name', 'Changed Name');
            self::assertSame('New', $other->getFirstName());
            self::assertSame('Changed', $this->manager->find(User::class, $user->getId())->getFirstName());
        }
        $this->client->request('GET', '/portal/profile');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRememberedUsersCanOpenTheirProfileAndAnonymousUsersCannot(): void
    {
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
        $user = $this->person('remembered-profile@example.test', ['ROLE_STUDENT']);
        $this->client->loginUser($user);
        $this->client->request('GET', '/portal');
        $token = new RememberMeToken($user, 'main');
        $session = $this->client->getRequest()->getSession();
        $session->set('_security_main', serialize($token));
        $session->save();
        self::getContainer()->get('security.untracked_token_storage')->setToken($token);
        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.office-person-card');
    }

    private function person(string $email, array $roles): User
    {
        $user = (new User())->setEmail($email)->setFirstName('New')->setLastName('User')->setRoles($roles)->setPassword('unused')->setCampus($this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']));
        $this->manager->persist($user);
        $this->manager->flush();
        return $user;
    }

    private function png(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'account-photo-');
        $this->files[] = $path;
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFh0AAAAASUVORK5CYII='));
        return $path;
    }

    private function photoPath(string $filename): string
    {
        return self::getContainer()->getParameter('kernel.project_dir').'/public/uploads/profile-photos/'.$filename;
    }

    private function upload(User $user): string
    {
        $this->client->request('GET', '/profile');
        $this->client->submitForm('Upload photo', ['account_photo[photo]' => $this->png()]);
        self::assertResponseRedirects('/profile', 303);
        $filename = $this->manager->find(User::class, $user->getId())->getProfilePhoto('account');
        self::assertMatchesRegularExpression('/^'.$user->getId().'-account-[a-f0-9]{32}\.png$/', $filename);
        $path = $this->photoPath($filename);
        $this->files[] = $path;
        return $path;
    }
}
