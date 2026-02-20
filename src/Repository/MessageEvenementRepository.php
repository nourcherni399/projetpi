<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\MessageEvenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageEvenement>
 */
class MessageEvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageEvenement::class);
    }

    /**
     * Messages d'une conversation (user + event), triés par date.
     *
     * @return list<MessageEvenement>
     */
    public function findByEvenementAndUserOrderByDate(Evenement $evenement, User $user): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.evenement = :evenement')
            ->andWhere('m.user = :user')
            ->setParameter('evenement', $evenement)
            ->setParameter('user', $user)
            ->orderBy('m.dateEnvoi', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste des user_id ayant au moins un message pour cet événement (pour l'admin).
     *
     * @return list<array{user_id: int, last_at: \DateTimeInterface}>
     */
    public function findConversationsByEvenement(Evenement $evenement): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.user) AS user_id', 'MAX(m.dateEnvoi) AS last_at')
            ->andWhere('m.evenement = :evenement')
            ->setParameter('evenement', $evenement)
            ->groupBy('m.user')
            ->orderBy('last_at', 'DESC')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'user_id' => (int) $row['user_id'],
                'last_at' => $row['last_at'] instanceof \DateTimeInterface ? $row['last_at'] : new \DateTimeImmutable((string) $row['last_at']),
            ];
        }
        return $out;
    }

    /**
     * Nombre de messages non lus envoyés par les users (pour l'admin, sur un événement ou tous).
     */
    public function countUnreadFromUserByEvenement(?Evenement $evenement = null): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.envoyePar = :envoyePar')
            ->andWhere('m.lu = false')
            ->setParameter('envoyePar', MessageEvenement::ENVOYE_PAR_USER);
        if ($evenement !== null) {
            $qb->andWhere('m.evenement = :evenement')->setParameter('evenement', $evenement);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Nombre de messages non lus (envoyés par les users) par événement, pour une liste d'IDs.
     * Utilisé dans la liste admin pour afficher un indicateur par événement.
     *
     * @param int[] $eventIds
     * @return array<int, int> [ event_id => count ]
     */
    public function countUnreadFromUserByEvenementIds(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.evenement) AS event_id', 'COUNT(m.id) AS cnt')
            ->andWhere('m.evenement IN (:ids)')
            ->andWhere('m.envoyePar = :envoyePar')
            ->andWhere('m.lu = false')
            ->setParameter('ids', $eventIds)
            ->setParameter('envoyePar', MessageEvenement::ENVOYE_PAR_USER)
            ->groupBy('m.evenement')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['event_id']] = (int) $row['cnt'];
        }
        return $out;
    }

    /**
     * ID d'un événement ayant des messages non lus (user → admin), le plus récent en priorité.
     * Pour rediriger l'admin directement vers la discussion.
     */
    public function findFirstEventIdWithUnreadFromUser(): ?int
    {
        $result = $this->createQueryBuilder('m')
            ->select('e.id')
            ->innerJoin('m.evenement', 'e')
            ->andWhere('m.envoyePar = :envoyePar')
            ->andWhere('m.lu = false')
            ->setParameter('envoyePar', MessageEvenement::ENVOYE_PAR_USER)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return \is_array($result) && isset($result['id']) ? (int) $result['id'] : null;
    }

    /**
     * Marquer comme lus les messages envoyés par l'user dans cette conversation.
     */
    public function markAsReadByEvenementAndUser(Evenement $evenement, User $user): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.lu', ':lu')
            ->where('m.evenement = :evenement')
            ->andWhere('m.user = :user')
            ->andWhere('m.envoyePar = :envoyePar')
            ->setParameter('lu', true)
            ->setParameter('evenement', $evenement)
            ->setParameter('user', $user)
            ->setParameter('envoyePar', MessageEvenement::ENVOYE_PAR_USER)
            ->getQuery()
            ->execute();
    }

    /**
     * Événements pour lesquels l'utilisateur a au moins un message non lu de l'admin (pour les notifs).
     *
     * @return list<array{eventId: int, eventTitle: string}>
     */
    public function findEventsWithUnreadAdminMessagesForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('e.id AS eventId', 'e.title AS eventTitle')
            ->innerJoin('m.evenement', 'e')
            ->andWhere('m.user = :user')
            ->andWhere('m.envoyePar = :envoyePar')
            ->andWhere('m.lu = false')
            ->setParameter('user', $user)
            ->setParameter('envoyePar', MessageEvenement::ENVOYE_PAR_ADMIN)
            ->groupBy('e.id', 'e.title')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'eventId' => (int) $row['eventId'],
                'eventTitle' => (string) $row['eventTitle'],
            ];
        }
        return $out;
    }

    /**
     * Nombre de messages non lus envoyés par l'admin pour un user sur un événement (côté user).
     */
    public function countUnreadFromAdminForUserAndEvenement(User $user, Evenement $evenement): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.evenement = :evenement')
            ->andWhere('m.user = :user')
            ->andWhere('m.envoyePar = :envoyePar')
            ->andWhere('m.lu = false')
            ->setParameter('evenement', $evenement)
            ->setParameter('user', $user)
            ->setParameter('envoyePar', MessageEvenement::ENVOYE_PAR_ADMIN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marquer comme lus les messages admin pour cette conversation (côté user).
     */
    public function markAdminMessagesAsReadByEvenementAndUser(Evenement $evenement, User $user): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.lu', ':lu')
            ->where('m.evenement = :evenement')
            ->andWhere('m.user = :user')
            ->andWhere('m.envoyePar = :envoyePar')
            ->setParameter('lu', true)
            ->setParameter('evenement', $evenement)
            ->setParameter('user', $user)
            ->setParameter('envoyePar', MessageEvenement::ENVOYE_PAR_ADMIN)
            ->getQuery()
            ->execute();
    }
}
