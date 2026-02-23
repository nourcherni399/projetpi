<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commentaire;
use App\Entity\CommentaireReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentaireReaction>
 */
class CommentaireReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentaireReaction::class);
    }

    public function findOneByUserAndCommentaire(User $user, Commentaire $commentaire): ?CommentaireReaction
    {
        return $this->createQueryBuilder('cr')
            ->where('cr.user = :user')
            ->andWhere('cr.commentaire = :commentaire')
            ->setParameter('user', $user)
            ->setParameter('commentaire', $commentaire)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<int>
     */
    public function findCommentaireIdsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('cr')
            ->select('IDENTITY(cr.commentaire) AS commentaire_id')
            ->where('cr.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['commentaire_id'], $rows);
    }

    /**
     * Returns [commentaire_id => type] for the given user
     * @return array<int, string>
     */
    public function findReactionTypesByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('cr')
            ->select('IDENTITY(cr.commentaire) AS commentaire_id, cr.type')
            ->where('cr.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['commentaire_id']] = $row['type'];
        }

        return $result;
    }

    public function countByCommentaire(Commentaire $commentaire): int
    {
        return (int) $this->createQueryBuilder('cr')
            ->select('COUNT(cr.id)')
            ->where('cr.commentaire = :commentaire')
            ->setParameter('commentaire', $commentaire)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns [type => count] for the given commentaire
     * @return array<string, int>
     */
    public function countByTypeForCommentaire(Commentaire $commentaire): array
    {
        $rows = $this->createQueryBuilder('cr')
            ->select('cr.type, COUNT(cr.id) AS cnt')
            ->where('cr.commentaire = :commentaire')
            ->setParameter('commentaire', $commentaire)
            ->groupBy('cr.type')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['type']] = (int) $row['cnt'];
        }

        return $result;
    }
}

