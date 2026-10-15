<?php

namespace App\Tests\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NotificationControllerTest extends WebTestCase
{
    private int $flushes = 0;

    public function testStudentCanMarkAnUnreadNotificationAsReadUsingItsRenderedForm(): void
    {
        [$client, $notification] = $this->notificationClient(true);
        $client->request('GET', '/portal/notifications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.content', 'Unread');
        self::assertSelectorExists('form[action="/portal/notifications/17/read"] input[name="_token"]');

        $client->submitForm('Mark read');

        self::assertResponseRedirects('/portal/notifications');
        self::assertTrue($notification->isRead());
        self::assertSame(1, $this->flushes);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.content', 'Read');
        self::assertSelectorNotExists('form[action="/portal/notifications/17/read"]');
    }

    public function testInvalidCsrfTokenDoesNotMarkNotificationAsRead(): void
    {
        [$client, $notification] = $this->notificationClient(false);
        $client->request('GET', '/portal/notifications');
        $client->submitForm('Mark read', ['_token' => 'invalid-token']);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($notification->isRead());
        self::assertSame(0, $this->flushes);
    }

    /** @return array{KernelBrowser, Notification} */
    private function notificationClient(bool $expectWrite): array
    {
        $client = static::createClient();
        $client->disableReboot();
        $user = (new User())
            ->setEmail('notification-test@example.com')
            ->setFirstName('Test')
            ->setLastName('Student')
            ->setRoles(['ROLE_STUDENT']);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 42);
        $notification = new Notification($user, 'Appointment approved', 'Your appointment has been approved.');
        (new \ReflectionProperty(Notification::class, 'id'))->setValue($notification, 17);

        // Keep the complete HTTP flow in memory, including security user refresh.
        $provider = $this->createStub(EntityUserProvider::class);
        $provider->method('refreshUser')->willReturn($user);
        $provider->method('supportsClass')->willReturn(true);
        static::getContainer()->set('security.user.provider.concrete.app_user_provider', $provider);

        $repository = $this->createMock(NotificationRepository::class);
        $repository->method('latestFor')->willReturn([$notification]);
        if ($expectWrite) {
            $repository->expects(self::once())->method('find')->with(17)->willReturn($notification);
        } else {
            $repository->expects(self::never())->method('find');
        }
        static::getContainer()->set(NotificationRepository::class, $repository);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('isOpen')->willReturn(true);
        $metadataFactory = $this->createStub(ClassMetadataFactory::class);
        $metadataFactory->method('isTransient')->willReturn(true);
        $entityManager->method('getMetadataFactory')->willReturn($metadataFactory);
        $entityManager->method('flush')->willReturnCallback(function (): void { ++$this->flushes; });
        static::getContainer()->set(EntityManagerInterface::class, $entityManager);
        $client->loginUser($user);

        return [$client, $notification];
    }
}
