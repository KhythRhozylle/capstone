<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'notifications_index', methods: ['GET'])]
    #[Route('/portal/notifications', name: 'portal_notifications', methods: ['GET'])]
    public function index(NotificationRepository $notifications, #[CurrentUser] User $user): Response
    {
        return $this->render('portal/notifications.html.twig', [
            'notifications' => $notifications->latestFor($user, 50),
        ]);
    }

    #[Route('/notifications/count', name: 'notifications_count', methods: ['GET'])]
    public function count(NotificationRepository $notifications, #[CurrentUser] User $user): JsonResponse
    {
        return $this->json(['unreadCount' => $notifications->unreadCount($user)], headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'notifications_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Route('/portal/notifications/{id}/read', name: 'portal_notification_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function read(int $id, Request $request, NotificationRepository $notifications, EntityManagerInterface $manager, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('notification-read-'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $notification = $notifications->find($id);
        if (!$notification || $notification->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        $notification->markRead();
        $manager->flush();

        return $this->redirectToRoute('notifications_index', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/notifications/read-all', name: 'notifications_read_all', methods: ['POST'])]
    #[Route('/portal/notifications/read-all', name: 'portal_notifications_read_all', methods: ['POST'])]
    public function readAll(Request $request, NotificationRepository $notifications, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('notifications-read-all', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $notifications->markAllRead($user);

        return $this->redirectToRoute('notifications_index', status: Response::HTTP_SEE_OTHER);
    }
    #[Route('/notifications/{id}/delete', name: 'notifications_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, NotificationRepository $notifications, EntityManagerInterface $manager, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('notification-delete-'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $notification = $notifications->find($id);
        if (!$notification || $notification->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        $manager->remove($notification);
        $manager->flush();

        return $this->redirectToRoute('notifications_index', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/notifications/clear-all', name: 'notifications_clear_all', methods: ['POST'])]
    public function clearAll(Request $request, NotificationRepository $notifications, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('notifications-clear-all', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $notifications->deleteAllFor($user);

        return $this->redirectToRoute('notifications_index', status: Response::HTTP_SEE_OTHER);
    }

}
