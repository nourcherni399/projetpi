<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FavorisModule;
use App\Entity\Module;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavorisModule>
 */
class FavorisModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavorisModule::class);
    }

    /**
     * @return list<FavorisModule>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('fm')
            ->where('fm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('fm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndModule(User $user, Module $module): ?FavorisModule
    {
        return $this->createQueryBuilder('fm')
            ->where('fm.user = :user')
            ->andWhere('fm.module = :module')
            ->setParameter('user', $user)
            ->setParameter('module', $module)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<int>
     */
    public function findModuleIdsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('fm')
            ->select('IDENTITY(fm.module) AS module_id')
            ->where('fm.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['module_id'], $rows);
    }
}

