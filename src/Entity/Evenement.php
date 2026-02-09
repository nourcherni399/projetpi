<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateEvent = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heureFin = null;

<<<<<<< HEAD
    #[ORM\Column(length: 255)]
=======
    #[ORM\Column(length: 255, nullable: true)]
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    private ?string $lieu = null;

    /** Agrégation : un événement appartient à une thématique (sans cascade delete). */
    #[ORM\ManyToOne(inversedBy: 'evenements')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Thematique $thematique = null;

    /** @var Collection<int, InscritEvents> */
    #[ORM\OneToMany(targetEntity: InscritEvents::class, mappedBy: 'evenement')]
    private Collection $inscrits;

    public function __construct()
    {
        $this->inscrits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getDateEvent(): ?\DateTimeInterface
    {
        return $this->dateEvent;
    }

<<<<<<< HEAD
    public function setDateEvent(\DateTimeInterface $dateEvent): static
=======
    public function setDateEvent(?\DateTimeInterface $dateEvent): static
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    {
        $this->dateEvent = $dateEvent;
        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heureDebut;
    }

<<<<<<< HEAD
    public function setHeureDebut(\DateTimeInterface $heureDebut): static
=======
    public function setHeureDebut(?\DateTimeInterface $heureDebut): static
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    {
        $this->heureDebut = $heureDebut;
        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heureFin;
    }

<<<<<<< HEAD
    public function setHeureFin(\DateTimeInterface $heureFin): static
=======
    public function setHeureFin(?\DateTimeInterface $heureFin): static
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    {
        $this->heureFin = $heureFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

<<<<<<< HEAD
    public function setLieu(string $lieu): static
=======
    public function setLieu(?string $lieu): static
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getThematique(): ?Thematique
    {
        return $this->thematique;
    }

    public function setThematique(?Thematique $thematique): static
    {
        $this->thematique = $thematique;
        return $this;
    }

    /** @return Collection<int, InscritEvents> */
    public function getInscrits(): Collection
    {
        return $this->inscrits;
    }

    public function addInscrit(InscritEvents $inscrit): static
    {
        if (!$this->inscrits->contains($inscrit)) {
            $this->inscrits->add($inscrit);
            $inscrit->setEvenement($this);
        }
        return $this;
    }

    public function removeInscrit(InscritEvents $inscrit): static
    {
        if ($this->inscrits->removeElement($inscrit)) {
            if ($inscrit->getEvenement() === $this) {
                $inscrit->setEvenement(null);
            }
        }
        return $this;
    }
}
