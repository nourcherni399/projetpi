<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ModuleQuizAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleQuizAttemptRepository::class)]
#[ORM\Table(name: 'module_quiz_attempt')]
class ModuleQuizAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Module::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Module $module = null;

    #[ORM\ManyToOne(targetEntity: ModuleQuiz::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ModuleQuiz $quiz = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $scorePercent = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $passed = false;

    /** @var array<int, int> Réponses envoyées (index question => index réponse choisie) */
    #[ORM\Column(type: Types::JSON)]
    private array $answersJson = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct()
    {
        $this->completedAt = new \DateTimeImmutable();
    }

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

    public function getModule(): ?Module
    {
        return $this->module;
    }

    public function setModule(?Module $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getQuiz(): ?ModuleQuiz
    {
        return $this->quiz;
    }

    public function setQuiz(?ModuleQuiz $quiz): static
    {
        $this->quiz = $quiz;

        return $this;
    }

    public function getScorePercent(): ?string
    {
        return $this->scorePercent;
    }

    public function setScorePercent(string $scorePercent): static
    {
        $this->scorePercent = $scorePercent;

        return $this;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed): static
    {
        $this->passed = $passed;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    public function getAnswersJson(): array
    {
        return $this->answersJson;
    }

    /**
     * @param array<int, int> $answersJson
     */
    public function setAnswersJson(array $answersJson): static
    {
        $this->answersJson = $answersJson;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
