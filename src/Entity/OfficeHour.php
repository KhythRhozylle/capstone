<?php

namespace App\Entity;

use App\Repository\OfficeHourRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** One opening window of a SAS unit on a weekday, in Philippine time. A unit may have several windows per day. */
#[ORM\Entity(repositoryClass: OfficeHourRepository::class)]
class OfficeHour
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SasUnit $unit;

    /** ISO weekday: 1 (Monday) to 7 (Sunday). */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 7)]
    private int $dayOfWeek = 1;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $opensAt = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $closesAt = null;

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

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getOpensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function setOpensAt(?\DateTimeImmutable $opensAt): static
    {
        $this->opensAt = $opensAt;

        return $this;
    }

    public function getClosesAt(): ?\DateTimeImmutable
    {
        return $this->closesAt;
    }

    public function setClosesAt(?\DateTimeImmutable $closesAt): static
    {
        $this->closesAt = $closesAt;

        return $this;
    }

    public function opensAtMinutes(): int
    {
        return $this->opensAt ? (int) $this->opensAt->format('G') * 60 + (int) $this->opensAt->format('i') : 0;
    }

    public function closesAtMinutes(): int
    {
        return $this->closesAt ? (int) $this->closesAt->format('G') * 60 + (int) $this->closesAt->format('i') : 0;
    }

    #[Assert\Callback]
    public function validateWindow(ExecutionContextInterface $context): void
    {
        if ($this->opensAt && $this->closesAt && $this->closesAtMinutes() <= $this->opensAtMinutes()) {
            $context->buildViolation('Closing time must be later than opening time.')->atPath('closesAt')->addViolation();
        }
    }
}
