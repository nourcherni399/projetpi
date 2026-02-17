<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Jour;
use App\Repository\DisponibiliteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: DisponibiliteRepository::class)]
#[Assert\Callback(callback: 'validateHeureFinApresDebut')]
class Disponibilite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: "L'heure de début est obligatoire.")]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: "L'heure de fin est obligatoire.")]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(type: 'string', enumType: Jour::class, columnDefinition: "ENUM('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche')")]
    #[Assert\NotNull(message: 'Le jour est obligatoire.')]
    private ?Jour $jour = null;

    /** Durée en minutes. */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\NotNull(message: 'La durée est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'La durée doit être positive ou nulle.')]
    private ?int $duree = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $estDispo = true;

    #[ORM\ManyToOne(inversedBy: 'disponibilites')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Medcin $medecin = null;

    /** @var Collection<int, RendezVous> */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'disponibilite')]
    private Collection $rendezVous;

    public function __construct()
    {
        $this->rendezVous = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getJour(): ?Jour
    {
        return $this->jour;
    }

    public function setJour(?Jour $jour): static
    {
        $this->jour = $jour;
        return $this;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(?int $duree): static
    {
        $this->duree = $duree;
        return $this;
    }

    public function isEstDispo(): bool
    {
        return $this->estDispo;
    }

    public function setEstDispo(bool $estDispo): static
    {
        $this->estDispo = $estDispo;
        return $this;
    }

    public function getMedecin(): ?Medcin
    {
        return $this->medecin;
    }

    public function setMedecin(?Medcin $medecin): static
    {
        $this->medecin = $medecin;
        return $this;
    }

    /** @return Collection<int, RendezVous> */
    public function getRendezVous(): Collection
    {
        return $this->rendezVous;
    }

    public function addRendezVous(RendezVous $rendezVous): static
    {
        if (!$this->rendezVous->contains($rendezVous)) {
            $this->rendezVous->add($rendezVous);
            $rendezVous->setDisponibilite($this);
        }
        return $this;
    }

    public function removeRendezVous(RendezVous $rendezVous): static
    {
        if ($this->rendezVous->removeElement($rendezVous)) {
            if ($rendezVous->getDisponibilite() === $this) {
                $rendezVous->setDisponibilite(null);
            }
        }
        return $this;
    }

    public function validateHeureFinApresDebut(ExecutionContextInterface $context, mixed $payload = null): void
    {
        $debut = $this->heureDebut;
        $fin = $this->heureFin;
        if ($debut !== null && $fin !== null && $fin <= $debut) {
            $context->buildViolation('L\'heure de fin doit être postérieure à l\'heure de début.')
                ->atPath('heureFin')
                ->addViolation();
        }
    }
}
