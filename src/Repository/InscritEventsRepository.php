<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\InscritEvents;
use App\Entity\User;
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

    public function findInscriptionForUserAndEvent(User $user, Evenement $evenement): ?InscritEvents
    {
        return $this->findOneBy(
            [
                'user' => $user,
                'evenement' => $evenement,
            ],
            ['dateInscrit' => 'DESC']
        );
    }

    /**
     * @return InscritEvents[]
     */
    public function findByEvenementOrderByDate(Evenement $evenement): array
    {
        return $this->findBy(
            ['evenement' => $evenement],
            ['dateInscrit' => 'DESC']
        );
    }

    /**
     * Demandes en attente (tous événements), pour les notifications admin.
     * @return InscritEvents[]
     */
    public function findPendingOrderByDate(): array
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.evenement', 'e')
            ->innerJoin('i.user', 'u')
            ->addSelect('e', 'u')
            ->where('i.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->orderBy('i.dateInscrit', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Inscriptions acceptées ou refusées pour un participant (notifications en haut de page), limité à 5.
     * @return InscritEvents[]
     */
    public function findAccepteOrRefuseForUser(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->innerJoin('i.evenement', 'e')
            ->addSelect('e')
            ->where('i.user = :user')
            ->andWhere('i.statut IN (:statuts)')
            ->setParameter('user', $user)
            ->setParameter('statuts', ['accepte', 'refuse'])
            ->orderBy('i.dateInscrit', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }
}
