<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Module;
use App\Entity\ModuleQuiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModuleQuiz>
 */
class ModuleQuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleQuiz::class);
    }

    public function findLatestForModule(Module $module): ?ModuleQuiz
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.module = :module')
            ->setParameter('module', $module)
            ->orderBy('q.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
