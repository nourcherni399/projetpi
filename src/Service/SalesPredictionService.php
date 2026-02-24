<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Produit;
use App\Repository\LigneCommandeRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProduitRepository;
use Phpml\Clustering\KMeans;
use Phpml\Preprocessing\Normalizer as PhpmlNormalizer;

final class SalesPredictionService
{
    private const DAYS_RECENT = 90;
    private const DAYS_PREVIOUS = 90;
    private const MOIS_PREVISION = 12;
    private const NORM_STD = PhpmlNormalizer::NORM_STD;

    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly LigneCommandeRepository $ligneCommandeRepository,
        private readonly OrderItemRepository $orderItemRepository,
    ) {
    }

    /**
     * Rapport complet : prédiction + prévisions ventes, prix, stock, tendances, CA estimé.
     *
     * @return array{
     *   predictions: array,
     *   byLabel: array,
     *   byStrategic: array,
     *   ca_estime_annee: float,
     *   annee_cible: int,
     *   total_ventes_prevu_mois: int,
     *   mois_cible: string
     * }
     */
    public function fullReport(): array
    {
        $predictions = $this->predict();
        if ($predictions === []) {
            return [
                'predictions' => [],
                'byLabel' => ['forte_demande' => [], 'potentiel_moyen' => [], 'faible_rotation' => []],
                'byStrategic' => ['star' => [], 'a_risque' => [], 'a_relancer' => [], 'a_supprimer' => []],
                'ca_estime_annee' => 0.0,
                'annee_cible' => (int) date('Y') + 1,
                'total_ventes_prevu_mois' => 0,
                'mois_cible' => (new \DateTimeImmutable('+1 month'))->format('Y-m'),
            ];
        }

        $ventesParMois = $this->mergeMonthly(
            $this->ligneCommandeRepository->getVentesParProduitParMois(24),
            $this->orderItemRepository->getVentesParProduitParMois(24)
        );

        $now = new \DateTimeImmutable();
        $moisProchain = $now->modify('+1 month')->format('Y-m');
        $anneeCible = (int) $now->format('Y') + 1;

        $totalVentesPrevuMois = 0;
        $caEstimeAnnee = 0.0;

        $byStrategic = ['star' => [], 'a_risque' => [], 'a_relancer' => [], 'a_supprimer' => []];

        foreach ($predictions as $id => &$row) {
            $produit = $row['produit'];
            $prix = (float) $produit->getPrix();
            $totalVendu = $row['total_vendu'] ?? 0;
            $recentVendu = $row['recent_vendu'] ?? 0;
            $moisData = $ventesParMois[$id] ?? [];

            $forecastMois = $this->forecastNextMonth($moisData, $recentVendu, $totalVendu);
            $forecastAnnee = $this->forecastNextYear($moisData, $totalVendu);
            $row['forecast_next_month'] = $forecastMois;
            $row['forecast_next_year'] = $forecastAnnee;
            $row['forecast_source'] = $moisData !== [] ? 'mensuel' : 'total_reel';
            $totalVentesPrevuMois += $forecastMois;
            $caEstimeAnnee += $forecastAnnee * $prix;

            $stockActuel = $produit->getQuantite();
            $row['stock_to_order'] = max(0, $forecastMois - $stockActuel);
            if ($forecastMois <= 0 && $stockActuel > 0) {
                $row['stock_to_order'] = 0;
            }

            $trend = $this->computeTrend($id);
            $row['trend'] = $trend['label'];
            $row['trend_detail'] = $trend['detail'];

            $row['price_suggestion'] = $this->priceSuggestion($row['label'], $prix);
            $row['strategic'] = $this->strategicLabel($row['label'], $trend['label'], $row);

            if ($row['strategic'] === 'star') {
                $byStrategic['star'][] = $row;
            } elseif ($row['strategic'] === 'a_risque') {
                $byStrategic['a_risque'][] = $row;
            } elseif ($row['strategic'] === 'a_relancer') {
                $byStrategic['a_relancer'][] = $row;
            } elseif ($row['strategic'] === 'a_supprimer') {
                $byStrategic['a_supprimer'][] = $row;
            }
        }
        unset($row);

        $byLabel = [
            'forte_demande' => array_filter($predictions, fn ($r) => $r['label'] === 'forte_demande'),
            'potentiel_moyen' => array_filter($predictions, fn ($r) => $r['label'] === 'potentiel_moyen'),
            'faible_rotation' => array_filter($predictions, fn ($r) => $r['label'] === 'faible_rotation'),
        ];

        return [
            'predictions' => $predictions,
            'byLabel' => $byLabel,
            'byStrategic' => $byStrategic,
            'ca_estime_annee' => round($caEstimeAnnee, 2),
            'annee_cible' => $anneeCible,
            'total_ventes_prevu_mois' => $totalVentesPrevuMois,
            'mois_cible' => $moisProchain,
        ];
    }

    /**
     * @param array<int, array<string, int>> $a
     * @param array<int, array<string, int>> $b
     * @return array<int, array<string, int>>
     */
    private function mergeMonthly(array $a, array $b): array
    {
        $ids = array_unique(array_merge(array_keys($a), array_keys($b)));
        $out = [];
        foreach ($ids as $id) {
            $moisA = $a[$id] ?? [];
            $moisB = $b[$id] ?? [];
            $allMois = array_unique(array_merge(array_keys($moisA), array_keys($moisB)));
            $out[$id] = [];
            foreach ($allMois as $m) {
                $out[$id][$m] = ($moisA[$m] ?? 0) + ($moisB[$m] ?? 0);
            }
            ksort($out[$id]);
        }
        return $out;
    }

    /**
     * Prévision mois prochain : à partir des ventes par mois si disponibles, sinon à partir des ventes 90 derniers jours (≈ 3 mois).
     *
     * @param array<string, int> $moisData
     */
    private function forecastNextMonth(array $moisData, int $recentVendu = 0, int $totalVendu = 0): int
    {
        if ($moisData !== []) {
            $vals = array_values($moisData);
            $n = min(6, count($vals));
            $recent = array_slice($vals, -$n);
            return (int) round(array_sum($recent) / count($recent));
        }
        // Pas de détail mensuel : estimer à partir des ventes des 90 derniers jours (≈ 1/3 mois → ×1 pour 1 mois)
        if ($recentVendu > 0) {
            return (int) round($recentVendu / 3.0);
        }
        if ($totalVendu > 0) {
            return (int) max(1, round($totalVendu / 12));
        }
        return 0;
    }

    /**
     * Prévision année : à partir des ventes par mois si disponibles, sinon à partir du total vendu réel.
     *
     * @param array<string, int> $moisData
     */
    private function forecastNextYear(array $moisData, int $totalVendu = 0): int
    {
        if ($moisData !== []) {
            $moyenneMensuelle = array_sum($moisData) / max(1, count($moisData));
            return (int) round($moyenneMensuelle * 12);
        }
        return $totalVendu;
    }

    private function computeTrend(int $productId): array
    {
        $recent = $this->mergeStats(
            $this->ligneCommandeRepository->getQuantitesVenduesParProduitDerniersJours(self::DAYS_RECENT),
            $this->orderItemRepository->getQuantitesVenduesParProduitDerniersJours(self::DAYS_RECENT)
        );
        $previous = $this->ventesDerniersJours($productId, self::DAYS_PREVIOUS, self::DAYS_RECENT);
        $vRecent = $recent[$productId] ?? 0;
        $vPrevious = $previous;
        if ($vPrevious === 0) {
            if ($vRecent > 0) {
                return ['label' => 'en_croissance', 'detail' => 'Ventes en hausse (pas de ventes sur la période précédente).'];
            }
            return ['label' => 'stable', 'detail' => 'Aucune vente récente.'];
        }
        $evolution = (($vRecent - $vPrevious) / $vPrevious) * 100;
        if ($evolution >= 20) {
            return ['label' => 'en_croissance', 'detail' => sprintf('+%.0f%% sur les 90 derniers jours vs période précédente.', $evolution)];
        }
        if ($evolution <= -20) {
            return ['label' => 'en_declin', 'detail' => sprintf('%.0f%% sur les 90 derniers jours vs période précédente.', $evolution)];
        }
        return ['label' => 'stable', 'detail' => 'Tendance stable.'];
    }

    private function ventesDerniersJours(int $productId, int $daysStart, int $daysEnd): int
    {
        $since = (new \DateTimeImmutable())->modify("-{$daysStart} days");
        $until = (new \DateTimeImmutable())->modify("-{$daysEnd} days");
        $conn = $this->ligneCommandeRepository->getEntityManager()->getConnection();
        $sql = "SELECT COALESCE(SUM(l.quantite), 0) AS total FROM ligne_commande l INNER JOIN commande c ON c.id = l.commande_id WHERE l.produit_id = :pid AND c.date_creation >= :since AND c.date_creation < :until";
        $stmt = $conn->executeQuery($sql, ['pid' => $productId, 'since' => $since->format('Y-m-d'), 'until' => $until->format('Y-m-d')]);
        $t1 = (int) $stmt->fetchOne();
        $sql2 = "SELECT COALESCE(SUM(oi.quantite), 0) AS total FROM order_item oi INNER JOIN `order` o ON o.id = oi.order_id WHERE oi.produit_id = :pid AND o.created_at >= :since AND o.created_at < :until";
        $stmt2 = $conn->executeQuery($sql2, ['pid' => $productId, 'since' => $since->format('Y-m-d'), 'until' => $until->format('Y-m-d')]);
        $t2 = (int) $stmt2->fetchOne();
        return $t1 + $t2;
    }

    private function priceSuggestion(string $label, float $prix): array
    {
        $formatPrix = fn (float $p) => number_format($p, 2, ',', ' ') . ' DT';
        switch ($label) {
            case 'forte_demande':
                $nouveau = $prix * 1.05;
                return ['action' => 'augmenter', 'percent' => 5, 'texte' => 'Prix conseillé +5% : ' . $formatPrix($nouveau), 'prix_conseille' => $nouveau];
            case 'faible_rotation':
                $nouveau = $prix * 0.90;
                return ['action' => 'promo', 'percent' => -10, 'texte' => 'Promotion recommandée -10% : ' . $formatPrix($nouveau), 'prix_conseille' => $nouveau];
            case 'potentiel_moyen':
            default:
                return ['action' => 'maintenir', 'percent' => 0, 'texte' => 'Maintenir le prix actuel.', 'prix_conseille' => $prix];
        }
    }

    private function strategicLabel(string $label, string $trend, array $row): string
    {
        if ($label === 'forte_demande' && $trend === 'en_croissance') {
            return 'star';
        }
        $totalVendu = $row['total_vendu'] ?? 0;
        $stock = $row['produit']->getQuantite();
        if ($label === 'faible_rotation' && $totalVendu === 0 && $stock > 10) {
            return 'a_supprimer';
        }
        if ($label === 'faible_rotation' && ($trend === 'en_declin' || $totalVendu === 0)) {
            return 'a_risque';
        }
        if (($label === 'faible_rotation' || $label === 'potentiel_moyen') && ($trend === 'en_declin' || ($row['recent_vendu'] ?? 0) === 0)) {
            return 'a_relancer';
        }
        return '';
    }

    /**
     * Prédit pour chaque produit un segment : forte_demande | faible_rotation | potentiel_moyen.
     *
     * @return array<int, array{produit: Produit, label: string, recommandation: string, total_vendu: int, recent_vendu: int, nb_commandes: int}>
     */
    public function predict(): array
    {
        $products = $this->produitRepository->findBy([], ['nom' => 'ASC']);
        if (count($products) === 0) {
            return [];
        }

        $byId = [];
        foreach ($products as $p) {
            $byId[$p->getId()] = $p;
        }

        $totalVendu = $this->mergeStats(
            $this->ligneCommandeRepository->getQuantitesVenduesParProduit(),
            $this->orderItemRepository->getQuantitesVenduesParProduit()
        );
        $recentVendu = $this->mergeStats(
            $this->ligneCommandeRepository->getQuantitesVenduesParProduitDerniersJours(self::DAYS_RECENT),
            $this->orderItemRepository->getQuantitesVenduesParProduitDerniersJours(self::DAYS_RECENT)
        );
        $nbCommandes = $this->mergeStats(
            $this->ligneCommandeRepository->getNombreCommandesParProduit(),
            $this->orderItemRepository->getNombreCommandesParProduit()
        );

        $maxPrix = 0.0;
        foreach ($products as $p) {
            $prix = (float) $p->getPrix();
            if ($prix > $maxPrix) {
                $maxPrix = $prix;
            }
        }
        if ($maxPrix <= 0) {
            $maxPrix = 1.0;
        }

        $samples = [];
        $productIds = [];
        foreach ($products as $p) {
            $id = $p->getId();
            $prix = (float) $p->getPrix();
            $samples[$id] = [
                (float) ($totalVendu[$id] ?? 0),
                (float) ($recentVendu[$id] ?? 0),
                (float) $p->getQuantite(),
                $prix / $maxPrix,
                (float) ($nbCommandes[$id] ?? 0),
            ];
            $productIds[] = $id;
        }

        if (count($samples) < 3) {
            return $this->fallbackLabels($byId, $totalVendu, $recentVendu, $nbCommandes);
        }

        $matrix = array_values($samples);
        $normalizer = new PhpmlNormalizer(self::NORM_STD);
        $normalizer->fit($matrix);
        $normalizer->transform($matrix);

        $samplesNormalized = [];
        $i = 0;
        foreach ($productIds as $id) {
            $samplesNormalized[$id] = $matrix[$i];
            $i++;
        }

        $kmeans = new KMeans(3, KMeans::INIT_KMEANS_PLUS_PLUS);
        $clusters = $kmeans->cluster($samplesNormalized);

        $productToCluster = [];
        $clusterMeans = [0 => [], 1 => [], 2 => []];
        foreach ($clusters as $clusterIndex => $points) {
            foreach ($points as $prodId => $coords) {
                $productToCluster[(int) $prodId] = $clusterIndex;
                $clusterMeans[$clusterIndex][] = $coords[0];
            }
        }

        $meanByCluster = [];
        foreach ($clusterMeans as $idx => $vals) {
            $meanByCluster[$idx] = count($vals) > 0 ? array_sum($vals) / count($vals) : 0.0;
        }
        arsort($meanByCluster, SORT_NUMERIC);
        $clusterToLabel = [];
        $labels = ['forte_demande', 'potentiel_moyen', 'faible_rotation'];
        $pos = 0;
        foreach (array_keys($meanByCluster) as $c) {
            $clusterToLabel[$c] = $labels[$pos];
            $pos++;
        }

        $result = [];
        foreach ($products as $p) {
            $id = $p->getId();
            $clusterIndex = $productToCluster[$id] ?? 1;
            $label = $clusterToLabel[$clusterIndex] ?? 'potentiel_moyen';
            $result[$id] = [
                'produit' => $p,
                'label' => $label,
                'recommandation' => $this->recommandation($label, $p, $totalVendu[$id] ?? 0, $recentVendu[$id] ?? 0),
                'total_vendu' => $totalVendu[$id] ?? 0,
                'recent_vendu' => $recentVendu[$id] ?? 0,
                'nb_commandes' => $nbCommandes[$id] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int> $a
     * @param array<int, int> $b
     * @return array<int, int>
     */
    private function mergeStats(array $a, array $b): array
    {
        $ids = array_unique(array_merge(array_keys($a), array_keys($b)));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ($a[$id] ?? 0) + ($b[$id] ?? 0);
        }
        return $out;
    }

    /**
     * @param array<int, Produit> $byId
     * @param array<int, int> $totalVendu
     * @param array<int, int> $recentVendu
     * @param array<int, int> $nbCommandes
     * @return array<int, array{produit: Produit, label: string, recommandation: string, total_vendu: int, recent_vendu: int, nb_commandes: int}>
     */
    private function fallbackLabels(array $byId, array $totalVendu, array $recentVendu, array $nbCommandes): array
    {
        $result = [];
        foreach ($byId as $id => $p) {
            $total = $totalVendu[$id] ?? 0;
            $recent = $recentVendu[$id] ?? 0;
            if ($total >= 5 || $recent >= 2) {
                $label = 'forte_demande';
            } elseif ($total === 0 && $recent === 0) {
                $label = 'faible_rotation';
            } else {
                $label = 'potentiel_moyen';
            }
            $result[$id] = [
                'produit' => $p,
                'label' => $label,
                'recommandation' => $this->recommandation($label, $p, $total, $recent),
                'total_vendu' => $total,
                'recent_vendu' => $recent,
                'nb_commandes' => $nbCommandes[$id] ?? 0,
            ];
        }
        return $result;
    }

    private function recommandation(string $label, Produit $p, int $totalVendu, int $recentVendu): string
    {
        $qte = $p->getQuantite();
        switch ($label) {
            case 'forte_demande':
                if ($qte < 5) {
                    return 'Réapprovisionner rapidement pour éviter les ruptures.';
                }
                return 'Maintenir le stock et la visibilité.';
            case 'faible_rotation':
                if ($totalVendu === 0) {
                    return 'Envisager une promo ou une mise en avant pour tester la demande.';
                }
                return 'Éviter le surstock ; privilégier des petites quantités.';
            case 'potentiel_moyen':
            default:
                if ($recentVendu > 0 && $qte > 10) {
                    return 'Stable ; surveiller les tendances.';
                }
                return 'Promotion ou mise en avant possible pour augmenter les ventes.';
        }
    }
}
