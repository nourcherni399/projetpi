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
     * @return list<RendezVous>
     */
    public function findByMedecinOrderByIdDesc(Medcin $medecin): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('r.id', 'DESC')
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

    /** Créneau (disponibilite + date) déjà pris (en_attente ou confirmer). */
    public function isSlotTaken(Disponibilite $disponibilite, \DateTimeInterface $date): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.disponibilite = :dispo')
            ->andWhere('r.dateRdv = :date')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('dispo', $disponibilite)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('statuses', [StatusRendezVous::EN_ATTENTE, StatusRendezVous::CONFIRMER])
            ->getQuery()
            ->getSingleScalarResult();
        return $count > 0;
    }
}
