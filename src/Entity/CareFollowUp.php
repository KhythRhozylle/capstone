<?php

namespace App\Entity;

use App\Repository\CareFollowUpRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CareFollowUpRepository::class)]
class CareFollowUp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'followUps')]
    #[ORM\JoinColumn(nullable: false)]
    private CareCase $careCase;

    #[ORM\Column]
    private \DateTimeImmutable $dueAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $notes = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCareCase(): CareCase
    {
        return $this->careCase;
    }

    public function setCareCase(CareCase $careCase): static
    {
        $this->careCase = $careCase;

        return $this;
    }

    public function getDueAt(): \DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function isOverdue(): bool
    {
        return !$this->completedAt instanceof \DateTimeImmutable && $this->dueAt < new \DateTimeImmutable();
    }
}
