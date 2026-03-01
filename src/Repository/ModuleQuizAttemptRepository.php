<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ModuleQuizAttempt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModuleQuizAttempt>
 */
class ModuleQuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModuleQuizAttempt::class);
    }
}
