<?php

namespace App\Entity;

use App\Repository\OfficeDocumentColumnRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OfficeDocumentColumnRepository::class)]
#[ORM\UniqueConstraint(name: 'office_document_column_label', fields: ['unit', 'label'])]
#[UniqueEntity(fields: ['unit', 'label'], message: 'This column already exists in your office.')]
class OfficeDocumentColumn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private SasUnit $unit;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private string $label = '';

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}
