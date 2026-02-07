<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Categorie;
use App\Repository\ProduitRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: Categorie::class, columnDefinition: "ENUM('sensoriels', 'bruit_et_environnement', 'education_apprentissage', 'communication_langage', 'jeux_therapeutiques_developpement', 'bien_etre_relaxation', 'vie_quotidienne')")]
    private ?Categorie $categorie = null;

    #[ORM\Column(type: 'float', precision: 10, scale: 2)]
    private ?float $prix = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $disponibilite = true;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image = null;

    private ?File $imageFile = null;

    #[ORM\ManyToOne(inversedBy: 'produits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\OneToOne(mappedBy: 'produit', targetEntity: Stock::class, cascade: ['persist', 'remove'])]
    private ?Stock $stock = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
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

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function isDisponibilite(): bool
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(bool $disponibilite): static
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getStock(): ?Stock
    {
        return $this->stock;
    }

    public function setStock(?Stock $stock): static
    {
        if ($stock !== null && $stock->getProduit() !== $this) {
            $stock->setProduit($this);
        }
        $this->stock = $stock;
        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): static
    {
        $this->imageFile = $imageFile;
        return $this;
    }
}
