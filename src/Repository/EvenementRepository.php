<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return Evenement[]
     */
    public function searchAndSort(?string $q, string $sortBy = 'date', string $sortOrder = 'asc'): array
    {
        $order = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.thematique', 't');

        if ($q !== null && $q !== '') {
            $trimmed = trim($q);
            $searchDate = null;
            foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/Y H:i', 'd-m-Y H:i', 'Y-m-d H:i'] as $format) {
                $parsed = \DateTimeImmutable::createFromFormat($format, $trimmed);
                if ($parsed !== false) {
                    $searchDate = $parsed;
                    break;
                }
            }
            if ($searchDate !== null) {
                $qb->andWhere('e.dateEvent = :searchDate')->setParameter('searchDate', $searchDate->format('Y-m-d'));
            } else {
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->like('e.title', ':q'),
                        $qb->expr()->like('e.description', ':q'),
                        $qb->expr()->like('e.lieu', ':q'),
                        $qb->expr()->like('t.nomThematique', ':q')
                    )
                )->setParameter('q', '%' . addcslashes($trimmed, '%_') . '%');
            }
        }

        switch ($sortBy) {
            case 'lieu':
                $qb->addOrderBy('e.lieu', $order)->addOrderBy('e.dateEvent', $order)->addOrderBy('e.heureDebut', $order);
                break;
            case 'theme':
                $qb->addOrderBy('t.nomThematique', $order)->addOrderBy('e.dateEvent', $order)->addOrderBy('e.heureDebut', $order);
                break;
            case 'titre':
                $qb->addOrderBy('e.title', $order)->addOrderBy('e.dateEvent', $order)->addOrderBy('e.heureDebut', $order);
                break;
            default:
                $qb->addOrderBy('e.dateEvent', $order)->addOrderBy('e.heureDebut', $order);
                break;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Filtrage par date (optionnel), thématique (optionnel), lieu (optionnel).
     * @return Evenement[]
     */
    public function findFiltered(?\DateTimeInterface $dateFrom, ?\DateTimeInterface $dateTo, ?int $thematiqueId, ?string $lieu): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.thematique', 't')
            ->addOrderBy('e.dateEvent', 'ASC')
            ->addOrderBy('e.heureDebut', 'ASC');

        if ($dateFrom !== null) {
            $qb->andWhere('e.dateEvent >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }
        if ($dateTo !== null) {
            $qb->andWhere('e.dateEvent <= :dateTo')->setParameter('dateTo', $dateTo);
        }
        if ($thematiqueId !== null && $thematiqueId > 0) {
            $qb->andWhere('e.thematique = :tid')->setParameter('tid', $thematiqueId);
        }
        if ($lieu !== null && $lieu !== '') {
            $qb->andWhere('e.lieu LIKE :lieu')->setParameter('lieu', '%' . addcslashes($lieu, '%_') . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return string[] */
    public function findDistinctLieux(): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('e.lieu')
            ->distinct()
            ->orderBy('e.lieu', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
        return array_values(array_filter($result));
    }
}
