<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\InscritEvents;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InscritEvents>
 */
class InscritEventsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InscritEvents::class);
    }

    public function findInscriptionForUserAndEvent(User $user, Evenement $evenement): ?InscritEvents
    {
        return $this->findOneBy(
            [
                'user' => $user,
                'evenement' => $evenement,
                'estInscrit' => true,
            ],
            ['dateInscrit' => 'DESC']
        );
    }
}
