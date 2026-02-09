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
<<<<<<< HEAD

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
=======
>>>>>>> origin/integreModule
}
