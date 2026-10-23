<?php

namespace App\Entity;

use App\Enum\AppointmentMode;
use App\Enum\AppointmentStatus;
use App\Repository\AppointmentRepository;
use App\Validator\AvailableAppointmentSlot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[AvailableAppointmentSlot(groups: ['scheduling'])]
class Appointment
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

    #[ORM\Column(enumType: AppointmentMode::class)]
    private AppointmentMode $mode = AppointmentMode::Physical;

    #[ORM\Column(enumType: AppointmentStatus::class)]
    private AppointmentStatus $status = AppointmentStatus::Pending;

    #[ORM\Column]
    #[Assert\NotBlank]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $meetingUrl = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $purpose = '';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $topic = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9+()\-\s]{7,32}$/', message: 'Enter a valid contact number.')]
    private ?string $contactNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $accommodationNote = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $staffNotes = null;

    #[ORM\ManyToOne]
    private ?Appointment $rescheduledFrom = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->scheduledAt = new \DateTimeImmutable('+1 day');
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

    public function getMode(): AppointmentMode
    {
        return $this->mode;
    }

    public function setMode(AppointmentMode $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getStatus(): AppointmentStatus
    {
        return $this->status;
    }

    public function setStatus(AppointmentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getMeetingUrl(): ?string
    {
        return $this->meetingUrl;
    }

    public function setMeetingUrl(?string $meetingUrl): static
    {
        $this->meetingUrl = $meetingUrl;

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

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(?string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    public function getContactNumber(): ?string
    {
        return $this->contactNumber;
    }

    public function setContactNumber(?string $contactNumber): static
    {
        $this->contactNumber = $contactNumber;

        return $this;
    }

    public function getAccommodationNote(): ?string
    {
        return $this->accommodationNote;
    }

    public function setAccommodationNote(?string $accommodationNote): static
    {
        $this->accommodationNote = $accommodationNote;

        return $this;
    }

    public function getStaffNotes(): ?string
    {
        return $this->staffNotes;
    }

    public function setStaffNotes(?string $staffNotes): static
    {
        $this->staffNotes = $staffNotes;

        return $this;
    }

    public function getRescheduledFrom(): ?self
    {
        return $this->rescheduledFrom;
    }

    public function setRescheduledFrom(?self $rescheduledFrom): static
    {
        $this->rescheduledFrom = $rescheduledFrom;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function canManageMeetingUrl(): bool
    {
        return AppointmentMode::Virtual === $this->mode && AppointmentStatus::Approved === $this->status;
    }

    public function canStudentChange(): bool
    {
        return \in_array($this->status, [
            AppointmentStatus::Pending,
            AppointmentStatus::Approved,
            AppointmentStatus::Rescheduled,
        ], true);
    }
}
