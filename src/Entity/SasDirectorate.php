<?php

namespace App\Entity;

use App\Repository\SasDirectorateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SasDirectorateRepository::class)]
class SasDirectorate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, unique: true)]
    private string $code;

    #[ORM\Column(length: 180)]
    private string $name;

    /** @var Collection<int, SasUnit> */
    #[ORM\OneToMany(targetEntity: SasUnit::class, mappedBy: 'directorate')]
    private Collection $units;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
        $this->units = new ArrayCollection();
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

    /** @return Collection<int, SasUnit> */
    public function getUnits(): Collection
    {
        return $this->units;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
