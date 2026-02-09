<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Medcin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Medcin>
 */
class MedcinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Medcin::class);
    }

    /**
     * @return list<Medcin>
     */
    public function findAllOrderByNom(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.nom', 'ASC')
            ->addOrderBy('m.prenom', 'ASC')

            ->setMaxResults(1000) // Limite de 1000 résultats pour éviter l'épuisement de mémoire

            ->getQuery()
            ->getResult();
    }
}
