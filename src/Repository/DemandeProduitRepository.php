<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DemandeProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeProduit>
 */
class DemandeProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeProduit::class);
    }

    /**
     * @return DemandeProduit[]
     */
    public function findEnAttente(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->setParameter('statut', DemandeProduit::STATUT_EN_ATTENTE)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return DemandeProduit[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}