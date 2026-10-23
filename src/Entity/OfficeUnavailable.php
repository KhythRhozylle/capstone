<?php

namespace App\Entity;

use App\Repository\OfficeUnavailableRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: OfficeUnavailableRepository::class)]
#[ORM\UniqueConstraint(fields: ['unit', 'day', 'startMinute', 'endMinute'])]
#[UniqueEntity(fields: ['unit', 'day', 'startMinute', 'endMinute'], message: 'This period is already unavailable.')]
class OfficeUnavailable
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
    private User $owner;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    #[ORM\Column(options: ['default' => 0])]
    private int $startMinute = 0;

    #[ORM\Column(options: ['default' => 1440])]
    private int $endMinute = 1440;

    public function getStartMinute(): int
    {
        return $this->startMinute;
    }

    public function setStartMinute(int $minutes): void
    {
        $this->startMinute = $minutes;
    }

    public function getEndMinute(): int
    {
        return $this->endMinute;
    }

    public function setEndMinute(int $minutes): void
    {
        $this->endMinute = $minutes;
    }

    public function isAllDay(): bool
    {
        return 0 === $this->startMinute && 1440 === $this->endMinute;
    }

    public function getPeriodLabel(): string
    {
        if ($this->isAllDay()) {
            return 'All day';
        }
        $format = static fn (int $minutes): string => 1440 === $minutes ? '12:00 AM (next day)' : (new \DateTimeImmutable('today'))->setTime(intdiv($minutes, 60), $minutes % 60)->format('g:i A');
        return $format($this->startMinute).' – '.$format($this->endMinute);
    }

    public function __construct(SasUnit $unit, User $owner)
    {
        $this->unit = $unit;
        $this->owner = $owner;
        $this->day = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUnit(): SasUnit
    {
        return $this->unit;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getDay(): \DateTimeImmutable
    {
        return $this->day;
    }

    public function setDay(\DateTimeImmutable $day): void
    {
        $this->day = $day;
    }
}
