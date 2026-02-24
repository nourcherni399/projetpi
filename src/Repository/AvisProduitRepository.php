<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AvisProduit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvisProduit>
 */
class AvisProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvisProduit::class);
    }

    public function findOneByProduitAndUser(\App\Entity\Produit $produit, \App\Entity\User $user): ?AvisProduit
    {
        return $this->findOneBy(['produit' => $produit, 'user' => $user]);
    }
}