<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPreference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPreference>
 */
class UserPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPreference::class);
    }

    public function incrementForUser(User $user, string $category, int $amount = 1): void
    {
        $pref = $this->findOneBy([
            'user' => $user,
            'category' => $category,
        ]);

        if (!$pref instanceof UserPreference) {
            $now = new \DateTimeImmutable();
            $pref = (new UserPreference())
                ->setUser($user)
                ->setCategory($category)
                ->setWeight(0)
                ->setCreatedAt($now)
                ->setUpdatedAt($now);
            $this->getEntityManager()->persist($pref);
        }

        $pref->increment($amount);
    }

    /**
     * @return list<UserPreference>
     */
    public function findTopForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.weight', 'DESC')
            ->addOrderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

