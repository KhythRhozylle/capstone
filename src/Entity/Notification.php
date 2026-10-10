<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $recipient;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(User $recipient, string $title, string $body, ?string $link = null)
    {
        $this->recipient = $recipient;
        $this->title = $title;
        $this->body = $body;
        $this->link = $link;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return $this->readAt instanceof \DateTimeImmutable;
    }

    public function markRead(): static
    {
        $this->readAt = new \DateTimeImmutable();

        return $this;
    }
}
