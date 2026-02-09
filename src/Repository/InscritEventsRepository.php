<?php

declare(strict_types=1);

namespace App\Repository;

<<<<<<< HEAD
use App\Entity\Evenement;
use App\Entity\InscritEvents;
use App\Entity\User;
=======
use App\Entity\InscritEvents;
>>>>>>> origin/integreModule
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InscritEvents>
 */
class InscritEventsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InscritEvents::class);
    }
<<<<<<< HEAD

    /**
     * Inscriptions en attente de validation (admin), triées par date.
     *
     * @return InscritEvents[]
     */
    public function findPendingOrderByDate(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->orderBy('i.dateInscrit', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Inscriptions acceptées ou refusées pour les bandeaux utilisateur.
     *
     * @return InscritEvents[]
     */
    public function findAccepteOrRefuseForUser(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->andWhere('i.statut IN (:statuts)')
            ->setParameter('user', $user)
            ->setParameter('statuts', ['accepte', 'refuse'])
            ->orderBy('i.dateInscrit', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findInscriptionForUserAndEvent(User $user, Evenement $evenement): ?InscritEvents
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->andWhere('i.evenement = :evenement')
            ->setParameter('user', $user)
            ->setParameter('evenement', $evenement)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return InscritEvents[]
     */
    public function findByEvenementOrderByDate(Evenement $evenement): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.evenement = :evenement')
            ->setParameter('evenement', $evenement)
            ->orderBy('i.dateInscrit', 'DESC')
            ->getQuery()
            ->getResult();
    }
=======
>>>>>>> origin/integreModule
}
