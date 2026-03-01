<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DemandeProduit;
use App\Enum\Categorie;
use Psr\Log\LoggerInterface;

/**
 * Enrichit une DemandeProduit avec données Amazon (API) et IA pour produire
 * une fiche produit complète prête à être publiée.
 */
final class DemandeProduitEnrichmentService
{
    private const SYSTEM_MERGE = <<<'PROMPT'
Tu es un assistant e-commerce AutiCare. Tu dois fusionner deux sources de données produit :

1) FICHE CHATBOT : données extraites des questions du client (nom, description, catégorie, prix estimé).
2) DONNÉES AMAZON : résultat d'une recherche API (nom, description, prix, image).

Objectif : produire la meilleure fiche produit possible pour notre catalogue.
- Si Amazon a des données complètes et pertinentes, privilégie-les (nom pro, description détaillée).
- Si la fiche chatbot est plus adaptée au besoin client (ex. contexte autisme), fusionne.
- Le nom doit être clair et vendeur.
- La description doit être en français, professionnelle, adaptée aux familles avec enfants autistes.
- Le prix : utilise celui d'Amazon si réaliste, sinon la fiche. Convertis en dinars tunisiens si besoin (1 EUR ≈ 3.3 TND).
- Catégorie : sensoriels, bruit_et_environnement, education_apprentissage, communication_langage, jeux_therapeutiques_developpement, bien_etre_relaxation, vie_quotidienne.

