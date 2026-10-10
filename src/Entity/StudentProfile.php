<?php

namespace App\Entity;

use App\Repository\StudentProfileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentProfileRepository::class)]
class StudentProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'studentProfile')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 32, unique: true)]
    #[Assert\NotBlank]
    private string $studentNumber = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $program = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $college = '';

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    private string $yearLevel = '';

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    private string $contactNumber = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStudentNumber(): string
    {
        return $this->studentNumber;
    }

    public function setStudentNumber(string $studentNumber): static
    {
        $this->studentNumber = $studentNumber;

        return $this;
    }

    public function getProgram(): string
    {
        return $this->program;
    }

    public function setProgram(string $program): static
    {
        $this->program = $program;

        return $this;
    }

    public function getCollege(): string
    {
        return $this->college;
    }

    public function setCollege(string $college): static
    {
        $this->college = $college;

        return $this;
    }

    public function getYearLevel(): string
    {
        return $this->yearLevel;
    }

    public function setYearLevel(string $yearLevel): static
    {
        $this->yearLevel = $yearLevel;

        return $this;
    }

    public function getContactNumber(): string
    {
        return $this->contactNumber;
    }

    public function setContactNumber(string $contactNumber): static
    {
        $this->contactNumber = $contactNumber;

        return $this;
    }
}
