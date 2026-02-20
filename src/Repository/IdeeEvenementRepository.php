<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IdeeEvenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IdeeEvenement>
 */
class IdeeEvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IdeeEvenement::class);
    }

    /**
     * @return IdeeEvenement[]
     */
    public function findRecentOrderByCreatedAt(int $limit = 50): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les idées par IDs en conservant l'ordre des IDs.
     *
     * @param int[] $ids
     * @return IdeeEvenement[]
     */
    public function findByIdsOrdered(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $qb = $this->createQueryBuilder('i')->where('i.id IN (:ids)')->setParameter('ids', $ids);
        $result = $qb->getQuery()->getResult();
        usort($result, static function (IdeeEvenement $a, IdeeEvenement $b) use ($ids): int {
            $pa = array_search($a->getId(), $ids, true);
            $pb = array_search($b->getId(), $ids, true);
            return ($pa <=> $pb);
        });
        return $result;
    }
}
