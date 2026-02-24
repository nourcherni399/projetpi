<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LigneCommande;
use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LigneCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneCommande::class);
    }

    public function countByProduit(Produit $produit): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.produit = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return int[] IDs des produits présents dans au moins une ligne de commande
     */
    public function getProduitIdsAvecCommandes(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('DISTINCT IDENTITY(l.produit) AS produit_id')
            ->getQuery()
            ->getResult();
        return array_map('intval', array_filter(array_column($rows, 'produit_id')));
    }

    public function save(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
