<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Disponibilite>
 */
class DisponibiliteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Disponibilite::class);
    }

    /** @return list<Disponibilite> */
    public function findByMedecin(Medcin $medecin): array
    {
        return $this->findBy(
            ['medecin' => $medecin],
            ['jour' => 'ASC', 'heureDebut' => 'ASC']
        );
    }

    /** @return list<Disponibilite> */
    public function findForListing(?Medcin $medecin): array
    {
        $criteria = $medecin !== null ? ['medecin' => $medecin] : ['medecin' => null];
        return $this->findBy(
            $criteria,
            ['jour' => 'ASC', 'heureDebut' => 'ASC']
        );
    }
}
