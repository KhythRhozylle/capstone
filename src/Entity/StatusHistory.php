<?php

namespace App\Entity;

use App\Repository\StatusHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusHistoryRepository::class)]
class StatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $entityType;

    #[ORM\Column]
    private int $entityId;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $fromStatus;

    #[ORM\Column(length: 64)]
    private string $toStatus;

    #[ORM\ManyToOne]
    private ?User $actor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $entityType, int $entityId, ?string $fromStatus, string $toStatus, ?User $actor = null, ?string $note = null)
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->fromStatus = $fromStatus;
        $this->toStatus = $toStatus;
        $this->actor = $actor;
        $this->note = $note;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getFromStatus(): ?string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
