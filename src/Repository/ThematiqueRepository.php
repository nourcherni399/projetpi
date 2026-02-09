<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Thematique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Thematique>
 */
class ThematiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Thematique::class);
    }
<<<<<<< HEAD
=======

    /**
     * @return list<Thematique>
     */
    public function search(?string $q): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.nomThematique', 'ASC');

        if ($q !== null && $q !== '') {
            $term = '%' . trim($q) . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    't.nomThematique LIKE :q',
                    't.codeThematique LIKE :q',
                    't.sousTitre LIKE :q',
                    't.description LIKE :q'
                )
            )->setParameter('q', $term);
        }

        return $qb->getQuery()->getResult();
    }
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
}
