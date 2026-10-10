<?php

namespace App\Entity;

use App\Enum\DocumentType;
use App\Enum\RequestStatus;
use App\Repository\DocumentRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRequestRepository::class)]
class DocumentRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $referenceCode = '';

    #[ORM\Column(length: 64, unique: true)]
    private string $verificationToken = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $student;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private SasUnit $unit;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Campus $campus;

    #[ORM\Column(enumType: DocumentType::class)]
    private DocumentType $documentType = DocumentType::GoodMoral;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $purpose = '';

    #[ORM\Column(enumType: RequestStatus::class)]
    private RequestStatus $status = RequestStatus::Submitted;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releasedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->verificationToken = bin2hex(random_bytes(16));
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

    public function getVerificationToken(): string
    {
        return $this->verificationToken;
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

    public function getDocumentType(): DocumentType
    {
        return $this->documentType;
    }

    public function setDocumentType(DocumentType $documentType): static
    {
        $this->documentType = $documentType;

        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function setPurpose(string $purpose): static
    {
        $this->purpose = $purpose;

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

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function setReleasedAt(?\DateTimeImmutable $releasedAt): static
    {
        $this->releasedAt = $releasedAt;

        return $this;
    }
}
