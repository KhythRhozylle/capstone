<?php

namespace App\Entity;

use App\Enum\FeedbackKind;
use App\Repository\FeedbackRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** A student's 1-5 rating of one finished appointment, service request or document request. */
#[ORM\Entity(repositoryClass: FeedbackRepository::class)]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $student;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private SasUnit $unit;

    #[ORM\Column(enumType: FeedbackKind::class)]
    private FeedbackKind $kind;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Appointment $appointment = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?DocumentRequest $documentRequest = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotBlank(message: 'Please choose a rating from 1 to 5.')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'Please choose a rating from 1 to 5.')]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $student, SasUnit $unit, FeedbackKind $kind)
    {
        $this->student = $student;
        $this->unit = $unit;
        $this->kind = $kind;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function forSubject(User $student, Appointment|ServiceRequest|DocumentRequest $subject): self
    {
        $feedback = match (true) {
            $subject instanceof Appointment => new self($student, $subject->getUnit(), FeedbackKind::Appointment),
            $subject instanceof ServiceRequest => new self($student, $subject->getUnit(), FeedbackKind::Service),
            $subject instanceof DocumentRequest => new self($student, $subject->getUnit(), FeedbackKind::Document),
        };
        match (true) {
            $subject instanceof Appointment => $feedback->appointment = $subject,
            $subject instanceof ServiceRequest => $feedback->serviceRequest = $subject,
            $subject instanceof DocumentRequest => $feedback->documentRequest = $subject,
        };

        return $feedback;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function getUnit(): SasUnit
    {
        return $this->unit;
    }

    public function getKind(): FeedbackKind
    {
        return $this->kind;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = null !== $comment && '' !== trim($comment) ? trim($comment) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
