<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Produit;
use App\Repository\LigneCommandeRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProduitRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AIPredictionService
{
    private const DAYS_RECENT = 90;

    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly LigneCommandeRepository $ligneCommandeRepository,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $groqApiKey,
    ) {
    }

    public function fullReport(): array
    {
        $products = $this->produitRepository->findBy([], ['nom' => 'ASC']);
        if (count($products) === 0) {
            return $this->emptyReport();
        }

        $salesData = $this->collectSalesData($products);
        
        $aiAnalysis = $this->getAIAnalysis($salesData);
        
        $predictions = $this->buildPredictions($products, $salesData, $aiAnalysis);

        return $this->buildFullReport($predictions);
    }

    private function emptyReport(): array
    {
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

    private function collectSalesData(array $products): array
    {
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

        $data = [];
        foreach ($products as $p) {
            $id = $p->getId();
            $data[$id] = [
                'id' => $id,
                'nom' => $p->getNom(),
                'prix' => (float) $p->getPrix(),
                'stock' => $p->getQuantite(),
                'total_vendu' => $totalVendu[$id] ?? 0,
                'recent_vendu' => $recentVendu[$id] ?? 0,
                'nb_commandes' => $nbCommandes[$id] ?? 0,
            ];
        }

        return $data;
    }

    private function getAIAnalysis(array $salesData): array
    {
        if (empty($this->groqApiKey)) {
            $this->logger->warning('Groq API key not configured, using fallback analysis');
            return $this->fallbackAnalysis($salesData);
        }

        $prompt = $this->buildPrompt($salesData);

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un expert en analyse commerciale et prédiction de ventes. Analyse les données de vente des produits et fournis des recommandations stratégiques. Réponds UNIQUEMENT en JSON valide sans markdown.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 4000,
                ],
                'timeout' => 60,
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';
            
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*/', '', $content);
            $content = trim($content);
            
            $analysis = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to parse AI response: ' . json_last_error_msg());
                return $this->fallbackAnalysis($salesData);
            }

            return $analysis;

        } catch (\Exception $e) {
            $this->logger->error('AI API error: ' . $e->getMessage());
            return $this->fallbackAnalysis($salesData);
        }
    }

    private function buildPrompt(array $salesData): string
    {
        $productList = [];
        foreach ($salesData as $id => $data) {
            $productList[] = sprintf(
                "ID:%d | %s | Prix:%.2f DT | Stock:%d | Vendu total:%d | Vendu 90j:%d | Commandes:%d",
                $id,
                $data['nom'],
                $data['prix'],
                $data['stock'],
                $data['total_vendu'],
                $data['recent_vendu'],
                $data['nb_commandes']
            );
        }

        return <<<PROMPT
Analyse ces données de vente et génère des prédictions pour chaque produit.

DONNÉES DES PRODUITS:
%PRODUCTS%

Pour chaque produit, détermine:
1. segment: "forte_demande" (vend bien), "potentiel_moyen" (stable), ou "faible_rotation" (vend peu)
2. strategic: "star" (promouvoir), "a_risque" (surveiller), "a_relancer" (booster), "a_supprimer" (retirer), ou "" (aucun)
3. trend: "en_croissance", "stable", ou "en_declin"
4. forecast_month: prévision ventes mois prochain (nombre entier)
5. forecast_year: prévision ventes année (nombre entier)
6. price_action: "augmenter" (+5%), "maintenir", ou "promo" (-10%)
7. recommandation: conseil court (max 100 caractères)

Réponds en JSON avec cette structure exacte:
{
  "products": {
    "ID_PRODUIT": {
      "segment": "...",
      "strategic": "...",
      "trend": "...",
      "trend_detail": "...",
      "forecast_month": 0,
      "forecast_year": 0,
      "price_action": "...",
      "recommandation": "..."
    }
  },
  "summary": {
    "ca_estime": 0,
    "insight": "..."
  }
}
PROMPT;

        return str_replace('%PRODUCTS%', implode("\n", $productList), $prompt);
    }

    private function fallbackAnalysis(array $salesData): array
    {
        $products = [];
        foreach ($salesData as $id => $data) {
            $total = $data['total_vendu'];
            $recent = $data['recent_vendu'];
            $stock = $data['stock'];

            if ($total >= 5 || $recent >= 2) {
                $segment = 'forte_demande';
                $trend = $recent > ($total / 12) ? 'en_croissance' : 'stable';
                $strategic = $trend === 'en_croissance' ? 'star' : '';
                $priceAction = 'augmenter';
                $recommandation = 'Produit performant, maintenir la visibilité.';
            } elseif ($total === 0 && $recent === 0) {
                $segment = 'faible_rotation';
                $trend = 'stable';
                $strategic = $stock > 10 ? 'a_supprimer' : 'a_risque';
                $priceAction = 'promo';
                $recommandation = 'Envisager une promotion pour tester la demande.';
            } else {
                $segment = 'potentiel_moyen';
                $trend = 'stable';
                $strategic = $recent === 0 ? 'a_relancer' : '';
                $priceAction = 'maintenir';
                $recommandation = 'Surveiller les tendances et ajuster.';
            }

            $forecastMonth = $recent > 0 ? max(1, (int) round($recent / 3)) : ($total > 0 ? max(1, (int) round($total / 12)) : 0);
            $forecastYear = $forecastMonth * 12;

            $products[$id] = [
                'segment' => $segment,
                'strategic' => $strategic,
                'trend' => $trend,
                'trend_detail' => 'Analyse basée sur les données historiques.',
                'forecast_month' => $forecastMonth,
                'forecast_year' => $forecastYear,
                'price_action' => $priceAction,
                'recommandation' => $recommandation,
            ];
        }

        return ['products' => $products, 'summary' => ['ca_estime' => 0, 'insight' => 'Analyse automatique']];
    }

    private function buildPredictions(array $products, array $salesData, array $aiAnalysis): array
    {
        $predictions = [];
        $aiProducts = $aiAnalysis['products'] ?? [];

        foreach ($products as $p) {
            $id = $p->getId();
            $data = $salesData[$id] ?? [];
            $ai = $aiProducts[$id] ?? $aiProducts[(string)$id] ?? null;

            if (!$ai) {
                $ai = $this->fallbackAnalysis([$id => $data])['products'][$id] ?? [];
            }

            $prix = (float) $p->getPrix();
            $priceSuggestion = $this->buildPriceSuggestion($ai['price_action'] ?? 'maintenir', $prix);
            $forecastMonth = (int) ($ai['forecast_month'] ?? 0);
            $forecastYear = (int) ($ai['forecast_year'] ?? 0);
            $stock = $p->getQuantite();

            $predictions[$id] = [
                'produit' => $p,
                'label' => $ai['segment'] ?? 'potentiel_moyen',
                'strategic' => $ai['strategic'] ?? '',
                'trend' => $ai['trend'] ?? 'stable',
                'trend_detail' => $ai['trend_detail'] ?? '',
                'recommandation' => $ai['recommandation'] ?? '',
                'total_vendu' => $data['total_vendu'] ?? 0,
                'recent_vendu' => $data['recent_vendu'] ?? 0,
                'nb_commandes' => $data['nb_commandes'] ?? 0,
                'forecast_next_month' => $forecastMonth,
                'forecast_next_year' => $forecastYear,
                'price_suggestion' => $priceSuggestion,
                'stock_to_order' => max(0, $forecastMonth - $stock),
            ];
        }

        return $predictions;
    }

    private function buildPriceSuggestion(string $action, float $prix): array
    {
        $formatPrix = fn (float $p) => number_format($p, 2, ',', ' ') . ' DT';

        return match ($action) {
            'augmenter' => [
                'action' => 'augmenter',
                'percent' => 5,
                'texte' => 'Prix conseillé +5% : ' . $formatPrix($prix * 1.05),
                'prix_conseille' => $prix * 1.05,
            ],
            'promo' => [
                'action' => 'promo',
                'percent' => -10,
                'texte' => 'Promotion recommandée -10% : ' . $formatPrix($prix * 0.90),
                'prix_conseille' => $prix * 0.90,
            ],
            default => [
                'action' => 'maintenir',
                'percent' => 0,
                'texte' => 'Maintenir le prix actuel.',
                'prix_conseille' => $prix,
            ],
        };
    }

    private function buildFullReport(array $predictions): array
    {
        $now = new \DateTimeImmutable();
        $moisProchain = $now->modify('+1 month')->format('Y-m');
        $anneeCible = (int) $now->format('Y') + 1;

        $totalVentesPrevuMois = 0;
        $caEstimeAnnee = 0.0;

        $byStrategic = ['star' => [], 'a_risque' => [], 'a_relancer' => [], 'a_supprimer' => []];
        $byLabel = ['forte_demande' => [], 'potentiel_moyen' => [], 'faible_rotation' => []];

        foreach ($predictions as $id => &$row) {
            $prix = (float) $row['produit']->getPrix();
            $totalVentesPrevuMois += $row['forecast_next_month'];
            $caEstimeAnnee += $row['forecast_next_year'] * $prix;

            $label = $row['label'];
            if (isset($byLabel[$label])) {
                $byLabel[$label][$id] = $row;
            }

            $strategic = $row['strategic'];
            if ($strategic && isset($byStrategic[$strategic])) {
                $byStrategic[$strategic][] = $row;
            }
        }
        unset($row);

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

    private function mergeStats(array $a, array $b): array
    {
        $ids = array_unique(array_merge(array_keys($a), array_keys($b)));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ($a[$id] ?? 0) + ($b[$id] ?? 0);
        }
        return $out;
    }
}