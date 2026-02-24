<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Blog;
use App\Entity\FavorisArticle;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FavorisArticle>
 */
class FavorisArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavorisArticle::class);
    }

    /**
     * @return list<FavorisArticle>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('fa')
            ->where('fa.user = :user')
            ->setParameter('user', $user)
            ->orderBy('fa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndBlog(User $user, Blog $blog): ?FavorisArticle
    {
        return $this->createQueryBuilder('fa')
            ->where('fa.user = :user')
            ->andWhere('fa.blog = :blog')
            ->setParameter('user', $user)
            ->setParameter('blog', $blog)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<int>
     */
    public function findBlogIdsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('fa')
            ->select('IDENTITY(fa.blog) AS blog_id')
            ->where('fa.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['blog_id'], $rows);
    }

    /**
     * Retourne les articles les plus ajoutés aux favoris (publiés et visibles uniquement).
     *
     * @return list<array{id: int, title: string}>
     */
    public function findMostFavoritedArticles(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('fa')
            ->select('b.id', 'b.titre')
            ->innerJoin('fa.blog', 'b')
            ->where('b.isPublished = :published')
            ->andWhere('b.isVisible = :visible')
            ->setParameter('published', true)
            ->setParameter('visible', true)
            ->groupBy('b.id')
            ->addGroupBy('b.titre')
            ->orderBy('COUNT(fa.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'title' => $row['titre'] ?? 'Article sans titre',
        ], $rows);
    }
}
