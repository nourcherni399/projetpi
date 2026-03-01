<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserHighlightRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserHighlightRepository::class)]
class UserHighlight
{
    public const TARGET_ARTICLE = 'article';
    public const TARGET_MODULE = 'module';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $targetType;

    #[ORM\Column]
    private int $targetId;

    #[ORM\Column]
    private int $startOffset = 0;

    #[ORM\Column]
    private int $endOffset = 0;

    #[ORM\Column(length: 20, options: ['default' => 'yellow'])]
    private string $color = 'yellow';

    #[ORM\Column]
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

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getTargetId(): int
    {
        return $this->targetId;
    }

    public function setTargetId(int $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getStartOffset(): int
    {
        return $this->startOffset;
    }

    public function setStartOffset(int $startOffset): static
    {
        $this->startOffset = $startOffset;

        return $this;
    }

    public function getEndOffset(): int
    {
        return $this->endOffset;
    }

    public function setEndOffset(int $endOffset): static
    {
        $this->endOffset = $endOffset;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
