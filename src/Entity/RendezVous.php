<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
#[ORM\Table(name: 'rendez_vous')]
class RendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Medcin $medecin = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Disponibilite $disponibilite = null;

    /** Date choisie pour le rendez-vous (ex. lundi 17 fév.). */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
<<<<<<< HEAD
    private ?\DateTimeInterface $dateRdv = null;
=======
    private ?\DateTime $dateRdv = null;
>>>>>>> origin/integreModule

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Patient $patient = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
<<<<<<< HEAD
    private ?\DateTimeInterface $dateNaissance = null;
=======
    private ?\DateTime $dateNaissance = null;
>>>>>>> origin/integreModule

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['default' => 'vide'])]
    private ?string $notePatient = 'vide';

    #[ORM\Column(type: 'string', enumType: StatusRendezVous::class, columnDefinition: "ENUM('en_attente', 'confirmer', 'annuler')")]
    private ?StatusRendezVous $status = null;

    #[ORM\Column(type: 'string', enumType: Motif::class, columnDefinition: "ENUM('urgence', 'suivie', 'normal')")]
    private ?Motif $motif = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDisponibilite(): ?Disponibilite
    {
        return $this->disponibilite;
    }

    public function setDisponibilite(?Disponibilite $disponibilite): static
    {
        $this->disponibilite = $disponibilite;
        return $this;
    }

<<<<<<< HEAD
    public function getDateRdv(): ?\DateTimeInterface
=======
    public function getDateRdv(): ?\DateTime
>>>>>>> origin/integreModule
    {
        return $this->dateRdv;
    }

<<<<<<< HEAD
    public function setDateRdv(?\DateTimeInterface $dateRdv): static
=======
    public function setDateRdv(?\DateTime $dateRdv): static
>>>>>>> origin/integreModule
    {
        $this->dateRdv = $dateRdv;
        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->patient;
    }

    public function setPatient(?Patient $patient): static
    {
        $this->patient = $patient;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

<<<<<<< HEAD
    public function getDateNaissance(): ?\DateTimeInterface
=======
    public function getDateNaissance(): ?\DateTime
>>>>>>> origin/integreModule
    {
        return $this->dateNaissance;
    }

<<<<<<< HEAD
    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
=======
    public function setDateNaissance(?\DateTime $dateNaissance): static
>>>>>>> origin/integreModule
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getNotePatient(): ?string
    {
        return $this->notePatient;
    }

    public function setNotePatient(?string $notePatient): static
    {
        $this->notePatient = $notePatient;
        return $this;
    }

    public function getStatus(): ?StatusRendezVous
    {
        return $this->status;
    }

    public function setStatus(?StatusRendezVous $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getMotif(): ?Motif
    {
        return $this->motif;
    }

    public function setMotif(?Motif $motif): static
    {
        $this->motif = $motif;
        return $this;
    }
}
