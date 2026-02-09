<?php

namespace App\Entity;

use App\Repository\CommentaireRepository;
<<<<<<< HEAD
=======
use Doctrine\DBAL\Types\Types;
>>>>>>> bc1944e (Integration user - PI)
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

<<<<<<< HEAD
    #[ORM\Column(length: 255)]
=======
    #[ORM\Column(type: Types::TEXT)]
>>>>>>> bc1944e (Integration user - PI)
    private ?string $contenu = null;

    #[ORM\Column]
    private ?bool $isPublished = null;

<<<<<<< HEAD
    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column]
    private ?\DateTime $dateModif = null;

    #[ORM\ManyToOne(inversedBy: 'commentaire')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Blog $blog = null;

    #[ORM\ManyToOne(inversedBy: 'commentaires')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

=======
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateModif = null;

    #[ORM\ManyToOne(inversedBy: 'commentaires')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'commentaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Blog $blog = null;

>>>>>>> bc1944e (Integration user - PI)
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

<<<<<<< HEAD
    public function getDateCreation(): ?\DateTime
=======
    public function getDateCreation(): ?\DateTimeInterface
>>>>>>> bc1944e (Integration user - PI)
    {
        return $this->dateCreation;
    }

<<<<<<< HEAD
    public function setDateCreation(\DateTime $dateCreation): static
=======
    public function setDateCreation(\DateTimeInterface $dateCreation): static
>>>>>>> bc1944e (Integration user - PI)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

<<<<<<< HEAD
    public function getDateModif(): ?\DateTime
=======
    public function getDateModif(): ?\DateTimeInterface
>>>>>>> bc1944e (Integration user - PI)
    {
        return $this->dateModif;
    }

<<<<<<< HEAD
    public function setDateModif(\DateTime $dateModif): static
=======
    public function setDateModif(?\DateTimeInterface $dateModif): static
>>>>>>> bc1944e (Integration user - PI)
    {
        $this->dateModif = $dateModif;

        return $this;
    }

<<<<<<< HEAD
=======
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

>>>>>>> bc1944e (Integration user - PI)
    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): static
    {
        $this->blog = $blog;

        return $this;
    }

<<<<<<< HEAD
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
=======
    public function __construct()
    {
        $this->dateCreation = new \DateTime();
>>>>>>> bc1944e (Integration user - PI)
    }
}
