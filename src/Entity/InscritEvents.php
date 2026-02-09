<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InscritEventsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InscritEventsRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EVENT', columns: ['user_id', 'evenement_id'])]
class InscritEvents
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire.')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'inscrits')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'événement est obligatoire.')]
    private ?Evenement $evenement = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date d\'inscription est obligatoire.')]
    private ?\DateTimeInterface $dateInscrit = null;

    /** true = inscrit, false = annulé (remplace l'enum Status_Inscrit). */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $estInscrit = true;

    /** en_attente | accepte | refuse | desinscrit */
    #[ORM\Column(length: 20, options: ['default' => 'en_attente'])]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Length(max: 20, maxMessage: 'Le statut ne peut pas dépasser {{ limit }} caractères.')]
    #[Assert\Choice(choices: ['en_attente', 'accepte', 'refuse', 'desinscrit'], message: 'Le statut doit être : en_attente, accepte, refuse ou desinscrit.')]
    private string $statut = 'en_attente';

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getDateInscrit(): ?\DateTimeInterface
    {
        return $this->dateInscrit;
    }

    public function setDateInscrit(?\DateTimeInterface $dateInscrit): static
    {
        $this->dateInscrit = $dateInscrit;
        return $this;
    }

    public function isEstInscrit(): bool
    {
        return $this->estInscrit;
    }

    public function setEstInscrit(bool $estInscrit): static
    {
        $this->estInscrit = $estInscrit;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut ?? 'en_attente';
        return $this;
    }
}
