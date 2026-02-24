<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Categorie;
use App\Repository\DemandeProduitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeProduitRepository::class)]
class DemandeProduit
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_APPROUVE = 'approuve';
    public const STATUT_REJETE = 'rejete';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Texte brut de la demande du client (chatbot) */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $demandeClient = null;

    /** Nom optimisé généré par l'IA */
    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    /** Description détaillée générée par l'IA */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Catégorie suggérée par l'IA */
    #[ORM\Column(type: 'string', enumType: Categorie::class)]
    private ?Categorie $categorie = null;

    /** Prix estimé par l'IA (DT) */
    #[ORM\Column(type: Types::FLOAT, precision: 10, scale: 2)]
    private ?float $prixEstime = null;

    /** Budget indiqué par le client (optionnel) */
    #[ORM\Column(type: Types::FLOAT, precision: 10, scale: 2, nullable: true)]
    private ?float $budgetClient = null;

    /** Caractéristiques extraites (JSON ou texte) */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $caracteristiques = null;

    /** Données enrichies depuis une API externe (ex. Amazon) */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $donneesExternes = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $demandeur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    /** Produit créé après approbation */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Produit $produit = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemandeClient(): ?string
    {
        return $this->demandeClient;
    }

    public function setDemandeClient(string $demandeClient): static
    {
        $this->demandeClient = $demandeClient;
        return $this;
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

    public function getPrixEstime(): ?float
    {
        return $this->prixEstime;
    }

    public function setPrixEstime(float $prixEstime): static
    {
        $this->prixEstime = $prixEstime;
        return $this;
    }

    public function getBudgetClient(): ?float
    {
        return $this->budgetClient;
    }

    public function setBudgetClient(?float $budgetClient): static
    {
        $this->budgetClient = $budgetClient;
        return $this;
    }

    public function getCaracteristiques(): ?string
    {
        return $this->caracteristiques;
    }

    public function setCaracteristiques(?string $caracteristiques): static
    {
        $this->caracteristiques = $caracteristiques;
        return $this;
    }

    public function getDonneesExternes(): ?array
    {
        return $this->donneesExternes;
    }

    public function setDonneesExternes(?array $donneesExternes): static
    {
        $this->donneesExternes = $donneesExternes;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDemandeur(): ?User
    {
        return $this->demandeur;
    }

    public function setDemandeur(?User $demandeur): static
    {
        $this->demandeur = $demandeur;
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

    public function getValidatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;
        return $this;
    }
}