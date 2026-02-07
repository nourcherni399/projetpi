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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieu = null;

    /** URL de localisation (lien Google Maps ou URL d'intégration iframe). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $locationUrl = null;

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

    public function setDateEvent(?\DateTimeInterface $dateEvent): static
    {
        $this->dateEvent = $dateEvent;
        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heureDebut;
    }

    public function setHeureDebut(?\DateTimeInterface $heureDebut): static
    {
        $this->heureDebut = $heureDebut;
        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heureFin;
    }

    public function setHeureFin(?\DateTimeInterface $heureFin): static
    {
        $this->heureFin = $heureFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getLocationUrl(): ?string
    {
        return $this->locationUrl;
    }

    public function setLocationUrl(?string $locationUrl): static
    {
        $this->locationUrl = $locationUrl;
        return $this;
    }

    /**
     * Retourne une URL d'intégration (iframe) pour afficher la même localisation sur la carte.
     * Utilise locationUrl si possible (lien précis), sinon le lieu (adresse).
     */
    public function getMapEmbedUrl(): ?string
    {
        $url = $this->locationUrl;
        if ($url !== null && $url !== '') {
            $urlLower = strtolower($url);
            if (str_contains($urlLower, 'embed') || str_contains($urlLower, 'iframe')) {
                return $url;
            }
            if (preg_match('/[?&]q=([^&]+)/', $url, $m)) {
                return 'https://maps.google.com/maps?q=' . rawurlencode(urldecode($m[1])) . '&output=embed';
            }
            if (preg_match('/[?&]query=([^&]+)/', $url, $m)) {
                return 'https://maps.google.com/maps?q=' . rawurlencode(urldecode($m[1])) . '&output=embed';
            }
            if (preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
                return 'https://maps.google.com/maps?q=' . $m[1] . ',' . $m[2] . '&output=embed';
            }
        }
        if ($this->lieu !== null && $this->lieu !== '') {
            return 'https://maps.google.com/maps?q=' . rawurlencode($this->lieu) . '&output=embed';
        }
        return null;
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
