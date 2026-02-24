<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LigneCommande;
use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LigneCommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneCommande::class);
    }

    /**
     * @return LigneCommande[]
     */
    public function findByProduit(Produit $produit): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.produit = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getResult();
    }

    public function countByProduit(Produit $produit): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.produit = :produit')
            ->setParameter('produit', $produit)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne pour chaque produit la quantité totale vendue (lignes de commande).
     * @return array<int, int> [produit_id => quantite_totale]
     */
    public function getQuantitesVenduesParProduit(): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.produit) AS produit_id', 'SUM(l.quantite) AS total')
            ->groupBy('l.produit');
        $rows = $qb->getQuery()->getResult();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['produit_id'];
            $out[$id] = (int) $row['total'];
        }
        return $out;
    }

    /**
     * Quantités vendues par produit sur les N derniers jours (commandes).
     * @return array<int, int> [produit_id => quantite]
     */
    public function getQuantitesVenduesParProduitDerniersJours(int $days): array
    {
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        $qb = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.produit) AS produit_id', 'SUM(l.quantite) AS total')
            ->join('l.commande', 'c')
            ->where('c.dateCreation >= :since')
            ->setParameter('since', $since)
            ->groupBy('l.produit');
        $rows = $qb->getQuery()->getResult();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['produit_id'];
            $out[$id] = (int) $row['total'];
        }
        return $out;
    }

    /**
     * Nombre de commandes distinctes par produit.
     * @return array<int, int> [produit_id => nb_commandes]
     */
    public function getNombreCommandesParProduit(): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.produit) AS produit_id', 'COUNT(DISTINCT l.commande) AS nb')
            ->groupBy('l.produit');
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
            SELECT l.produit_id, DATE_FORMAT(c.date_creation, '%Y-%m') AS mois, SUM(l.quantite) AS total
            FROM ligne_commande l
            INNER JOIN commande c ON c.id = l.commande_id
            WHERE c.date_creation >= :since
            GROUP BY l.produit_id, mois
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

    /**
     * @return int[] IDs des produits présents dans au moins une ligne de commande
     */
    public function getProduitIdsAvecCommandes(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('DISTINCT IDENTITY(l.produit) AS produit_id')
            ->getQuery()
            ->getResult();
        return array_map('intval', array_filter(array_column($rows, 'produit_id')));
    }

    public function save(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(LigneCommande $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}