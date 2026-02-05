<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MedcinRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MedcinRepository::class)]
class Medcin extends User
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $specialite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomCabinet = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $adresseCabinet = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephoneCabinet = null;

    #[ORM\Column(type: 'float', precision: 10, scale: 2, nullable: true)]
    private ?float $tarifConsultation = null;

    /** @var Collection<int, Disponibilite> */
    #[ORM\OneToMany(targetEntity: Disponibilite::class, mappedBy: 'medecin')]
    private Collection $disponibilites;

    /** @var Collection<int, RendezVous> */
    #[ORM\OneToMany(targetEntity: RendezVous::class, mappedBy: 'medecin')]
    private Collection $rendezVous;

    public function __construct()
    {
        parent::__construct();
        $this->disponibilites = new ArrayCollection();
        $this->rendezVous = new ArrayCollection();
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(?string $specialite): static
    {
        $this->specialite = $specialite;
        return $this;
    }

    public function getNomCabinet(): ?string
    {
        return $this->nomCabinet;
    }

    public function setNomCabinet(?string $nomCabinet): static
    {
        $this->nomCabinet = $nomCabinet;
        return $this;
    }

    public function getAdresseCabinet(): ?string
    {
        return $this->adresseCabinet;
    }

    public function setAdresseCabinet(?string $adresseCabinet): static
    {
        $this->adresseCabinet = $adresseCabinet;
        return $this;
    }

    public function getTelephoneCabinet(): ?string
    {
        return $this->telephoneCabinet;
    }

    public function setTelephoneCabinet(?string $telephoneCabinet): static
    {
        $this->telephoneCabinet = $telephoneCabinet;
        return $this;
    }

    public function getTarifConsultation(): ?float
    {
        return $this->tarifConsultation;
    }

    public function setTarifConsultation(?float $tarifConsultation): static
    {
        $this->tarifConsultation = $tarifConsultation;
        return $this;
    }

    /** @return Collection<int, Disponibilite> */
    public function getDisponibilites(): Collection
    {
        return $this->disponibilites;
    }

    public function addDisponibilite(Disponibilite $disponibilite): static
    {
        if (!$this->disponibilites->contains($disponibilite)) {
            $this->disponibilites->add($disponibilite);
            $disponibilite->setMedecin($this);
        }
        return $this;
    }

    public function removeDisponibilite(Disponibilite $disponibilite): static
    {
        if ($this->disponibilites->removeElement($disponibilite)) {
            if ($disponibilite->getMedecin() === $this) {
                $disponibilite->setMedecin(null);
            }
        }
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
            $rendezVous->setMedecin($this);
        }
        return $this;
    }

    public function removeRendezVous(RendezVous $rendezVous): static
    {
        if ($this->rendezVous->removeElement($rendezVous)) {
            if ($rendezVous->getMedecin() === $this) {
                $rendezVous->setMedecin(null);
            }
        }
        return $this;
    }
}
