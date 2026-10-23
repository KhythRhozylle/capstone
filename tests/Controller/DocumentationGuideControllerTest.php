<?php

namespace App\Tests\Controller;

use App\Entity\DocumentationGuide;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DocumentationGuideControllerTest extends WebTestCase
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

    #[DataProvider('editors')]
    public function testOnlyEditorsCanPublishEditAndDelete(string $email, string $role): void
    {
        $editor = $this->user($email, $role);
        $this->client->loginUser($editor);
        $crawler = $this->client->request('GET', '/documentation');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.side-nav a[href="/documentation"][aria-current="page"]');
        self::assertSelectorExists('a[href="/documentation/new"]');
        $this->client->request('GET', '/documentation/new');
        $this->client->submitForm('Publish guide', [
            'documentation_guide[title]' => 'How to request documents',
            'documentation_guide[body]' => "Select the document.\nSubmit your request.",
        ]);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'How to request documents');
        $guide = $this->manager->getRepository(DocumentationGuide::class)->findOneBy(['title' => 'How to request documents']);
        self::assertSame($editor->getId(), $guide->getCreatedBy()->getId());
        $id = $guide->getId();

        // The other authorized editor can update the same guide.
        $other = $this->user($email === 'vpsas@norsu.edu.ph' ? 'secretary@norsu.edu.ph' : 'vpsas@norsu.edu.ph', $role === 'ROLE_VPSAS' ? 'ROLE_VPSAS_SECRETARIAT' : 'ROLE_VPSAS');
        $this->client->loginUser($other);
        $this->client->request('GET', '/documentation/'.$id.'/edit');
        $this->client->submitForm('Save changes', ['documentation_guide[body]' => 'Updated instructions <script>alert(1)</script>']);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.documentation-content', 'Updated instructions');
        self::assertSelectorNotExists('.documentation-content script');
        $this->manager->clear();
        self::assertSame($other->getId(), $this->manager->find(DocumentationGuide::class, $id)->getUpdatedBy()->getId());

        $student = $this->user('reader@example.test', 'ROLE_STUDENT');
        $this->client->loginUser($student);
        $this->client->request('GET', '/documentation/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.documentation-content', 'Updated instructions');
        self::assertSelectorNotExists('a[href$="/edit"]');
        self::assertSelectorNotExists('form[action$="/delete"]');
        $this->client->request('GET', '/documentation');
        self::assertSelectorNotExists('a[href="/documentation/new"]');

        $this->client->loginUser($this->manager->find(User::class, $other->getId()));
        $this->client->request('POST', '/documentation/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/documentation/'.$id);
        $this->client->submitForm('Delete guide');
        self::assertResponseStatusCodeSame(303);
        self::assertNull($this->manager->find(DocumentationGuide::class, $id));
    }

    public static function editors(): iterable
    {
        yield 'Cesar' => ['vpsas@norsu.edu.ph', 'ROLE_VPSAS'];
        yield 'Dianne' => ['secretary@norsu.edu.ph', 'ROLE_VPSAS_SECRETARIAT'];
    }

    #[DataProvider('nonEditors')]
    public function testDirectMutationRoutesAreForbidden(string $email, string $role, bool $canRead): void
    {
        $author = $this->user('vpsas@norsu.edu.ph', 'ROLE_VPSAS');
        $guide = (new DocumentationGuide())->setTitle('Existing guide')->setBody('Original body');
        $guide->publish($author);
        $this->manager->persist($guide);
        $this->manager->flush();
        $id = $guide->getId();
        $this->client->loginUser($this->user($email, $role));
        $this->client->request('GET', '/documentation');
        self::assertResponseStatusCodeSame($canRead ? 200 : 403);
        foreach ([['GET', '/new'], ['POST', '/new'], ['GET', '/'.$id.'/edit'], ['POST', '/'.$id.'/edit'], ['POST', '/'.$id.'/delete']] as [$method, $path]) {
            $this->client->request($method, '/documentation'.$path);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame('Original body', $this->manager->find(DocumentationGuide::class, $id)->getBody());
    }

    public static function nonEditors(): iterable
    {
        yield 'student' => ['student@example.test', 'ROLE_STUDENT', true];
        yield 'staff' => ['staff@example.test', 'ROLE_STAFF', true];
        yield 'another executive' => ['executive@example.test', 'ROLE_VPSAS', true];
        yield 'another secretary' => ['secretary@example.test', 'ROLE_VPSAS_SECRETARIAT', true];
    }

    public function testAnonymousVisitorsMustSignIn(): void
    {
        $this->client->request('GET', '/documentation');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testBlankGuidesCannotBePublished(): void
    {
        $this->client->loginUser($this->user('secretary@norsu.edu.ph', 'ROLE_VPSAS_SECRETARIAT'));
        $this->client->request('GET', '/documentation/new');
        $this->client->submitForm('Publish guide', ['documentation_guide[title]' => ' ', 'documentation_guide[body]' => ' ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->manager->getRepository(DocumentationGuide::class)->count([]));
    }

    private function user(string $email, string $role): User
    {
        $user = (new User())->setEmail($email)->setFirstName('Test')->setLastName('User')->setRoles([$role])->setPassword('unused-test-password');
        $this->manager->persist($user);
        $this->manager->flush();

        return $user;
    }
}
