<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Module;
use App\Entity\ModuleCompletion;
use App\Enum\CategorieModule;
use App\Entity\ModuleQuizAttempt;
use App\Entity\User;
use App\Repository\ModuleCompletionRepository;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ModuleProgressionService
{
    private const NIVEAU_ORDER = ['facile' => 1, 'moyen' => 2, 'difficile' => 3];

    public function __construct(
        private readonly ModuleCompletionRepository $moduleCompletionRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function isModuleUnlocked(?User $user, Module $module): bool
    {
        if ($user === null) {
            return $module->getNiveau() === 'facile';
        }

        $niveau = $module->getNiveau();
        if ($niveau === 'facile') {
            return true;
        }

        return $this->hasCompletedLevel($user, $this->getPreviousLevel($niveau), $module->getCategorie());
    }

    public function hasCompletedModule(?User $user, Module $module): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->moduleCompletionRepository->hasUserCompletedModule($user, $module);
    }

    public function hasCompletedLevel(User $user, string $niveau, ?CategorieModule $categorie = null): bool
    {
        $criteria = [
            'niveau' => $niveau,
            'isPublished' => true,
        ];
        if ($categorie !== null && $categorie !== CategorieModule::EMPTY) {
            $criteria['categorie'] = $categorie;
        }
        $modulesOfLevel = $this->moduleRepository->findBy($criteria);

        // Pas de modules à ce niveau : vérifier le niveau d'avant (ex: Difficile sans Moyen → exiger Facile)
        if (count($modulesOfLevel) === 0 && $niveau !== 'facile') {
            return $this->hasCompletedLevel($user, $this->getPreviousLevel($niveau), $categorie);
        }

        if (count($modulesOfLevel) === 0) {
            return true;
        }

        $completedIds = $this->moduleCompletionRepository->getCompletedModuleIdsByUser($user);

        foreach ($modulesOfLevel as $m) {
            if ($m->getId() !== null && !\in_array($m->getId(), $completedIds, true)) {
                return false;
            }
        }

        return true;
    }

    public function getCompletedModuleIds(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $this->moduleCompletionRepository->getCompletedModuleIdsByUser($user);
    }

    public function markModuleComplete(User $user, Module $module, ModuleQuizAttempt $attempt): void
    {
        $existing = $this->moduleCompletionRepository->findOneBy(['user' => $user, 'module' => $module]);
        if ($existing !== null) {
            return;
        }

        $completion = new ModuleCompletion();
        $completion->setUser($user);
        $completion->setModule($module);
        $completion->setQuizAttempt($attempt);
        $completion->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->persist($completion);
        $this->entityManager->flush();
    }

    private function getPreviousLevel(string $niveau): string
    {
        $currentOrder = self::NIVEAU_ORDER[$niveau] ?? 3;
        $prevOrder = max(1, $currentOrder - 1);

        foreach (self::NIVEAU_ORDER as $n => $order) {
            if ($order === $prevOrder) {
                return $n;
            }
        }

        return 'facile';
    }
}
