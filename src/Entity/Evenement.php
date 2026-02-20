<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre de l\'événement est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 65535, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de l\'événement est obligatoire.')]
    private ?\DateTimeInterface $dateEvent = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'L\'heure de début est obligatoire.')]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'L\'heure de fin est obligatoire.')]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $lieu = null;

    /** URL de localisation (lien Google Maps ou iframe). */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $locationUrl = null;

    /** Latitude pour affichage sur carte (Google Maps). */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;

    /** Longitude pour affichage sur carte (Google Maps). */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;

    /** Mode de l'événement : presentiel | en_ligne | hybride */
    #[ORM\Column(length: 20, nullable: false, options: ['default' => 'presentiel'])]
    #[Assert\Choice(choices: ['presentiel', 'en_ligne', 'hybride'], message: 'Le mode doit être présentiel, en ligne ou hybride.')]
    private string $mode = 'presentiel';

    /** Lien de la réunion Zoom (généré via API ou saisi à la main). */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $meetingUrl = null;

    /** Image générée (affiche) : chemin relatif ex. uploads/evenements/xxx.png. */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $image = null;

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

    public function setTitle(?string $title): static
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

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): static
    {
        $this->mode = $mode;
        return $this;
    }

    public function getMeetingUrl(): ?string
    {
        return $this->meetingUrl;
    }

    public function setMeetingUrl(?string $meetingUrl): static
    {
        $this->meetingUrl = $meetingUrl;
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

    /** True si l'événement a une partie en ligne (mode en_ligne ou hybride). */
    public function isOnlineOrHybrid(): bool
    {
        return $this->mode === 'en_ligne' || $this->mode === 'hybride';
    }

    /**
     * Retourne [latitude, longitude] si disponibles (champs ou parsing de locationUrl).
     *
     * @return array{0: float, 1: float}|null
     */
    public function getCoordinates(): ?array
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return [(float) $this->latitude, (float) $this->longitude];
        }
        $url = $this->locationUrl;
        if ($url !== null && preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
            return [(float) $m[1], (float) $m[2]];
        }
        return null;
    }

    /**
     * URL d'intégration iframe pour la carte (Google Maps).
     * Priorité : latitude/longitude (exact) > locationUrl > lieu.
     */
    public function getMapEmbedUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return 'https://www.google.com/maps?q=' . $this->latitude . ',' . $this->longitude . '&output=embed';
        }
        $url = $this->locationUrl;
        if ($url !== null && $url !== '') {
            $urlLower = strtolower($url);
            if (str_contains($urlLower, 'embed') || str_contains($urlLower, 'iframe')) {
                return $url;
            }
            if (preg_match('/[?&]q=([^&]+)/', $url, $m)) {
                return 'https://www.google.com/maps?q=' . rawurlencode(urldecode($m[1])) . '&output=embed';
            }
            if (preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
                return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2] . '&output=embed';
            }
        }
        if ($this->lieu !== null && $this->lieu !== '') {
            return 'https://www.google.com/maps?q=' . rawurlencode($this->lieu) . '&output=embed';
        }
        return null;
    }

    /**
     * URL pour ouvrir la localisation dans Google Maps (page complète).
     * Priorité : latitude/longitude (exact) > locationUrl > lieu.
     */
    public function getMapsPageUrl(): ?string
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return 'https://www.google.com/maps?q=' . $this->latitude . ',' . $this->longitude;
        }
        $url = $this->locationUrl;
        if ($url !== null && $url !== '') {
            $urlLower = strtolower($url);
            if (str_contains($urlLower, 'embed') || str_contains($urlLower, 'iframe')) {
                if (preg_match('/[?&]q=([^&]+)/', $url, $m)) {
                    return 'https://www.google.com/maps?q=' . $m[1];
                }
                if (preg_match('/@(-?\d+\.?\d*),(-?\d+\.?\d*)/', $url, $m)) {
                    return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2];
                }
            }
            return str_starts_with($url, 'http') ? $url : 'https://' . $url;
        }
        if ($this->lieu !== null && $this->lieu !== '') {
            return 'https://www.google.com/maps?q=' . rawurlencode($this->lieu);
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