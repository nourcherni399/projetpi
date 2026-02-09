<?php

namespace App\Entity;
<<<<<<< HEAD
use App\Entity\Enum\TypePost;

=======

use App\Entity\Enum\TypePost;
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
use App\Repository\BlogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogRepository::class)]
class Blog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

<<<<<<< HEAD
#[ORM\Column(
    columnDefinition: "ENUM('recommandation', 'plainte', 'question', 'experience') NOT NULL"
)]
private string $type;
=======
    #[ORM\Column(
        columnDefinition: "ENUM('recommandation', 'plainte', 'question', 'experience') NOT NULL"
    )]
    private string $type;
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3

    #[ORM\Column]
    private ?bool $isPublished = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isUrgent = null;

    #[ORM\Column]
    private ?bool $isVisible = null;

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column]
    private ?\DateTime $dateModif = null;

    #[ORM\Column(length: 255)]
    private ?string $contenu = null;

    #[ORM\ManyToOne(inversedBy: 'blogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Module $module = null;

    #[ORM\ManyToOne(inversedBy: 'blogs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * @var Collection<int, Commentaire>
     */
    #[ORM\OneToMany(targetEntity: Commentaire::class, mappedBy: 'blog')]
<<<<<<< HEAD
    private Collection $commentaire;

    public function __construct()
    {
        $this->commentaire = new ArrayCollection();
=======
    private Collection $commentaires;

    public function __construct()
    {
        $this->commentaires = new ArrayCollection();
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function isUrgent(): ?bool
    {
        return $this->isUrgent;
    }

    public function setIsUrgent(?bool $isUrgent): static
    {
        $this->isUrgent = $isUrgent;
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function isVisible(): ?bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): static
    {
        $this->isVisible = $isVisible;
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function getDateModif(): ?\DateTime
    {
        return $this->dateModif;
    }

    public function setDateModif(\DateTime $dateModif): static
    {
        $this->dateModif = $dateModif;
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
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

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(?Module $module): static
    {
        $this->module = $module;
<<<<<<< HEAD

=======
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
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    /**
     * @return Collection<int, Commentaire>
     */
<<<<<<< HEAD
    public function getCommentaire(): Collection
    {
        return $this->commentaire;
=======
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
    }

    public function addCommentaire(Commentaire $commentaire): static
    {
<<<<<<< HEAD
        if (!$this->commentaire->contains($commentaire)) {
            $this->commentaire->add($commentaire);
            $commentaire->setBlog($this);
        }

=======
        if (!$this->commentaires->contains($commentaire)) {
            $this->commentaires->add($commentaire);
            $commentaire->setBlog($this);
        }
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }

    public function removeCommentaire(Commentaire $commentaire): static
    {
<<<<<<< HEAD
        if ($this->commentaire->removeElement($commentaire)) {
            // set the owning side to null (unless already changed)
=======
        if ($this->commentaires->removeElement($commentaire)) {
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
            if ($commentaire->getBlog() === $this) {
                $commentaire->setBlog(null);
            }
        }
<<<<<<< HEAD

=======
>>>>>>> 95dad675f769b1ba531a1349a5f6084dd26c4be3
        return $this;
    }
}
