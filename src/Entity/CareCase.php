<?php

namespace App\Entity;

use App\Enum\CareCaseStatus;
use App\Repository\CareCaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CareCaseRepository::class)]
class CareCase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $referenceCode = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $student;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Campus $campus;

    #[ORM\ManyToOne]
    private ?User $counselor = null;

    #[ORM\ManyToOne]
    private ?Concern $sourceConcern = null;

    #[ORM\Column(enumType: CareCaseStatus::class)]
    private CareCaseStatus $status = CareCaseStatus::Requested;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $reason = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $studentVisibleSummary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $clinicalNotes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, CareFollowUp> */
    #[ORM\OneToMany(targetEntity: CareFollowUp::class, mappedBy: 'careCase', cascade: ['persist'], orphanRemoval: true)]
    private Collection $followUps;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->followUps = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferenceCode(): string
    {
        return $this->referenceCode;
    }

    public function setReferenceCode(string $referenceCode): static
    {
        $this->referenceCode = $referenceCode;

        return $this;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function setStudent(User $student): static
    {
        $this->student = $student;

        return $this;
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

    public function getCounselor(): ?User
    {
        return $this->counselor;
    }

    public function setCounselor(?User $counselor): static
    {
        $this->counselor = $counselor;

        return $this;
    }

    public function getSourceConcern(): ?Concern
    {
        return $this->sourceConcern;
    }

    public function setSourceConcern(?Concern $sourceConcern): static
    {
        $this->sourceConcern = $sourceConcern;

        return $this;
    }

    public function getStatus(): CareCaseStatus
    {
        return $this->status;
    }

    public function setStatus(CareCaseStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getStudentVisibleSummary(): ?string
    {
        return $this->studentVisibleSummary;
    }

    public function setStudentVisibleSummary(?string $studentVisibleSummary): static
    {
        $this->studentVisibleSummary = $studentVisibleSummary;

        return $this;
    }

    public function getClinicalNotes(): ?string
    {
        return $this->clinicalNotes;
    }

    public function setClinicalNotes(?string $clinicalNotes): static
    {
        $this->clinicalNotes = $clinicalNotes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, CareFollowUp> */
    public function getFollowUps(): Collection
    {
        return $this->followUps;
    }

    public function addFollowUp(CareFollowUp $followUp): static
    {
        if (!$this->followUps->contains($followUp)) {
            $this->followUps->add($followUp);
            $followUp->setCareCase($this);
        }

        return $this;
    }
}
