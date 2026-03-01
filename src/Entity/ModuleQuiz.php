<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ModuleQuizRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleQuizRepository::class)]
#[ORM\Table(name: 'module_quiz')]
class ModuleQuiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Module::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Module $module = null;

    #[ORM\Column(type: Types::JSON)]
    private array $questionsJson = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return array<int, array{question: string, reponses: array<int, string>, bonneReponse: int}>
     */
    public function getQuestionsJson(): array
    {
        return $this->questionsJson;
    }

    /**
     * @param array<int, array{question: string, reponses: array<int, string>, bonneReponse: int}> $questionsJson
     */
    public function setQuestionsJson(array $questionsJson): static
    {
        $this->questionsJson = $questionsJson;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
