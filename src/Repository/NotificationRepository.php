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
