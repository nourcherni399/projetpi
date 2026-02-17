<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\NiveauDifficulte;
use App\Entity\Enum\PublicCible;
use App\Repository\ThematiqueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ThematiqueRepository::class)]
#[ORM\Table(name: 'thematique')]
class Thematique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $nomThematique = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Length(max: 50, maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $codeThematique = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Length(max: 65535, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Length(max: 20)]
    private ?string $couleur = null;

    /** URL ou chemin de l'image représentant la thématique */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Length(max: 500)]
    private ?string $image = null;

    #[ORM\Column(name: 'sous_titre', length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Ce champ est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $sousTitre = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\NotNull(message: 'Ce champ est obligatoire.')]
    #[Assert\Range(min: 0, max: 32767, notInRangeMessage: 'L\'ordre doit être entre {{ min }} et {{ max }}.')]
    private ?int $ordre = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(type: 'string', enumType: PublicCible::class, columnDefinition: "ENUM('Enfant', 'Parent', 'Médecin', 'Éducateur', 'Aidant', 'Autre')", nullable: true)]
    private ?PublicCible $publicCible = null;

    #[ORM\Column(type: 'string', enumType: NiveauDifficulte::class, columnDefinition: "ENUM('Débutant', 'Intermédiaire', 'Avancé')", nullable: true)]
    private ?NiveauDifficulte $niveauDifficulte = null;

    /** @var Collection<int, Evenement> */
    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'thematique')]
    private Collection $evenements;

    public function __construct()
    {
        $this->evenements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomThematique(): ?string
    {
        return $this->nomThematique;
    }

    public function setNomThematique(?string $nomThematique): static
    {
        $this->nomThematique = $nomThematique;
        return $this;
    }

    public function getCodeThematique(): ?string
    {
        return $this->codeThematique;
    }

    public function setCodeThematique(?string $codeThematique): static
    {
        $this->codeThematique = $codeThematique;
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

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = $couleur;
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

    public function getSousTitre(): ?string
    {
        return $this->sousTitre;
    }

    public function setSousTitre(?string $sousTitre): static
    {
        $this->sousTitre = $sousTitre;
        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(?int $ordre): static
    {
        $this->ordre = $ordre;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getPublicCible(): ?PublicCible
    {
        return $this->publicCible;
    }

    public function setPublicCible(?PublicCible $publicCible): static
    {
        $this->publicCible = $publicCible;
        return $this;
    }

    public function getNiveauDifficulte(): ?NiveauDifficulte
    {
        return $this->niveauDifficulte;
    }

    public function setNiveauDifficulte(?NiveauDifficulte $niveauDifficulte): static
    {
        $this->niveauDifficulte = $niveauDifficulte;
        return $this;
    }

    /** @return Collection<int, Evenement> */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): static
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements->add($evenement);
            $evenement->setThematique($this);
        }
        return $this;
    }

    public function removeEvenement(Evenement $evenement): static
    {
        if ($this->evenements->removeElement($evenement)) {
            if ($evenement->getThematique() === $this) {
                $evenement->setThematique(null);
            }
        }
        return $this;
    }
}