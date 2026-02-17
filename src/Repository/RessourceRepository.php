<?php

namespace App\Repository;

use App\Entity\Module;
use App\Entity\Ressource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ressource>
 */
class RessourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ressource::class);
    }

    /**
     * @return list<Ressource>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.module', 'm')
            ->addSelect('m')
            ->orderBy('m.titre', 'ASC')
            ->addOrderBy('r.ordre', 'ASC')
            ->addOrderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Ressource>
     */
    public function findByModuleOrdered(Module $module): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.module = :module')
            ->setParameter('module', $module)
            ->orderBy('r.ordre', 'ASC')
            ->addOrderBy('r.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Ressource[] Returns an array of Ressource objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Ressource
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
