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
     * Indique si un créneau existe déjà pour ce médecin avec la même date et les mêmes heures (début et fin).
     */
    public function existsSameSlot(Medcin $medecin, \DateTimeInterface $date, \DateTimeInterface $heureDebut, \DateTimeInterface $heureFin, ?int $excludeId = null): bool
    {
        $dateNorm = $date instanceof \DateTimeImmutable ? $date : new \DateTimeImmutable($date->format('Y-m-d'));
        $hD = $heureDebut->format('H:i');
        $hF = $heureFin->format('H:i');

        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.medecin = :medecin')
            ->andWhere('d.date = :date')
            ->setParameter('medecin', $medecin)
            ->setParameter('date', $dateNorm);
        if ($excludeId !== null) {
            $qb->andWhere('d.id != :exclude')->setParameter('exclude', $excludeId);
        }
        /** @var list<Disponibilite> $candidates */
        $candidates = $qb->getQuery()->getResult();
        foreach ($candidates as $entity) {
            $dbDebut = $entity->getHeureDebut()?->format('H:i');
            $dbFin = $entity->getHeureFin()?->format('H:i');
            if ($dbDebut !== null && $dbFin !== null && $dbDebut === $hD && $dbFin === $hF) {
                return true;
            }
        }
        return false;
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

    /**
     * Trouve un créneau du médecin pour la date et l'heure données (l'heure demandée doit être dans [heureDebut, heureFin]).
     */
    public function findSlotAt(Medcin $medecin, string $dateYmd, string $timeHi): ?Disponibilite
    {
        $dispos = $this->findByMedecinAndDate($medecin, new \DateTimeImmutable($dateYmd));
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $timeHi, $m)) {
            return null;
        }
        $requestedMinutes = (int) $m[1] * 60 + (int) $m[2];
        foreach ($dispos as $dispo) {
            if (!$dispo->isEstDispo()) {
                continue;
            }
            $debut = $dispo->getHeureDebut();
            $fin = $dispo->getHeureFin();
            if ($debut === null || $fin === null) {
                continue;
            }
            $debutMinutes = (int) $debut->format('H') * 60 + (int) $debut->format('i');
            $finMinutes = (int) $fin->format('H') * 60 + (int) $fin->format('i');
            if ($requestedMinutes >= $debutMinutes && $requestedMinutes < $finMinutes) {
                return $dispo;
            }
        }
        return null;
    }
}
