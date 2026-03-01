<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Module;
use App\Entity\ModuleCompletion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModuleCompletion>
 */
class ModuleCompletionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleCompletion::class);
    }

    public function hasUserCompletedModule(User $user, Module $module): bool
    {
        return $this->findOneBy(['user' => $user, 'module' => $module]) !== null;
    }

    /**
     * @return int[] IDs des modules complétés par l'utilisateur
     */
    public function getCompletedModuleIdsByUser(User $user): array
    {
        $results = $this->createQueryBuilder('mc')
            ->select('IDENTITY(mc.module)')
            ->andWhere('mc.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $results);
    }
}
