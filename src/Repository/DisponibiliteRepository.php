<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Disponibilite>
 */
class DisponibiliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Disponibilite::class);
    }

    /** @return list<Disponibilite> */
    public function findByMedecin(Medcin $medecin): array
    {
        return $this->findBy(
            ['medecin' => $medecin],
            ['date' => 'ASC', 'heureDebut' => 'ASC']
        );
    }

    /** @return list<Disponibilite> */
    public function findForListing(?Medcin $medecin): array
    {
        $criteria = $medecin !== null ? ['medecin' => $medecin] : ['medecin' => null];
        return $this->findBy(
            $criteria,
            ['date' => 'ASC', 'heureDebut' => 'ASC']
        );
    }

    /** @return list<Disponibilite> */
    public function findByMedecinAndDate(Medcin $medecin, \DateTimeInterface $date): array
    {
        $dateStr = $date instanceof \DateTimeImmutable
            ? $date->format('Y-m-d')
            : (new \DateTimeImmutable($date->format('Y-m-d')))->format('Y-m-d');
        return $this->createQueryBuilder('d')
            ->andWhere('d.medecin = :medecin')
            ->andWhere('d.date = :date')
            ->setParameter('medecin', $medecin)
            ->setParameter('date', $dateStr)
            ->orderBy('d.heureDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des disponibilités par date (format jj/mm/aaaa, aaaa-mm-jj ou texte contenant une date).
     *
     * @return list<Disponibilite>
     */
    public function searchByMedecin(Medcin $medecin, string $query, int $limit = 20): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('d.date', 'ASC')
            ->addOrderBy('d.heureDebut', 'ASC')
            ->setMaxResults($limit);

        $dateStr = null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $q, $m)) {
            $dateStr = $q;
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $q, $m)) {
            $dateStr = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        } elseif (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $q, $m)) {
            $dateStr = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if ($dateStr === null) {
            return [];
        }
        $qb->andWhere('d.date = :date')->setParameter('date', $dateStr);
        return $qb->getQuery()->getResult();
    }
}
