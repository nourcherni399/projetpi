<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour rechercher les prix et images de produits sur différents sites e-commerce.
 * Sources: Google Shopping (SerpAPI), Amazon (RapidAPI), Jumia
 */
final class ProductPriceSearchService
{
    private const CURRENCY_TND_RATE = 3.1; // 1 USD ≈ 3.1 TND, 1 EUR ≈ 3.4 TND

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly SluggerInterface $slugger,
        private readonly ?string $serpApiKey = null,
        private readonly ?string $rapidApiKey = null,
        private readonly ?string $pexelsApiKey = null,
        private readonly ?string $groqApiKey = null,
        private readonly string $uploadsDirectory = 'public/uploads/produits',
    ) {
    }

    /**
     * Recherche le produit sur plusieurs sites et retourne les résultats avec prix et images.
     * 
     * @return array{
     *   found: bool,
     *   results: array<array{
     *     source: string,
     *     name: string,
     *     price: float,
     *     currency: string,
     *     price_tnd: float,
     *     image_url: ?string,
     *     product_url: ?string
     *   }>,
     *   best_price: ?array,
     *   suggested_price_tnd: float,
     *   image_path: ?string
     * }
     */
    public function searchProduct(string $productName): array
    {
        $results = [];
        
        // 1. Recherche sur Google Shopping via SerpAPI
        $googleResults = $this->searchGoogleShopping($productName);
        $results = array_merge($results, $googleResults);
        
        // 2. Recherche sur Amazon via RapidAPI
        $amazonResults = $this->searchAmazon($productName);
        $results = array_merge($results, $amazonResults);
        
        // 3. Recherche sur Jumia Tunisie (scraping simple)
        $jumiaResults = $this->searchJumia($productName);
        $results = array_merge($results, $jumiaResults);
        
        // 4. Recherche sur Pexels (images gratuites uniquement — pas de prix)
        $pexelsResults = $this->searchPexels($productName);
        $allResultsForImages = array_merge($results, $pexelsResults);

        // Résultats avec prix réel uniquement (exclure Pexels qui renvoie toujours 0)
        $resultsWithPrice = array_filter($results, fn($r) => ($r['price_tnd'] ?? 0) > 0);

        if (empty($resultsWithPrice)) {
            // Fallback : estimation du prix par l’IA (Groq) pour afficher un vrai prix
            $aiPrice = $this->estimatePriceWithAI($productName);
            if ($aiPrice !== null && $aiPrice > 0) {
                $imagePath = null;
                foreach ($allResultsForImages as $result) {
                    if (!empty($result['image_url'])) {
                        $imagePath = $this->downloadProductImage($result['image_url'], $productName);
                        if ($imagePath) {
                            break;
                        }
                    }
                }
                $bestPrice = [
                    'source' => 'Estimation IA (Groq)',
                    'name' => $productName,
                    'price' => $aiPrice,
                    'currency' => 'TND',
                    'price_tnd' => $aiPrice,
                    'image_url' => null,
                    'product_url' => null,
                ];
                return [
                    'found' => true,
                    'results' => [$bestPrice],
                    'best_price' => $bestPrice,
                    'suggested_price_tnd' => round($aiPrice, 2),
                    'image_path' => $imagePath,
                ];
            }
            // Aucun prix (ni APIs ni IA) : image Pexels uniquement
            $imagePath = null;
            foreach ($allResultsForImages as $result) {
                if (!empty($result['image_url'])) {
                    $imagePath = $this->downloadProductImage($result['image_url'], $productName);
                    if ($imagePath) {
                        break;
                    }
                }
            }
            return [
                'found' => false,
                'results' => [],
                'best_price' => null,
                'suggested_price_tnd' => 0,
                'image_path' => $imagePath,
            ];
        }

        // Trier par prix TND (sources avec prix uniquement)
        usort($resultsWithPrice, fn($a, $b) => $a['price_tnd'] <=> $b['price_tnd']);
        $bestPrice = $resultsWithPrice[0];

        // Télécharger la meilleure image (toutes sources, y compris Pexels)
        $imagePath = null;
        foreach ($allResultsForImages as $result) {
            if (!empty($result['image_url'])) {
                $imagePath = $this->downloadProductImage($result['image_url'], $productName);
                if ($imagePath) {
                    break;
                }
            }
        }

        // Prix suggéré = moyenne des 3 premiers résultats avec prix
        $topPrices = array_slice(array_column($resultsWithPrice, 'price_tnd'), 0, 3);
        $suggestedPrice = round(array_sum($topPrices) / count($topPrices), 2);

        return [
            'found' => true,
            'results' => $resultsWithPrice,
            'best_price' => $bestPrice,
            'suggested_price_tnd' => $suggestedPrice,
            'image_path' => $imagePath,
        ];
    }

    /**
     * Formate les résultats pour le chatbot
     */
    public function formatForChatbot(array $searchResults): string
    {
        if (!$searchResults['found'] || empty($searchResults['results'])) {
            return "Je n'ai pas trouvé ce produit sur les sites de vente en ligne.";
        }

        $message = "🔍 J'ai trouvé ce produit sur plusieurs sites :\n\n";
        
        $shown = 0;
        foreach ($searchResults['results'] as $result) {
            if ($shown >= 3) break;
            $source = $result['source'];
            $price = number_format($result['price_tnd'], 2);
            $message .= "• **{$source}** : {$price} DT\n";
            $shown++;
        }

        $suggested = number_format($searchResults['suggested_price_tnd'], 2);
        $message .= "\n💡 Prix suggéré : **{$suggested} DT**";

        return $message;
    }

    /**
     * Recherche sur Google Shopping via SerpAPI
     */
    private function searchGoogleShopping(string $query): array
    {
        if (empty($this->serpApiKey)) {
            return [];
        }

        try {
            $this->logger->info('SerpAPI search', ['query' => $query, 'key_present' => !empty($this->serpApiKey)]);
            
            $response = $this->httpClient->request('GET', 'https://serpapi.com/search.json', [
                'query' => [
                    'engine' => 'google_shopping',
                    'q' => $query,
                    'location' => 'France',
                    'hl' => 'fr',
                    'gl' => 'fr',
                    'api_key' => $this->serpApiKey,
                ],
                'timeout' => 20,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray(false);
            $products = $data['shopping_results'] ?? [];
            $results = [];
            
            $this->logger->info('SerpAPI response', ['product_count' => count($products)]);

            foreach (array_slice($products, 0, 5) as $product) {
                $price = $this->extractPrice($product['extracted_price'] ?? $product['price'] ?? '0');
                $currency = $this->detectCurrency($product['price'] ?? '');
                
                $results[] = [
                    'source' => 'Google Shopping',
                    'name' => $product['title'] ?? $query,
                    'price' => $price,
                    'currency' => $currency,
                    'price_tnd' => $this->convertToTND($price, $currency),
                    'image_url' => $product['thumbnail'] ?? null,
                    'product_url' => $product['link'] ?? null,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur Google Shopping', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Recherche sur Amazon via RapidAPI
     */
    private function searchAmazon(string $query): array
    {
        if (empty($this->rapidApiKey)) {
            return [];
        }

        try {
            $host = 'real-time-amazon-data.p.rapidapi.com';
            $response = $this->httpClient->request('GET', "https://{$host}/search", [
                'query' => [
                    'query' => $query,
                    'page' => '1',
                    'country' => 'FR', // Amazon France (plus proche de la Tunisie)
                    'sort_by' => 'RELEVANCE',
                ],
                'headers' => [
                    'x-rapidapi-key' => $this->rapidApiKey,
                    'x-rapidapi-host' => $host,
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray(false);
            $products = $data['data']['products'] ?? [];
            $results = [];

            foreach (array_slice($products, 0, 5) as $product) {
                $priceRaw = $product['product_price'] ?? '0';
                $price = $this->extractPrice($priceRaw);
                
                if ($price <= 0) {
                    continue;
                }

                $results[] = [
                    'source' => 'Amazon',
                    'name' => $product['product_title'] ?? $query,
                    'price' => $price,
                    'currency' => 'EUR',
                    'price_tnd' => $this->convertToTND($price, 'EUR'),
                    'image_url' => $product['product_photo'] ?? $product['thumbnail'] ?? null,
                    'product_url' => $product['product_url'] ?? null,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur Amazon', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Recherche sur Jumia Tunisie (via API publique ou scraping léger)
     */
    private function searchJumia(string $query): array
    {
        try {
            // Jumia a une API de recherche accessible
            $searchUrl = 'https://www.jumia.com.tn/catalog/?' . http_build_query([
                'q' => $query,
            ]);

            $response = $this->httpClient->request('GET', $searchUrl, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'fr-FR,fr;q=0.9',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $html = $response->getContent();
            $results = [];

            // Parser le HTML pour extraire les produits
            // Structure Jumia: <article class="prd _fb" data-sku="...">
            if (preg_match_all('/<article[^>]*class="[^"]*prd[^"]*"[^>]*>.*?<\/article>/s', $html, $matches)) {
                foreach (array_slice($matches[0], 0, 5) as $articleHtml) {
                    $name = '';
                    $price = 0;
                    $imageUrl = null;
                    $productUrl = null;

                    // Extraire le nom
                    if (preg_match('/<h3[^>]*class="[^"]*name[^"]*"[^>]*>([^<]+)<\/h3>/i', $articleHtml, $m)) {
                        $name = trim(html_entity_decode($m[1]));
                    }

                    // Extraire le prix
                    if (preg_match('/data-price="([\d.]+)"/', $articleHtml, $m)) {
                        $price = (float) $m[1];
                    } elseif (preg_match('/<span[^>]*class="[^"]*prc[^"]*"[^>]*>([\d\s,.]+)\s*(?:DT|TND)?<\/span>/i', $articleHtml, $m)) {
                        $price = $this->extractPrice($m[1]);
                    }

                    // Extraire l'image
                    if (preg_match('/data-src="([^"]+)"/', $articleHtml, $m)) {
                        $imageUrl = $m[1];
                    } elseif (preg_match('/<img[^>]*src="([^"]+)"/', $articleHtml, $m)) {
                        $imageUrl = $m[1];
                    }

                    // Extraire l'URL du produit
                    if (preg_match('/<a[^>]*href="([^"]+)"[^>]*class="[^"]*core[^"]*"/', $articleHtml, $m)) {
                        $productUrl = 'https://www.jumia.com.tn' . $m[1];
                    }

                    if ($name && $price > 0) {
                        $results[] = [
                            'source' => 'Jumia Tunisie',
                            'name' => $name,
                            'price' => $price,
                            'currency' => 'TND',
                            'price_tnd' => $price, // Déjà en TND
                            'image_url' => $imageUrl,
                            'product_url' => $productUrl,
                        ];
                    }
                }
            }

            return $results;
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur Jumia', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Recherche sur Pexels (images gratuites de qualité)
     */
    private function searchPexels(string $query): array
    {
        if (empty($this->pexelsApiKey)) {
            return [];
        }

        try {
            $this->logger->info('Pexels search', ['query' => $query]);
            
            $response = $this->httpClient->request('GET', 'https://api.pexels.com/v1/search', [
                'query' => [
                    'query' => $query,
                    'per_page' => 8,
                    'locale' => 'fr-FR',
                ],
                'headers' => [
                    'Authorization' => $this->pexelsApiKey,
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Pexels API error', ['status' => $response->getStatusCode()]);
                return [];
            }

            $data = $response->toArray(false);
            $photos = $data['photos'] ?? [];
            $results = [];
            
            $this->logger->info('Pexels response', ['photo_count' => count($photos)]);

            foreach ($photos as $photo) {
                $results[] = [
                    'source' => 'Pexels',
                    'name' => $photo['alt'] ?? $query,
                    'price' => 0,
                    'currency' => 'TND',
                    'price_tnd' => 0,
                    'image_url' => $photo['src']['medium'] ?? $photo['src']['small'] ?? null,
                    'product_url' => $photo['url'] ?? null,
                ];
            }

            return $results;
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur Pexels', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Télécharge l'image du produit et la sauvegarde localement
     */
    private function downloadProductImage(string $imageUrl, string $productName): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $imageUrl, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $content = $response->getContent();
            if (empty($content) || strlen($content) < 1000) {
                return null;
            }

            // Déterminer l'extension
            $contentType = $response->getHeaders()['content-type'][0] ?? '';
            $ext = 'jpg';
            if (str_contains($contentType, 'png')) {
                $ext = 'png';
            } elseif (str_contains($contentType, 'webp')) {
                $ext = 'webp';
            } elseif (str_contains($contentType, 'gif')) {
                $ext = 'gif';
            }

            $safeName = $this->slugger->slug($productName)->toString();
            $filename = $safeName . '-' . uniqid() . '.' . $ext;
            
            if (!is_dir($this->uploadsDirectory)) {
                mkdir($this->uploadsDirectory, 0755, true);
            }

            $path = $this->uploadsDirectory . '/' . $filename;
            $this->logger->info('Saving image', ['path' => $path, 'size' => strlen($content)]);
            
            file_put_contents($path, $content);

            return 'uploads/produits/' . $filename;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur téléchargement image', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return null;
        }
    }

    /**
     * Estime un prix en DT via l’IA Groq quand les APIs e-commerce ne renvoient rien.
     */
    private function estimatePriceWithAI(string $productQuery): ?float
    {
        if (empty($this->groqApiKey)) {
            return null;
        }

        try {
            $prompt = <<<PROMPT
Tu es un expert en prix pour la Tunisie. Donne UN SEUL nombre : le prix réaliste en dinars tunisiens (DT) pour ce produit vendu en Tunisie.
Produit : {$productQuery}
Réponds uniquement par un nombre décimal (exemple : 89.50 ou 120), sans texte ni devise.
PROMPT;

            $payload = [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 30,
                'temperature' => 0.3,
            ];

            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $data = $response->toArray(false);
            $text = trim($data['choices'][0]['message']['content'] ?? '');
            if ($text === '') {
                return null;
            }

            // Extraire un nombre du texte
            if (preg_match('/[\d]+[.,]?[\d]*/', $text, $m)) {
                $value = str_replace(',', '.', $m[0]);
                $price = (float) $value;
                if ($price > 0 && $price < 100000) {
                    $this->logger->info('Prix estimé par IA', ['query' => $productQuery, 'price_tnd' => $price]);
                    return round($price, 2);
                }
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('Erreur estimation prix IA', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractPrice(mixed $raw): float
    {
        if (is_numeric($raw)) {
            return (float) $raw;
        }

        if (!is_string($raw)) {
            return 0;
        }

        // Nettoyer le prix
        $clean = preg_replace('/[^\d.,]/', '', $raw);
        $clean = str_replace(',', '.', $clean);
        
        // Gérer les cas avec plusieurs points (ex: 1.299.00)
        if (substr_count($clean, '.') > 1) {
            $clean = str_replace('.', '', $clean);
            $clean = substr_replace($clean, '.', -2, 0);
        }

        return (float) $clean;
    }

    private function detectCurrency(string $priceString): string
    {
        $priceString = strtoupper($priceString);
        
        if (str_contains($priceString, 'TND') || str_contains($priceString, 'DT')) {
            return 'TND';
        }
        if (str_contains($priceString, '€') || str_contains($priceString, 'EUR')) {
            return 'EUR';
        }
        if (str_contains($priceString, '$') || str_contains($priceString, 'USD')) {
            return 'USD';
        }
        
        return 'EUR'; // Par défaut pour les sites européens
    }

    private function convertToTND(float $price, string $currency): float
    {
        return match ($currency) {
            'TND' => $price,
            'EUR' => round($price * 3.4, 2),  // 1 EUR ≈ 3.4 TND
            'USD' => round($price * 3.1, 2),  // 1 USD ≈ 3.1 TND
            default => round($price * 3.4, 2),
        };
    }
}