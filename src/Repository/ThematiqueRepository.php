<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Thematique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Thematique>
 */
class ThematiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Thematique::class);
    }

    /**
     * @return Thematique[]
     */
    public function search(?string $q): array
    {
        if ($q === null || trim($q) === '') {
            return $this->findBy([], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);
        }
        $qb = $this->createQueryBuilder('t')
            ->where('t.nomThematique LIKE :q OR t.codeThematique LIKE :q OR t.sousTitre LIKE :q OR t.description LIKE :q')
            ->setParameter('q', '%' . addcslashes(trim($q), '%_') . '%')
            ->orderBy('t.ordre', 'ASC')
            ->addOrderBy('t.nomThematique', 'ASC');
        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns list of thematiques with their event count, ordered by event count desc.
     *
     * @return array<array{thematique: Thematique, event_count: int}>
     */
    public function getThematiquesWithEventCount(): array
    {
        $thematiques = $this->findBy([], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);
        $result = [];
        foreach ($thematiques as $t) {
            $result[] = [
                'thematique' => $t,
                'event_count' => $t->getEvenements()->count(),
            ];
        }
        usort($result, static fn ($a, $b) => $b['event_count'] <=> $a['event_count']);
        return $result;
    }
}