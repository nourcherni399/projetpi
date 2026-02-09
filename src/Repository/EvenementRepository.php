<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return Evenement[]
     */
    public function searchAndSort(?string $q, string $sortBy = 'date', string $sortOrder = 'asc'): array
    {
        $order = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.thematique', 't');

        if ($q !== null && $q !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('e.title', ':q'),
                    $qb->expr()->like('e.description', ':q'),
                    $qb->expr()->like('e.lieu', ':q'),
                    $qb->expr()->like('t.nomThematique', ':q')
                )
            )->setParameter('q', '%' . addcslashes($q, '%_') . '%');
        }

        switch ($sortBy) {
            case 'lieu':
                $qb->addOrderBy('e.lieu', $order)->addOrderBy('e.dateEvent', 'ASC')->addOrderBy('e.heureDebut', 'ASC');
                break;
            case 'theme':
                $qb->addOrderBy('t.nomThematique', $order)->addOrderBy('e.dateEvent', 'ASC')->addOrderBy('e.heureDebut', 'ASC');
                break;
            case 'titre':
                $qb->addOrderBy('e.title', $order)->addOrderBy('e.dateEvent', 'ASC')->addOrderBy('e.heureDebut', 'ASC');
                break;
            default:
                $qb->addOrderBy('e.dateEvent', $order)->addOrderBy('e.heureDebut', $order);
                break;
        }

        return $qb->getQuery()->getResult();
    }
}
