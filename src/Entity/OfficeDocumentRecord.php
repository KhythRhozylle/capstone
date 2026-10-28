<?php

namespace App\Entity;

use App\Enum\DocumentRecordStatus;
use App\Repository\OfficeDocumentRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: OfficeDocumentRecordRepository::class)]
class OfficeDocumentRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private SasUnit $unit;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Campus $campus = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    private string $particular = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    #[Assert\LessThan('10000000000')]
    private string $amount = '0.00';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $receivedBy = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'receivedAt', message: 'The release date cannot be before the date received.')]
    private ?\DateTimeImmutable $releasedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $releasedBy = null;

    #[ORM\Column(enumType: DocumentRecordStatus::class)]
    #[Assert\NotNull]
    private ?DocumentRecordStatus $status = DocumentRecordStatus::Pending;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $extraData = [];

    public function getExtraData(): array
    {
        return $this->extraData ?? [];
    }

    public function setExtraData(array $extraData): void
    {
        $this->extraData = $extraData;
    }

    public function __construct(SasUnit $unit)
    {
        $this->unit = $unit;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnit(): SasUnit
    {
        return $this->unit;
    }

    public function getCampus(): ?Campus
    {
        return $this->campus;
    }

    public function setCampus(?Campus $campus): void
    {
        $this->campus = $campus;
    }

    public function getParticular(): string
    {
        return $this->particular;
    }

    public function setParticular(string $particular): void
    {
        $this->particular = $particular;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): void
    {
        $this->amount = $amount;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): void
    {
        $this->receivedAt = $receivedAt;
    }

    public function getReceivedBy(): string
    {
        return $this->receivedBy;
    }

    public function setReceivedBy(string $receivedBy): void
    {
        $this->receivedBy = $receivedBy;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public function setReleasedAt(?\DateTimeImmutable $releasedAt): void
    {
        $this->releasedAt = $releasedAt;
    }

    public function getReleasedBy(): ?string
    {
        return $this->releasedBy;
    }

    public function setReleasedBy(?string $releasedBy): void
    {
        $this->releasedBy = $releasedBy;
    }

    public function getStatus(): ?DocumentRecordStatus
    {
        return $this->status;
    }

    public function setStatus(?DocumentRecordStatus $status): void
    {
        $this->status = $status;
    }


    #[Assert\Callback]
    public function validateRelease(ExecutionContextInterface $context): void
    {
        if (DocumentRecordStatus::Released !== $this->status) {
            return;
        }
        if (null === $this->releasedAt) {
            $context->buildViolation('Enter the date released.')->atPath('releasedAt')->addViolation();
        }
        if ('' === trim($this->releasedBy ?? '')) {
            $context->buildViolation('Enter who released the document.')->atPath('releasedBy')->addViolation();
        }
    }
}
