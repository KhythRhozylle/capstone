<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Twig\Attribute\AsTwigFunction;

class NotificationExtension
{
    public function __construct(private readonly NotificationRepository $notifications)
    {
    }

    #[AsTwigFunction('notification_unread_count')]
    public function unreadCount(?User $user): int
    {
        return $user ? $this->notifications->unreadCount($user) : 0;
    }
}
