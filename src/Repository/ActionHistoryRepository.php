<?php

namespace App\Repository;

use App\Entity\ActionHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActionHistory>
 *
 * @method ActionHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActionHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActionHistory[]    findAll()
 * @method ActionHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionHistory::class);
    }

    /**
     * Récupère les dernières actions (par défaut les 5 dernières)
     */
    public function findLatestActions(int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.dateHeure', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Enregistre une nouvelle action
     */
    public function createAction(string $utilisateur, string $action, ?string $module = null, ?string $details = null): ActionHistory
    {
        $actionHistory = new ActionHistory();
        $actionHistory->setUtilisateur($utilisateur);
        $actionHistory->setAction($action);
        $actionHistory->setModule($module);
        $actionHistory->setDetails($details);

        $this->getEntityManager()->persist($actionHistory);
        $this->getEntityManager()->flush();

        return $actionHistory;
    }
}