Réponds UNIQUEMENT en JSON : {"nom": "...", "description": "...", "categorie": "xxx", "prix": float}
PROMPT;

    public function __construct(
        private readonly ProductExternalApiService $externalApiService,
        private readonly LoggerInterface $logger,
        private readonly ?string $openaiApiKey = null,
    ) {
    }

    /**
     * Enrichit une demande produit avec API Amazon + IA.
     *
     * @return array{nom: string, description: string, categorie: string, prix: float, image_url: ?string, source: string}
     */
    public function enrich(DemandeProduit $demande): array
    {
        $fiche = [
            'nom' => $demande->getNom(),
            'description' => $demande->getDescription() ?? '',
            'categorie' => $demande->getCategorie()?->value ?? 'vie_quotidienne',
            'prix' => $demande->getPrixEstime() ?? 50.0,
        ];

        $demandeClient = $demande->getDemandeClient() ?? '';
        $donneesExt = $demande->getDonneesExternes();
        $imageUrl = is_array($donneesExt) ? ($donneesExt['image_url'] ?? null) : null;

        // 1) Chercher sur Amazon (demande client ou nom proposé)
        $apiProduct = null;
        if ($this->externalApiService->isConfigured()) {
            $keyword = trim($demandeClient) !== '' ? $demandeClient : $fiche['nom'];
            $apiProduct = $this->externalApiService->searchAndGetBestProduct($keyword);
        }

        if ($apiProduct !== null) {
            $imageUrl = $apiProduct['image_url'] ?? $imageUrl;
            // 2) Fusionner avec IA si disponible
            if ($this->openaiApiKey) {
                $merged = $this->mergeWithAi($fiche, $apiProduct, $demandeClient);
                return [
                    'nom' => $merged['nom'],
                    'description' => $merged['description'],
                    'categorie' => $merged['categorie'],
                    'prix' => $merged['prix'],
                    'image_url' => $imageUrl,
                    'source' => 'amazon_ia',
                ];
            }
            // Sans IA : prendre Amazon en priorité
            return [
                'nom' => $apiProduct['nom'] ?: $fiche['nom'],
                'description' => $apiProduct['description'] ?: $fiche['description'],
                'categorie' => $this->guessCategorie($apiProduct['nom'] . ' ' . ($apiProduct['description'] ?? '')),
                'prix' => $apiProduct['prix'] > 0 ? $apiProduct['prix'] : $fiche['prix'],
                'image_url' => $imageUrl,
                'source' => 'amazon',
            ];
        }

        // Pas d'API : utiliser la fiche, améliorer avec IA si dispo
        if ($this->openaiApiKey) {
            $improved = $this->improveFicheWithAi($fiche, $demandeClient);
            return [
                'nom' => $improved['nom'],
                'description' => $improved['description'],
                'categorie' => $improved['categorie'],
                'prix' => $improved['prix'],
                'image_url' => $imageUrl,
                'source' => 'fiche_ia',
            ];
        }

        return [
            'nom' => $fiche['nom'],
            'description' => $fiche['description'] ?: "Produit adapté à : {$demandeClient}",
            'categorie' => $fiche['categorie'],
            'prix' => $fiche['prix'],
            'image_url' => $imageUrl,
            'source' => 'fiche',
        ];
    }

    /**
     * @param array{nom: string, description: string, categorie: string, prix: float} $fiche
     * @param array{nom: string, description: ?string, prix: float} $apiProduct
     *
     * @return array{nom: string, description: string, categorie: string, prix: float}
     */
    private function mergeWithAi(array $fiche, array $apiProduct, string $demandeClient): array
    {
        try {
            $client = \OpenAI::client($this->openaiApiKey);
            $userContent = "FICHE CHATBOT:\n" . json_encode($fiche, JSON_UNESCAPED_UNICODE)
                . "\n\nDONNÉES AMAZON:\n" . json_encode($apiProduct, JSON_UNESCAPED_UNICODE)
                . "\n\nDemande client: " . $demandeClient;

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_MERGE],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'temperature' => 0.3,
            ]);
            $content = trim($response->choices[0]->message->content ?? '');
            $content = preg_replace('/^```json\s*|\s*```$/s', '', $content);
            $data = json_decode($content, true);
            if ($data && isset($data['nom'], $data['description'], $data['categorie'], $data['prix'])) {
                return [
                    'nom' => $data['nom'],
                    'description' => $data['description'],
                    'categorie' => $this->normalizeCategorie($data['categorie']),
                    'prix' => max(0.01, (float) $data['prix']),
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('DemandeProduitEnrichmentService mergeWithAi', ['error' => $e->getMessage()]);
        }
        return [
            'nom' => $apiProduct['nom'] ?: $fiche['nom'],
            'description' => $apiProduct['description'] ?: $fiche['description'],
            'categorie' => $this->guessCategorie($apiProduct['nom'] ?? ''),
            'prix' => $apiProduct['prix'] > 0 ? $apiProduct['prix'] : $fiche['prix'],
        ];
    }

    /**
     * @param array{nom: string, description: string, categorie: string, prix: float} $fiche
     *
     * @return array{nom: string, description: string, categorie: string, prix: float}
     */
    private function improveFicheWithAi(array $fiche, string $demandeClient): array
    {
        try {
            $client = \OpenAI::client($this->openaiApiKey);
            $userContent = "FICHE CHATBOT:\n" . json_encode($fiche, JSON_UNESCAPED_UNICODE)
                . "\n\nDemande client: " . $demandeClient
                . "\n\nAméliore le nom et la description pour un catalogue e-commerce AutiCare (produits pour autisme).";

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu améliores une fiche produit. Réponds UNIQUEMENT en JSON : {"nom": "...", "description": "...", "categorie": "xxx", "prix": float}'],
                    ['role' => 'user', 'content' => $userContent],
                ],
                'temperature' => 0.4,
            ]);
            $content = trim($response->choices[0]->message->content ?? '');
            $content = preg_replace('/^```json\s*|\s*```$/s', '', $content);
            $data = json_decode($content, true);
            if ($data && isset($data['nom'])) {
                return [
                    'nom' => $data['nom'],
                    'description' => $data['description'] ?? $fiche['description'],
                    'categorie' => $this->normalizeCategorie($data['categorie'] ?? $fiche['categorie']),
                    'prix' => max(0.01, (float) ($data['prix'] ?? $fiche['prix'])),
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('DemandeProduitEnrichmentService improveFicheWithAi', ['error' => $e->getMessage()]);
        }
        return $fiche;
    }

    private function guessCategorie(string $text): string
    {
        $t = mb_strtolower($text);
        $map = [
            'sensoriel' => 'sensoriels',
            'calme' => 'bien_etre_relaxation',
            'relaxation' => 'bien_etre_relaxation',
            'jeu' => 'jeux_therapeutiques_developpement',
            'communication' => 'communication_langage',
            'education' => 'education_apprentissage',
            'bruit' => 'bruit_et_environnement',
            'casque' => 'bruit_et_environnement',
        ];
        foreach ($map as $kw => $cat) {
            if (str_contains($t, $kw)) {
                return $cat;
            }
        }
        return 'vie_quotidienne';
    }

    private function normalizeCategorie(string $value): string
    {
        $valid = [
            'sensoriels', 'bruit_et_environnement', 'education_apprentissage',
            'communication_langage', 'jeux_therapeutiques_developpement',
            'bien_etre_relaxation', 'vie_quotidienne',
        ];
        $v = strtolower(trim(preg_replace('/\s+/', '_', $value)));
        return in_array($v, $valid, true) ? $v : 'vie_quotidienne';
    }
}