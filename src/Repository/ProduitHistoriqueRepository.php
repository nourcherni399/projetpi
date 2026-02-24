<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Produit;
use App\Entity\ProduitHistorique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProduitHistorique>
 */
class ProduitHistoriqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProduitHistorique::class);
    }

    /**
     * @return ProduitHistorique[]
     */
    public function findByProduitOrderByCreatedDesc(Produit $produit, int $limit = 20): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.produit = :produit')
            ->setParameter('produit', $produit)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}