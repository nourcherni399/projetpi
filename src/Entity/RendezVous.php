<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use App\Repository\RendezVousRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[Assert\NotNull(message: 'Le médecin est obligatoire.')]
    private ?Medcin $medecin = null;

    #[ORM\ManyToOne(inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Disponibilite $disponibilite = null;

    /** Date choisie pour le rendez-vous (ex. lundi 17 fév.). */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateRdv = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Patient $patient = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.', maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $prenom = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $adresse = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual('today', message: 'La date de naissance ne peut pas être dans le futur.')]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30, maxMessage: 'Le téléphone ne peut pas dépasser {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^[\d\s\-\+\.\(\)]{0,30}$/', message: 'Le téléphone contient des caractères non autorisés.')]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['default' => 'vide'])]
    #[Assert\Length(max: 5000, maxMessage: 'La note ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $notePatient = 'vide';

    #[ORM\Column(type: 'string', enumType: StatusRendezVous::class, columnDefinition: "ENUM('en_attente', 'confirmer', 'annuler')")]
    #[Assert\NotNull(message: 'Le statut est obligatoire.')]
    private ?StatusRendezVous $status = null;

    #[ORM\Column(type: 'string', enumType: Motif::class, columnDefinition: "ENUM('urgence', 'suivie', 'normal')")]
    #[Assert\NotNull(message: 'Le motif est obligatoire.')]
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

    public function getDateRdv(): ?\DateTimeInterface
    {
        return $this->dateRdv;
    }

    public function setDateRdv(?\DateTimeInterface $dateRdv): static
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

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
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