<?php

namespace App\Tests\Controller;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotificationTest extends WebTestCase
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

    #[DataProvider('accounts')]
    public function testBadgesUseUnreadRecordsAndDisappearAfterReading(string $role): void
    {
        $user = $this->user('reader@example.test', $role);
        $other = $this->user('other@example.test', 'ROLE_STUDENT');
        $ids = [];
        for ($i = 0; $i < 3; ++$i) {
            $notification = new Notification($user, 'Update '.$i, 'Your request was updated.');
            $this->manager->persist($notification);
            $ids[] = $notification;
        }
        $this->manager->persist((new Notification($user, 'Already read', 'Old update'))->markRead());
        $this->manager->persist(new Notification($other, 'Other account', 'Private update'));
        $this->manager->flush();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.notification-bell .notification-count', '3');
        self::assertSelectorNotExists('.side-nav a[title="Notifications"]');
        self::assertCount(1, $crawler->filter('.topnav .notification-bell'));
        self::assertSelectorExists('.notification-bell[href="'.($role === 'ROLE_STUDENT' ? '/portal/notifications' : '/notifications').'"]');
        self::assertCount(3, $crawler->filter('button:contains("Mark read")'));
        self::assertSelectorNotExists('.notification-bell .notification-count[hidden]');
        $form = $crawler->filter('form[action="/notifications/'.$ids[0]->getId().'/read"]')->form();
        $values = $form->getPhpValues();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextSame('.notification-bell .notification-count', '2');
        self::assertSelectorNotExists('.side-nav .notification-count');
        $firstReadAt = $this->manager->find(Notification::class, $ids[0]->getId())->getReadAt();
        // Repeating a read must neither reduce the count again nor change its read time.
        $this->client->request('POST', '/notifications/'.$ids[0]->getId().'/read', $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextSame('.notification-bell .notification-count', '2');
        self::assertEquals($firstReadAt, $this->manager->find(Notification::class, $ids[0]->getId())->getReadAt());
        $this->client->submitForm('Mark all read');
        self::assertResponseStatusCodeSame(303);
        $this->manager->clear();
        $this->client->followRedirect();
        self::assertSelectorExists('.notification-bell .notification-count[hidden]');
        self::assertSelectorTextSame('.notification-bell .notification-count', '');
        self::assertSelectorNotExists('.side-nav .notification-count');
        self::assertSelectorNotExists('button:contains("Mark all read")');
        $this->client->request('GET', '/notifications/count?user='.$other->getId());
        self::assertResponseIsSuccessful();
        self::assertSame(['unreadCount' => 0], json_decode($this->client->getResponse()->getContent(), true));
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $this->client->loginUser($this->manager->find(User::class, $other->getId()));
        $this->client->request('GET', '/notifications/count');
        self::assertSame(['unreadCount' => 1], json_decode($this->client->getResponse()->getContent(), true));
    }

    public static function accounts(): iterable
    {
        yield 'student' => ['ROLE_STUDENT'];
        yield 'staff' => ['ROLE_STAFF'];
        yield 'guidance' => ['ROLE_GUIDANCE'];
        yield 'director' => ['ROLE_DIRECTOR'];
        yield 'Cesar role' => ['ROLE_VPSAS'];
        yield 'Dianne role' => ['ROLE_VPSAS_SECRETARIAT'];
    }

    public function testCountIncludesAllUnreadRecordsBeyondThePageLimit(): void
    {
        $user = $this->user('many@example.test', 'ROLE_STUDENT');
        for ($i = 0; $i < 105; ++$i) {
            $this->manager->persist(new Notification($user, 'Update '.$i, 'Message'));
        }
        $this->manager->flush();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/portal/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.notification-bell .notification-count', '105');
        self::assertCount(50, $crawler->filter('.simple-list > li'));
        $this->client->submitForm('Mark all read');
        $this->manager->clear();
        $this->client->followRedirect();
        self::assertSelectorExists('.notification-bell .notification-count[hidden]');
        $this->client->request('GET', '/notifications/count');
        self::assertSame(['unreadCount' => 0], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testReadingRequiresCsrfAndCannotAffectOtherAccounts(): void
    {
        $user = $this->user('owner@example.test', 'ROLE_STUDENT');
        $other = $this->user('outsider@example.test', 'ROLE_STUDENT');
        $notification = new Notification($user, 'Private', 'Message');
        $this->manager->persist($notification);
        $this->manager->flush();
        $id = $notification->getId();
        $this->client->loginUser($other);
        $this->client->request('POST', '/notifications/'.$id.'/read', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/notifications/read-all', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->manager->clear();
        self::assertFalse($this->manager->find(Notification::class, $id)->isRead());
    }

    public function testStudentDashboardOmitsTheRecentNotificationsCard(): void
    {
        $user = $this->user('dashboard-notifications@example.test', 'ROLE_STUDENT');
        $this->manager->persist(new Notification($user, 'Private chat update', 'Open chat to read and reply.'));
        $this->manager->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/portal');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Recent notifications', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Updates on your requests', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Private chat update', $this->client->getResponse()->getContent());
        self::assertContains('Announcements', $this->client->getCrawler()->filter('.panel-title')->each(static fn ($node) => $node->text()));
        self::assertSelectorTextSame('.notification-bell .notification-count', '1');
        $this->client->request('GET', '/portal/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Private chat update');
    }

    public function testAnonymousCannotReadTheUnreadCount(): void
    {
        $this->client->request('GET', '/notifications/count');
        self::assertResponseRedirects('http://localhost/login');
    }

    #[DataProvider('accounts')]
    public function testUsersCanDeleteOneNotificationAndClearOnlyTheirOwn(string $role): void
    {
        $user = $this->user('delete-owner@example.test', $role);
        $other = $this->user('delete-other@example.test', 'ROLE_STUDENT');
        $first = new Notification($user, 'First update', 'A private update');
        $second = (new Notification($user, 'Second update', 'Already read'))->markRead();
        $private = new Notification($other, 'Other account update', 'Keep this');
        foreach ([$first, $second, $private] as $notification) {
            $this->manager->persist($notification);
        }
        $this->manager->flush();
        $firstId = $first->getId();
        $privateId = $private->getId();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/notifications');
        self::assertSelectorTextSame('.notification-date-group', 'Today');
        self::assertSelectorExists('.notification-card.is-unread');
        self::assertCount(2, $crawler->filter('.notification-delete'));
        $form = $crawler->filter('form[action="/notifications/'.$firstId.'/delete"]')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $this->manager->clear();
        self::assertNull($this->manager->find(Notification::class, $firstId));
        $this->client->followRedirect();
        self::assertSelectorNotExists('.notification-card.is-unread');
        self::assertSelectorExists('.notification-bell .notification-count[hidden]');
        self::assertSelectorTextContains('.notification-card', 'Second update');
        // Clearing includes records beyond the fifty displayed cards.
        $owner = $this->manager->find(User::class, $user->getId());
        for ($i = 0; $i < 55; ++$i) {
            $this->manager->persist(new Notification($owner, 'Extra '.$i, 'Update'));
        }
        $this->manager->flush();
        $this->client->submitForm('Clear All');
        self::assertResponseStatusCodeSame(303);
        $this->manager->clear();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.notification-empty', "You're all caught up");
        self::assertSelectorNotExists('button:contains("Clear All")');
        self::assertNotNull($this->manager->find(Notification::class, $privateId));
        self::assertSame(1, $this->manager->getRepository(Notification::class)->count([]));
    }

    public function testDeletingRequiresValidCsrfAndOwnership(): void
    {
        $owner = $this->user('secure-owner@example.test', 'ROLE_STUDENT');
        $other = $this->user('secure-other@example.test', 'ROLE_STUDENT');
        $notification = new Notification($owner, 'Protected update', 'Keep this notification');
        $this->manager->persist($notification);
        $this->manager->flush();
        $id = $notification->getId();
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/notifications');
        $values = $crawler->filter('form[action="/notifications/'.$id.'/delete"]')->form()->getPhpValues();
        $this->client->request('POST', '/notifications/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/notifications/clear-all', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($other);
        $this->client->request('POST', '/notifications/'.$id.'/delete', $values);
        self::assertResponseStatusCodeSame(403);
        $this->manager->clear();
        self::assertNotNull($this->manager->find(Notification::class, $id));
        self::assertFalse($this->manager->find(Notification::class, $id)->isRead());
    }

    private function user(string $email, string $role): User
    {
        $user = (new User())->setEmail($email)->setRoles([$role])->setFirstName('Notification')->setLastName('User')->setPassword('unused');
        $this->manager->persist($user);
        $this->manager->flush();

        return $user;
    }
}
