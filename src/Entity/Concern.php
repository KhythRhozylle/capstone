<?php

namespace App\Entity;

use App\Enum\ConcernType;
use App\Enum\RequestStatus;
use App\Repository\ConcernRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConcernRepository::class)]
class Concern
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

    #[ORM\Column(enumType: ConcernType::class)]
    private ConcernType $type = ConcernType::Concern;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $subject = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $respondents = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private SasUnit $assignedUnit;

    #[ORM\ManyToOne]
    private ?SasUnit $referredToUnit = null;

    #[ORM\Column(enumType: RequestStatus::class)]
    private RequestStatus $status = RequestStatus::Submitted;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolutionNotes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getType(): ConcernType
    {
        return $this->type;
    }

    public function setType(ConcernType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getRespondents(): ?string
    {
        return $this->respondents;
    }

    public function setRespondents(?string $respondents): static
    {
        $this->respondents = $respondents;

        return $this;
    }

    public function getAssignedUnit(): SasUnit
    {
        return $this->assignedUnit;
    }

    public function setAssignedUnit(SasUnit $assignedUnit): static
    {
        $this->assignedUnit = $assignedUnit;

        return $this;
    }

    public function getReferredToUnit(): ?SasUnit
    {
        return $this->referredToUnit;
    }

    public function setReferredToUnit(?SasUnit $referredToUnit): static
    {
        $this->referredToUnit = $referredToUnit;

        return $this;
    }

    public function getStatus(): RequestStatus
    {
        return $this->status;
    }

    public function setStatus(RequestStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function setResolutionNotes(?string $resolutionNotes): static
    {
        $this->resolutionNotes = $resolutionNotes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

        return $this;
    }
}
