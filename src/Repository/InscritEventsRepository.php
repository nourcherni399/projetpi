<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Evenement;
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
     * Inscriptions acceptées, refusées ou en attente pour les bandeaux utilisateur.
     *
     * @return InscritEvents[]
     */
    public function findAccepteRefuseOuEnAttenteForUser(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->andWhere('i.statut IN (:statuts)')
            ->setParameter('user', $user)
            ->setParameter('statuts', ['accepte', 'refuse', 'en_attente'])
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

    /**
     * Inscrits à qui envoyer un rappel (acceptés ou en attente, toujours inscrits).
     *
     * @return InscritEvents[]
     */
    public function findInscritsToRemindForEvent(Evenement $evenement): array
    {
        $list = $this->findByEvenementOrderByDate($evenement);
        return array_values(array_filter($list, static function (InscritEvents $i): bool {
            return $i->isEstInscrit()
                && \in_array($i->getStatut(), ['accepte', 'en_attente'], true)
                && $i->getUser() !== null
                && $i->getUser()->getEmail() !== null
                && trim($i->getUser()->getEmail()) !== '';
        }));
    }

    public function countByStatut(string $statut): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.statut = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotalInscriptions(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

}