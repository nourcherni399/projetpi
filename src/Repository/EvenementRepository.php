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

        $searchDate = null;
        if ($q !== null && trim($q) !== '') {
            $qTrimmed = trim($q);
            $searchDate = $this->parseSearchAsDate($qTrimmed);

            if ($searchDate !== null) {
                $qb->andWhere('e.dateEvent = :searchDate');
                $qb->setParameter('searchDate', $searchDate);
            } else {
                $qb->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->like('e.title', ':q'),
                        $qb->expr()->like('e.description', ':q'),
                        $qb->expr()->like('e.lieu', ':q'),
                        $qb->expr()->like('t.nomThematique', ':q')
                    )
                )->setParameter('q', '%' . addcslashes($qTrimmed, '%_') . '%');
            }
        }

        switch ($sortBy) {
            case 'lieu':
                $qb->addOrderBy('e.lieu', $order)->addOrderBy('e.dateEvent', 'ASC')->addOrderBy('e.heureDebut', 'ASC');
                break;
            case 'theme':
                $qb->addOrderBy('t.nomThematique', $order)->addOrderBy('e.dateEvent', 'ASC')->addOrderBy('e.heureDebut', 'ASC');
                break;
            case 'titre':
                $qb->addOrderBy('e.title', $order)->addOrderBy('e.dateEvent', 'ASC')->addOrderBy('e.heureDebut', 'ASC');
                break;
            default:
                $qb->addOrderBy('e.dateEvent', $order)->addOrderBy('e.heureDebut', $order);
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUpcoming(): int
    {
        $today = (new \DateTime())->setTime(0, 0, 0);
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.dateEvent >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPast(): int
    {
        $today = (new \DateTime())->setTime(0, 0, 0);
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.dateEvent < :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns array of [thematique_id => event_count] (thematique_id can be null for events without theme).
     *
     * @return array<int|string, int>
     */
    public function countByThematique(): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.thematique) AS theme_id', 'COUNT(e.id) AS cnt')
            ->groupBy('e.thematique')
            ->getQuery()
            ->getResult();
        $out = [];
        foreach ($rows as $row) {
            $id = $row['theme_id'] ?? 'sans_theme';
            $out[$id] = (int) $row['cnt'];
        }
        return $out;
    }

    private function parseSearchAsDate(string $q): ?\DateTimeInterface
    {
        $formats = ['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i', 'Y-m-d', 'd-m-Y H:i', 'd-m-Y'];
        foreach ($formats as $format) {
            $d = \DateTimeImmutable::createFromFormat($format, trim($q));
            if ($d !== false) {
                return $d->setTime(0, 0, 0);
            }
        }
        return null;
    }

    /**
     * Filtre pour le front : date (from/to), lieu (contient), thématique (id).
     * @return Evenement[]
     */
    public function findFilteredForFront(?\DateTimeInterface $dateFrom, ?\DateTimeInterface $dateTo, ?string $lieu, ?int $thematiqueId): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.thematique', 't')
            ->orderBy('e.dateEvent', 'ASC')
            ->addOrderBy('e.heureDebut', 'ASC');

        if ($dateFrom !== null) {
            $qb->andWhere('e.dateEvent >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }
        if ($dateTo !== null) {
            $qb->andWhere('e.dateEvent <= :dateTo')->setParameter('dateTo', $dateTo);
        }
        if ($lieu !== null && trim($lieu) !== '') {
            $lieuTrimmed = trim($lieu);
            $lieuNormalized = str_replace(["'", "'", "`", "\u{2019}"], "'", $lieuTrimmed);
            $words = array_filter(preg_split('/\s+/u', $lieuNormalized, -1, PREG_SPLIT_NO_EMPTY));
            if ($words !== []) {
                $qb->andWhere('e.lieu IS NOT NULL');
                foreach ($words as $i => $word) {
                    $pattern = '%' . addcslashes($word, '%_') . '%';
                    $paramName = 'lieuWord' . $i;
                    $qb->andWhere('LOWER(e.lieu) LIKE :' . $paramName)->setParameter($paramName, mb_strtolower($pattern));
                }
            }
        }
        if ($thematiqueId !== null && $thematiqueId > 0) {
            $qb->andWhere('e.thematique = :tid')->setParameter('tid', $thematiqueId);
        }

        return $qb->getQuery()->getResult();
    }
}