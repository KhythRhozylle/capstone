<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\OfficeMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ChatMessageSender
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly ChatPhotoStorage $photos,
        private readonly ChatFileStorage $files,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function send(OfficeMessage $message): void
    {
        $filename = null;
        $attachment = null;
        try {
            if ($message->getPhotoUpload()) {
                $filename = $this->photos->store($message->getPhotoUpload());
                $message->setPhotoFilename($filename);
            }
            if ($file = $message->getAttachmentUpload()) {
                $name = $this->files->originalName($file);
                $size = $file->getSize();
                $attachment = $this->files->store($file);
                $message->setAttachment($attachment, $name, $size);
            }
            $this->manager->persist($message);
            $this->manager->persist(new Notification($message->getRecipient(), 'New message from '.$message->getSenderName(), 'Open chat to read and reply.', $this->urls->generate('vpsas_chat_thread', ['id' => $message->getSender()->getId()])));
            $this->manager->flush();
        } catch (\Throwable $error) {
            if (null !== $filename) {
                $this->photos->remove($filename);
                $message->setPhotoFilename(null);
            }
            if (null !== $attachment) {
                $this->files->remove($attachment);
                $message->setAttachment(null);
            }
            throw $error;
        }
    }
}
