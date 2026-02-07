<?php

namespace App\Repository;

use App\Entity\Commentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commentaire>
 *
 * @method Commentaire|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commentaire|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commentaire[]    findAll()
 * @method Commentaire[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaire::class);
    }

    /**
     * Récupère les commentaires publiés pour un article
     */
    public function findPublishedByBlog(int $blogId): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.blog = :blogId')
            ->andWhere('c.isPublished = :published')
            ->setParameter('blogId', $blogId)
            ->setParameter('published', true)
            ->orderBy('c.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de commentaires pour un article
     */
    public function countByBlog(int $blogId): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.blog = :blogId')
            ->andWhere('c.isPublished = :published')
            ->setParameter('blogId', $blogId)
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les commentaires récents
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
