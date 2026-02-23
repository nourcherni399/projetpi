<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CommentaireReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentaireReactionRepository::class)]
#[ORM\Table(name: 'commentaire_reaction', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_user_commentaire_reaction', columns: ['user_id', 'commentaire_id'])])]
class CommentaireReaction
{
    public const TYPES = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Commentaire::class, inversedBy: 'reactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Commentaire $commentaire;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type = 'like';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCommentaire(): Commentaire
    {
        return $this->commentaire;
    }

    public function setCommentaire(Commentaire $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, self::TYPES, true)) {
            $type = 'like';
        }
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
