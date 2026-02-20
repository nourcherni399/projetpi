<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IdeeEvenementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IdeeEvenementRepository::class)]
#[ORM\Table(name: 'idee_evenement')]
class IdeeEvenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $titre = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $theme = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $pourquoi = null;

    #[ORM\Column(name: 'mots_cle', length: 255, nullable: true)]
    private ?string $motsCle = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function getPourquoi(): ?string
    {
        return $this->pourquoi;
    }

    public function setPourquoi(?string $pourquoi): static
    {
        $this->pourquoi = $pourquoi;
        return $this;
    }

    public function getMotsCle(): ?string
    {
        return $this->motsCle;
    }

    public function setMotsCle(?string $motsCle): static
    {
        $this->motsCle = $motsCle;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
