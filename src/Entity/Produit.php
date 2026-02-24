<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Categorie;
use App\Enum\StatutPublication;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\ProduitRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

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

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(type: 'string', length: 20, enumType: StatutPublication::class, options: ['default' => 'brouillon'])]
    private StatutPublication $statutPublication = StatutPublication::BROUILLON;

    #[ORM\Column(type: 'float', precision: 3, scale: 2, options: ['default' => 0])]
    private float $noteMoyenne = 0.0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $nbAvis = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $seuilAlerte = null;

    /** @var Collection<int, ProduitImage> */
    #[ORM\OneToMany(targetEntity: ProduitImage::class, mappedBy: 'produit', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, AvisProduit> */
    #[ORM\OneToMany(targetEntity: AvisProduit::class, mappedBy: 'produit', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $avisProduits;

    #[ORM\ManyToOne(inversedBy: 'produits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'produits', targetEntity: Stock::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le stock est obligatoire.')]
    private ?Stock $stock = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Assert\Range(min: 1, max: 999999, notInRangeMessage: 'La quantité doit être au moins 1.')]
    private int $quantite = 1;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $genereParIa = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $valide = true;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->avisProduits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
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

    public function setPrix(?float $prix): static
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
        $this->stock = $stock;
        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
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

    public function isGenereParIa(): bool
    {
        return $this->genereParIa;
    }

    public function setGenereParIa(bool $genereParIa): static
    {
        $this->genereParIa = $genereParIa;
        return $this;
    }

    public function isValide(): bool
    {
        return $this->valide;
    }

    public function setValide(bool $valide): static
    {
        $this->valide = $valide;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;
        return $this;
    }

    public function getStatutPublication(): StatutPublication
    {
        return $this->statutPublication;
    }

    public function setStatutPublication(StatutPublication $statutPublication): static
    {
        $this->statutPublication = $statutPublication;
        return $this;
    }

    public function getNoteMoyenne(): float
    {
        return $this->noteMoyenne;
    }

    public function setNoteMoyenne(float $noteMoyenne): static
    {
        $this->noteMoyenne = $noteMoyenne;
        return $this;
    }

    public function getNbAvis(): int
    {
        return $this->nbAvis;
    }

    public function setNbAvis(int $nbAvis): static
    {
        $this->nbAvis = $nbAvis;
        return $this;
    }

    public function getSeuilAlerte(): ?int
    {
        return $this->seuilAlerte;
    }

    public function setSeuilAlerte(?int $seuilAlerte): static
    {
        $this->seuilAlerte = $seuilAlerte;
        return $this;
    }

    /** @return Collection<int, ProduitImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProduitImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduit($this);
        }
        return $this;
    }

    public function removeImage(ProduitImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getProduit() === $this) {
                $image->setProduit(null);
            }
        }
        return $this;
    }

    /** @return Collection<int, AvisProduit> */
    public function getAvisProduits(): Collection
    {
        return $this->avisProduits;
    }

    public function addAvisProduit(AvisProduit $avisProduit): static
    {
        if (!$this->avisProduits->contains($avisProduit)) {
            $this->avisProduits->add($avisProduit);
            $avisProduit->setProduit($this);
        }
        return $this;
    }

    public function removeAvisProduit(AvisProduit $avisProduit): static
    {
        if ($this->avisProduits->removeElement($avisProduit)) {
            if ($avisProduit->getProduit() === $this) {
                $avisProduit->setProduit(null);
            }
        }
        return $this;
    }
}