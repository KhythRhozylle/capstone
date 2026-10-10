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
        $this->entityManager->persist(new Notification($recipient, $title, $body, $link));

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

    /** @param iterable<User> $recipients */
    public function notifyMany(iterable $recipients, string $title, string $body, ?string $link = null): void
    {
        foreach ($recipients as $recipient) {
            $this->notify($recipient, $title, $body, $link);
        }
    }
}
