<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Module;
use App\Entity\ModuleBookmark;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModuleBookmark>
 */
class ModuleBookmarkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleBookmark::class);
    }

    /**
     * @return list<ModuleBookmark>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('mb')
            ->where('mb.user = :user')
            ->setParameter('user', $user)
            ->orderBy('mb.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndModule(User $user, Module $module): ?ModuleBookmark
    {
        return $this->createQueryBuilder('mb')
            ->where('mb.user = :user')
            ->andWhere('mb.module = :module')
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
        $rows = $this->createQueryBuilder('mb')
            ->select('IDENTITY(mb.module) AS module_id')
            ->where('mb.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['module_id'], $rows);
    }
}
