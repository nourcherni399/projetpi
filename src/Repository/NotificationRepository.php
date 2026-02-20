<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    /**
     * Notifications de type RDV (accepté/refusé) pour un destinataire, pour affichage bandeau utilisateur.
     *
     * @return list<Notification>
     */
    public function findRdvForDestinataireOrderByCreatedDesc(User $user, int $limit = 15): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.type IN (:types)')
            ->setParameter('user', $user)
            ->setParameter('types', [Notification::TYPE_RDV_ACCEPTE, Notification::TYPE_RDV_REFUSE])
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Notifications de type commande (confirmée, livraison, reçu) pour un destinataire.
     *
     * @return list<Notification>
     */
    public function findCommandeForDestinataireOrderByCreatedDesc(User $user, int $limit = 15): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.type IN (:types)')
            ->setParameter('user', $user)
            ->setParameter('types', [
                Notification::TYPE_COMMANDE_CONFIRMEE,
                Notification::TYPE_COMMANDE_LIVRAISON,
                Notification::TYPE_COMMANDE_RECU,
            ])
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Notification>
     */
    public function findByDestinataireOrderByCreatedDesc(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadByDestinataire(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.lu = :lu')
            ->setParameter('user', $user)
            ->setParameter('lu', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
