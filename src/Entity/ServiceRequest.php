<?php

namespace App\Entity;

use App\Enum\RequestStatus;
use App\Repository\ServiceRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
class ServiceRequest
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
    private SasUnit $unit;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Campus $campus;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $subject = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $description = '';

    #[ORM\Column(enumType: RequestStatus::class)]
    private RequestStatus $status = RequestStatus::Submitted;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

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

    public function getUnit(): SasUnit
    {
        return $this->unit;
    }

    public function setUnit(SasUnit $unit): static
    {
        $this->unit = $unit;

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

    public function getStatus(): RequestStatus
    {
        return $this->status;
    }

    public function setStatus(RequestStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
