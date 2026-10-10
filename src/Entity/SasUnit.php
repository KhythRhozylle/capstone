<?php

namespace App\Entity;

use App\Repository\SasUnitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SasUnitRepository::class)]
class SasUnit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $code;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\ManyToOne(inversedBy: 'units')]
    #[ORM\JoinColumn(nullable: false)]
    private SasDirectorate $directorate;

    #[ORM\Column]
    private bool $handlesAppointments = true;

    #[ORM\Column]
    private bool $handlesCare = false;

    #[ORM\Column]
    private bool $handlesGrievance = false;

    #[ORM\Column]
    private bool $handlesDocuments = true;

    public function __construct(string $code, string $name, SasDirectorate $directorate)
    {
        $this->code = $code;
        $this->name = $name;
        $this->directorate = $directorate;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDirectorate(): SasDirectorate
    {
        return $this->directorate;
    }

    public function handlesAppointments(): bool
    {
        return $this->handlesAppointments;
    }

    public function setHandlesAppointments(bool $handlesAppointments): static
    {
        $this->handlesAppointments = $handlesAppointments;

        return $this;
    }

    public function handlesCare(): bool
    {
        return $this->handlesCare;
    }

    public function setHandlesCare(bool $handlesCare): static
    {
        $this->handlesCare = $handlesCare;

        return $this;
    }

    public function handlesGrievance(): bool
    {
        return $this->handlesGrievance;
    }

    public function setHandlesGrievance(bool $handlesGrievance): static
    {
        $this->handlesGrievance = $handlesGrievance;

        return $this;
    }

    public function handlesDocuments(): bool
    {
        return $this->handlesDocuments;
    }

    public function setHandlesDocuments(bool $handlesDocuments): static
    {
        $this->handlesDocuments = $handlesDocuments;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
