<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    /** @var array<string, string> */
    #[ORM\Column]
    private array $profilePhotos = [];

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 120)]
    private string $firstName = '';

    #[ORM\Column(length: 120)]
    private string $lastName = '';

    #[ORM\ManyToOne]
    private ?Campus $campus = null;

    #[ORM\ManyToOne]
    private ?SasUnit $staffUnit = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: StudentProfile::class, cascade: ['persist', 'remove'])]
    private ?StudentProfile $studentProfile = null;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'recipient')]
    private Collection $notifications;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getProfilePhoto(string $profile): ?string
    {
        return $this->profilePhotos[$profile] ?? null;
    }

    public function setProfilePhoto(string $profile, ?string $filename): void
    {
        if (null === $filename) {
            unset($this->profilePhotos[$profile]);
        } else {
            $this->profilePhotos[$profile] = $filename;
        }
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getCampus(): ?Campus
    {
        return $this->campus;
    }

    public function setCampus(?Campus $campus): static
    {
        $this->campus = $campus;

        return $this;
    }

    public function getStaffUnit(): ?SasUnit
    {
        return $this->staffUnit;
    }

    public function setStaffUnit(?SasUnit $staffUnit): static
    {
        $this->staffUnit = $staffUnit;

        return $this;
    }

    public function getStudentProfile(): ?StudentProfile
    {
        return $this->studentProfile;
    }

    public function setStudentProfile(?StudentProfile $studentProfile): static
    {
        $this->studentProfile = $studentProfile;
        if ($studentProfile instanceof StudentProfile && $studentProfile->getUser() !== $this) {
            $studentProfile->setUser($this);
        }

        return $this;
    }

    public function isStudent(): bool
    {
        return \in_array('ROLE_STUDENT', $this->getRoles(), true);
    }

    public function isStaff(): bool
    {
        return \in_array('ROLE_STAFF', $this->getRoles(), true)
            || \in_array('ROLE_GUIDANCE', $this->getRoles(), true)
            || \in_array('ROLE_DIRECTOR', $this->getRoles(), true)
            || \in_array('ROLE_VPSAS', $this->getRoles(), true)
            || \in_array('ROLE_VPSAS_SECRETARIAT', $this->getRoles(), true);
    }

    public function __toString(): string
    {
        return $this->getFullName() !== '' ? $this->getFullName() : $this->email;
    }
}
