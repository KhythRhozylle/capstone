<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class AppNotifier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function notify(User $recipient, string $title, string $body, ?string $link = null): void
    {
        $this->persistNotification($recipient, $title, $body, $link);
        $this->entityManager->flush();
        $this->sendMail($recipient, $title, $body, $link);
    }

    /** @param iterable<User> $recipients */
    public function notifyMany(iterable $recipients, string $title, string $body, ?string $link = null): void
    {
        foreach ($recipients as $recipient) {
            $this->persistNotification($recipient, $title, $body, $link);
        }
        $this->entityManager->flush();

        foreach ($recipients as $recipient) {
            $this->sendMail($recipient, $title, $body, $link);
        }
    }

    private function persistNotification(User $recipient, string $title, string $body, ?string $link): void
    {
        $this->entityManager->persist(new Notification($recipient, $title, $body, $link));
    }

    private function sendMail(User $recipient, string $title, string $body, ?string $link): void
    {
        try {
            $email = (new Email())
                ->from('sas@norsu.edu.ph')
                ->to($recipient->getEmail())
                ->subject('[NORSU SAS] '.$title)
                ->text($body.($link ? "\n\n".$link : ''));
            $this->mailer->send($email);
        } catch (\Throwable) {
            // In-app notification is the source of truth; mail is best-effort.
        }
    }
}
