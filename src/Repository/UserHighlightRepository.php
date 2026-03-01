<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserHighlight;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserHighlight>
 */
class UserHighlightRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserHighlight::class);
    }

    /**
     * @return UserHighlight[]
     */
    public function findByUserAndTarget(User $user, string $targetType, int $targetId): array
    {
        return $this->findBy(
            [
                'user' => $user,
                'targetType' => $targetType,
                'targetId' => $targetId,
            ],
            ['startOffset' => 'ASC']
        );
    }
}
