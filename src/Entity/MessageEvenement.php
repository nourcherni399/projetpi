<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MessageEvenementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageEvenementRepository::class)]
#[ORM\Table(name: 'message_evenement')]
class MessageEvenement
{
    public const ENVOYE_PAR_USER = 'user';
    public const ENVOYE_PAR_ADMIN = 'admin';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Evenement $evenement = null;

    /** Participant de la conversation (utilisateur inscrit ou ayant écrit). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le message ne peut pas être vide.')]
    #[Assert\Length(max: 5000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $contenu = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateEnvoi = null;

    /** 'user' ou 'admin' */
    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: [self::ENVOYE_PAR_USER, self::ENVOYE_PAR_ADMIN])]
    private ?string $envoyePar = null;

    /** Lu par l'admin (messages user) ou par l'user (messages admin). */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $lu = false;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeImmutable
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeImmutable $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    public function getEnvoyePar(): ?string
    {
        return $this->envoyePar;
    }

    public function setEnvoyePar(?string $envoyePar): static
    {
        $this->envoyePar = $envoyePar;
        return $this;
    }

    public function isLu(): bool
    {
        return $this->lu;
    }

    public function setLu(bool $lu): static
    {
        $this->lu = $lu;
        return $this;
    }
}
