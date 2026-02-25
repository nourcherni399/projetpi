<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Medcin;
use App\Entity\MedecinRating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MedecinRating>
 */
class MedecinRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MedecinRating::class);
    }

    /**
     * @return array{avg: float, count: int}
     */
    public function getAverageAndCountByMedecin(Medcin $medecin): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.note) as avgNote', 'COUNT(r.id) as count')
            ->where('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->getQuery()
            ->getSingleResult();

        $avg = $result['avgNote'] !== null ? (float) $result['avgNote'] : 0.0;
        $count = (int) ($result['count'] ?? 0);

        return ['avg' => round($avg, 1), 'count' => $count];
    }

    /**
     * @return array<int, array{avg: float, count: int}>
     */
    public function getAverageAndCountByMedecins(array $medecins): array
    {
        if ($medecins === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.medecin) as medecinId', 'AVG(r.note) as avgNote', 'COUNT(r.id) as count')
            ->where('r.medecin IN (:medecins)')
            ->setParameter('medecins', $medecins)
            ->groupBy('r.medecin');

        $rows = $qb->getQuery()->getResult();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['medecinId'];
            $out[$id] = [
                'avg' => round((float) $row['avgNote'], 1),
                'count' => (int) $row['count'],
            ];
        }

        return $out;
    }

    public function findByMedecinAndUser(Medcin $medecin, User $user): ?MedecinRating
    {
        return $this->createQueryBuilder('r')
            ->where('r.medecin = :medecin')
            ->andWhere('r.user = :user')
            ->setParameter('medecin', $medecin)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
