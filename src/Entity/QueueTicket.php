<?php

namespace App\Entity;

use App\Enum\QueueStatus;
use App\Repository\QueueTicketRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QueueTicketRepository::class)]
class QueueTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Campus $campus;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private SasUnit $unit;

    #[ORM\ManyToOne]
    private ?User $student = null;

    #[ORM\Column(length: 180)]
    private string $displayName;

    #[ORM\Column]
    private int $number;

    #[ORM\Column(enumType: QueueStatus::class)]
    private QueueStatus $status = QueueStatus::Waiting;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $calledAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampus(): Campus
    {
        return $this->campus;
    }

    public function setCampus(Campus $campus): static
    {
        $this->campus = $campus;

        return $this;
    }

    public function getUnit(): SasUnit
    {
        return $this->unit;
    }

    public function setUnit(SasUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): QueueStatus
    {
        return $this->status;
    }

    public function setStatus(QueueStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCalledAt(): ?\DateTimeImmutable
    {
        return $this->calledAt;
    }

    public function setCalledAt(?\DateTimeImmutable $calledAt): static
    {
        $this->calledAt = $calledAt;

        return $this;
    }
}
