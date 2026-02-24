<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * @return OrderItem[]
     */
    public function findByProduit(Produit $produit): array
    {
        return $this->createQueryBuilder('oi')
            ->andWhere('oi.produit = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Quantités vendues par produit (order_item).
     * @return array<int, int> [produit_id => quantite_totale]
     */
    public function getQuantitesVenduesParProduit(): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.produit) AS produit_id', 'SUM(oi.quantite) AS total')
            ->groupBy('oi.produit');
        $rows = $qb->getQuery()->getResult();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['produit_id'];
            $out[$id] = (int) $row['total'];
        }
        return $out;
    }

    /**
     * Quantités vendues par produit sur les N derniers jours (orders).
     * @return array<int, int> [produit_id => quantite]
     */
    public function getQuantitesVenduesParProduitDerniersJours(int $days): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        $qb = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.produit) AS produit_id', 'SUM(oi.quantite) AS total')
            ->join('oi.order', 'o')
            ->where('o.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('oi.produit');
        $rows = $qb->getQuery()->getResult();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['produit_id'];
            $out[$id] = (int) $row['total'];
        }
        return $out;
    }

    /**
     * Nombre de commandes (orders) distinctes par produit.
     * @return array<int, int> [produit_id => nb]
     */
    public function getNombreCommandesParProduit(): array
    {
        $qb = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.produit) AS produit_id', 'COUNT(DISTINCT oi.order) AS nb')
            ->groupBy('oi.produit');
        $rows = $qb->getQuery()->getResult();
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['produit_id']] = (int) $row['nb'];
        }
        return $out;
    }

    /**
     * Quantités vendues par produit et par mois (clé YYYY-MM).
     * @return array<int, array<string, int>> [produit_id => ['2024-01' => qty, ...]]
     */
    public function getVentesParProduitParMois(int $nombreMois = 24): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$nombreMois} months");
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT oi.produit_id, DATE_FORMAT(o.created_at, '%Y-%m') AS mois, SUM(oi.quantite) AS total
            FROM order_item oi
            INNER JOIN `order` o ON o.id = oi.order_id
            WHERE o.created_at >= :since
            GROUP BY oi.produit_id, mois
        ";
        $stmt = $conn->executeQuery($sql, ['since' => $since->format('Y-m-d')]);
        $rows = $stmt->fetchAllAssociative();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['produit_id'];
            if (!isset($out[$id])) {
                $out[$id] = [];
            }
            $out[$id][$row['mois']] = (int) $row['total'];
        }
        return $out;
    }

    public function save(OrderItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrderItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
