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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

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
     * Notifications de type demande produit (produit créé par l'admin) pour un destinataire.
     *
     * @return list<Notification>
     */
    public function findDemandeProduitForDestinataireOrderByCreatedDesc(User $user, int $limit = 15): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', Notification::TYPE_DEMANDE_PRODUIT_IA)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Notifications d'alerte stock pour un admin (destinataire).
     *
     * @return list<Notification>
     */
    public function findAlerteStockForDestinataire(User $user, int $limit = 15): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', Notification::TYPE_ALERTE_STOCK)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les notifications d'un destinataire triées par date de création.
     *
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

    /**
     * Compte le nombre de notifications non lues pour un destinataire.
     */
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

    /**
     * Récupère les notifications par type pour un destinataire.
     *
     * @param string|array<string> $type
     * @return list<Notification>
     */
    public function findByTypeForDestinataire(User $user, string|array $type, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC');

        if (is_array($type)) {
            $qb->andWhere('n.type IN (:types)')
               ->setParameter('types', $type);
        } else {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $type);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Marque toutes les notifications d'un destinataire comme lues.
     */
    public function markAllAsReadForDestinataire(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.lu', ':lu')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.lu = :false')
            ->setParameter('lu', true)
            ->setParameter('user', $user)
            ->setParameter('false', false)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les notifications plus anciennes qu'une certaine date pour un destinataire.
     */
    public function deleteOlderThanForDestinataire(User $user, \DateTimeInterface $date): int
    {
        return (int) $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.createdAt < :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}