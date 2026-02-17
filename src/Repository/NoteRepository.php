<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Medcin;
use App\Entity\Note;
use App\Entity\Patient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * @return list<Note>
     */
    public function findByMedecinOrderByDate(Medcin $medecin): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Note>
     */
    public function findByMedecinAndPatient(Medcin $medecin, Patient $patient): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.medecin = :medecin')
            ->andWhere('n.patient = :patient')
            ->setParameter('medecin', $medecin)
            ->setParameter('patient', $patient)
            ->orderBy('n.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByMedecin(Medcin $medecin): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Note>
     */
    public function findRecentByMedecin(Medcin $medecin, int $limit = 3): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.medecin = :medecin')
            ->setParameter('medecin', $medecin)
            ->orderBy('n.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}