<?php

namespace App\Entity;

use App\Repository\CommentaireRepository;
<<<<<<< HEAD
=======
use Doctrine\DBAL\Types\Types;
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
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
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
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

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3

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
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;
<<<<<<< HEAD

        return $this;
    }

    public function getDateCreation(): ?\DateTime
=======
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
    {
        return $this->dateCreation;
    }

<<<<<<< HEAD
    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateModif(): ?\DateTime
=======
    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateModif(): ?\DateTimeInterface
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
    {
        return $this->dateModif;
    }

<<<<<<< HEAD
    public function setDateModif(\DateTime $dateModif): static
    {
        $this->dateModif = $dateModif;

        return $this;
    }

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): static
    {
        $this->blog = $blog;

=======
    public function setDateModif(?\DateTimeInterface $dateModif): static
    {
        $this->dateModif = $dateModif;
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
<<<<<<< HEAD

=======
        return $this;
    }

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): static
    {
        $this->blog = $blog;
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }
}
