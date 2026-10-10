<?php

namespace App\Entity;

use App\Enum\AnnouncementScope;
use App\Repository\AnnouncementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $body = '';

    #[ORM\Column(enumType: AnnouncementScope::class)]
    private AnnouncementScope $scope = AnnouncementScope::University;

    #[ORM\ManyToOne]
    private ?Campus $campus = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $publishedBy;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    public function __construct()
    {
        $this->publishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getScope(): AnnouncementScope
    {
        return $this->scope;
    }

    public function setScope(AnnouncementScope $scope): static
    {
        $this->scope = $scope;

        return $this;
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

    public function getPublishedBy(): User
    {
        return $this->publishedBy;
    }

    public function setPublishedBy(User $publishedBy): static
    {
        $this->publishedBy = $publishedBy;

        return $this;
    }

    public function getPublishedAt(): \DateTimeImmutable
    {
        return $this->publishedAt;
    }
}
