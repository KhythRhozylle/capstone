<?php

namespace App\Controller;

use App\Dto\ChatInboxQuery;
use App\Entity\OfficeMessage;
use App\Entity\User;
use App\Form\OfficeMessageType;
use App\Repository\UserRepository;
use App\Service\ChatInbox;
use App\Service\ChatMessageSender;
use App\Service\ChatPhotoStorage;
use App\Service\ChatFileStorage;
use App\Service\OfficeProfileContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class OfficeChatController extends AbstractController
{
    #[Route('/messages/search', name: 'chat_search', methods: ['GET'])]
    public function search(ChatInbox $inbox, #[CurrentUser] User $user, #[MapQueryString] ChatInboxQuery $query = new ChatInboxQuery()): Response
    {
        return $this->render('chat/_contacts.html.twig', [
            'rows' => '' === trim($query->q) && 'people' !== $query->view ? $inbox->conversations($user, $query->view) : $inbox->search($user, $query->q), 'recipient' => null,
            'searching' => '' !== trim($query->q), 'view' => '' !== trim($query->q) ? 'people' : $query->view,
        ], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/messages', name: 'vpsas_chat', methods: ['GET'])]
    #[Route('/messages/{id}', name: 'vpsas_chat_thread', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function index(Request $request, UserRepository $users, OfficeProfileContext $profiles, ChatInbox $inbox, ChatMessageSender $sender, #[CurrentUser] User $user, #[MapQueryString] ChatInboxQuery $query = new ChatInboxQuery(), ?int $id = null): Response
    {
        $recipient = null === $id ? null : $users->find($id);
        if (null !== $id && null === $recipient) {
            throw $this->createNotFoundException('User not found.');
        }
        if ($recipient?->getId() === $user->getId()) {
            throw $this->createAccessDeniedException('Choose another user to message.');
        }
        $form = null;
        $messages = [];
        if ($recipient) {
            $message = new OfficeMessage($user, $recipient, $profiles->supports($user) ? $profiles->current($user)->label() : $user->getFullName());
            $form = $this->createForm(OfficeMessageType::class, $message);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $sender->send($message);

                    return $this->redirectToRoute('vpsas_chat_thread', ['id' => $recipient->getId()], 303);
                } catch (FileException) {
                    $form->addError(new FormError('The attachment could not be saved. Please select it again and retry.'));
                }
            }
            $messages = $inbox->openConversation($user, $recipient);
        }
        $searching = '' !== trim($query->q);
        $rows = $searching || 'people' === $query->view ? $inbox->search($user, $query->q) : $inbox->conversations($user, $query->view);

        return $this->render('vpsas/chat.html.twig', [
            'rows' => $rows, 'recipient' => $recipient, 'messages' => $messages,
            'form' => $form?->createView(), 'view' => $query->view, 'searching' => $searching,
            'query' => $query->q, 'archived' => $recipient && $inbox->isArchived($user, $recipient),
        ], new Response(status: $form?->isSubmitted() && !$form->isValid() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

    #[Route('/messages/photos/{id}', name: 'chat_photo', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function photo(int $id, EntityManagerInterface $manager, ChatPhotoStorage $photos, #[CurrentUser] User $user): BinaryFileResponse
    {
        $message = $manager->find(OfficeMessage::class, $id) ?? throw $this->createNotFoundException();
        if ($message->getSender()->getId() !== $user->getId() && $message->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        if (null === $message->getPhotoFilename() || !is_file($photos->path($message->getPhotoFilename()))) {
            throw $this->createNotFoundException();
        }
        $response = $this->file($photos->path($message->getPhotoFilename()), $message->getPhotoFilename(), ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/messages/files/{id}', name: 'chat_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(int $id, EntityManagerInterface $manager, ChatFileStorage $files, #[CurrentUser] User $user): BinaryFileResponse
    {
        $message = $manager->find(OfficeMessage::class, $id) ?? throw $this->createNotFoundException();
        if ($message->getSender()->getId() !== $user->getId() && $message->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        if (null === $message->getAttachmentFilename() || !is_file($files->path($message->getAttachmentFilename()))) {
            throw $this->createNotFoundException();
        }
        $response = $this->file($files->path($message->getAttachmentFilename()), $message->getAttachmentName() ?? 'file', ResponseHeaderBag::DISPOSITION_ATTACHMENT);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    #[Route('/messages/{id}/archive', name: 'chat_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(int $id, Request $request, UserRepository $users, ChatInbox $inbox, #[CurrentUser] User $user): Response
    {
        if (!$this->isCsrfTokenValid('chat-archive-'.$id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $contact = $users->find($id) ?? throw $this->createNotFoundException();
        if ($contact->getId() === $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        $inbox->toggleArchive($user, $contact);

        return $this->redirectToRoute('vpsas_chat_thread', ['id' => $id], 303);
    }
}
