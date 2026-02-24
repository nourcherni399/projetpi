<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CartItem;
use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    /**
     * @return CartItem[]
     */
    public function findByProduit(Produit $produit): array
    {
        return $this->createQueryBuilder('ci')
            ->andWhere('ci.produit = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getResult();
    }

    public function save(CartItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CartItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
