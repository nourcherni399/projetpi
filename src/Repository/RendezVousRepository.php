<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\StatusRendezVous;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RendezVous>
 */
class RendezVousRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RendezVous::class);
    }

    /**
     * Liste des rendez-vous d'un médecin, triés par date.
     *
     * @return list<RendezVous>
     */
    public function findByMedecinOrderByDate(Medcin $medecin, string $direction = 'ASC'): array
    {
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('r.dateRdv', $dir)
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Patient>
     */
    public function findDistinctPatientsByMedecin(Medcin $medecin): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.patient)')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.patient IS NOT NULL')
            ->setParameter('medecin', $medecin)
            ->distinct()
            ->getQuery()
            ->getSingleColumnResult();

        if ($result === []) {
            return [];
        }

        return $this->getEntityManager()
            ->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $result)
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<RendezVous>
     */
    public function findByMedecinAndPatient(Medcin $medecin, Patient $patient): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.patient = :patient')
            ->setParameter('medecin', $medecin)
            ->setParameter('patient', $patient)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<RendezVous>
     */
    public function findByPatientOrderByIdDesc(Patient $patient): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.patient = :patient')
            ->setParameter('patient', $patient)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<RendezVous>
     */
    public function findEnAttenteByMedecin(Medcin $medecin): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.status = :status')
            ->setParameter('medecin', $medecin)
            ->setParameter('status', StatusRendezVous::EN_ATTENTE)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<RendezVous>
     */
    public function findByDisponibiliteAndStatus(Disponibilite $disponibilite, StatusRendezVous $status): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.disponibilite = :dispo')
            ->andWhere('r.status = :status')
            ->setParameter('dispo', $disponibilite)
            ->setParameter('status', $status)
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Créneau bloqué uniquement quand le médecin a accepté la demande (confirmer). Les demandes en attente ne bloquent pas le créneau. Les créneaux passés sont considérés comme libres. */
    public function isSlotTaken(Disponibilite $disponibilite): bool
    {
        $date = $disponibilite->getDate();
        $heureFin = $disponibilite->getHeureFin();
        if ($date !== null && $heureFin !== null) {
            $day = $date instanceof \DateTimeImmutable ? $date : \DateTimeImmutable::createFromInterface($date);
            $endAt = $day->setTime(
                (int) $heureFin->format('H'),
                (int) $heureFin->format('i'),
                (int) $heureFin->format('s')
            );
            if ($endAt < new \DateTimeImmutable('now')) {
                return false;
            }
        }

        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.disponibilite = :dispo')
            ->andWhere('r.status = :status')
            ->setParameter('dispo', $disponibilite)
            ->setParameter('status', StatusRendezVous::CONFIRMER->value)
            ->getQuery()
            ->getSingleScalarResult();
        return $count > 0;
    }

    public function countByMedecin(Medcin $medecin): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTodayByMedecin(Medcin $medecin): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.dateRdv = :today')
            ->setParameter('medecin', $medecin)
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctPatientsByMedecin(Medcin $medecin): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(DISTINCT r.patient)')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.patient IS NOT NULL')
            ->setParameter('medecin', $medecin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche dans les rendez-vous (nom, prénom patient, date) pour un médecin.
     *
     * @return list<RendezVous>
     */
    public function searchByMedecin(Medcin $medecin, string $query, int $limit = 20): array
    {
        $term = '%' . addcslashes(trim($query), '%_') . '%';
        if ($term === '%%') {
            return [];
        }
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('r.dateRdv', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit);

        $dateStr = null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($query), $m)) {
            $dateStr = trim($query);
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', trim($query), $m)) {
            $dateStr = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if ($dateStr !== null) {
            $qb->andWhere('r.dateRdv = :date')->setParameter('date', $dateStr);
        } else {
            $qb->andWhere('r.nom LIKE :term OR r.prenom LIKE :term')->setParameter('term', $term);
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<RendezVous>
     */
    public function findUpcomingByMedecin(Medcin $medecin, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.dateRdv >= :today')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('medecin', $medecin)
            ->setParameter('today', new \DateTime('today'))
            ->setParameter('statuses', [StatusRendezVous::EN_ATTENTE, StatusRendezVous::CONFIRMER])
            ->orderBy('r.dateRdv', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countThisWeekByMedecin(Medcin $medecin): int
    {
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = new \DateTime('sunday this week');

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.dateRdv >= :startOfWeek')
            ->andWhere('r.dateRdv <= :endOfWeek')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('medecin', $medecin)
            ->setParameter('startOfWeek', $startOfWeek)
            ->setParameter('endOfWeek', $endOfWeek)
            ->setParameter('statuses', [StatusRendezVous::EN_ATTENTE, StatusRendezVous::CONFIRMER])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Rendez-vous récents ayant une note patient non vide (notes saisies lors de la réservation).
     *
     * @return list<RendezVous>
     */
    public function findRecentWithPatientNotesByMedecin(Medcin $medecin, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->andWhere('r.notePatient IS NOT NULL')
            ->andWhere("r.notePatient != ''")
            ->andWhere("r.notePatient != 'vide'")
            ->setParameter('medecin', $medecin)
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}