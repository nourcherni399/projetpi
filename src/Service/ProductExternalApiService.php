<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour enrichir les fiches produit via APIs externes.
 * - Pexels (gratuit) : PEXELS_API_KEY - photos libres par mot-clé
 * - Amazon (payant) : RAPIDAPI_KEY
 */
final class ProductExternalApiService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $rapidApiKey = null,
        private readonly ?string $pexelsApiKey = null,
        private readonly ?string $uploadsDirectory = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function isPexelsConfigured(): bool
    {
        return $this->pexelsApiKey !== null && $this->pexelsApiKey !== '';
    }

    /**
     * Trouve une image correspondant au prompt via Pexels.
     * Retourne le chemin local de l'image ou null.
     */
    public function generateImageAI(string $prompt): ?string
    {
        if (!$this->isPexelsConfigured()) {
            return null;
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            $prompt = 'product';
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.pexels.com/v1/search', [
                'query' => ['query' => $prompt, 'per_page' => '1', 'orientation' => 'square'],
                'headers' => ['Authorization' => $this->pexelsApiKey],
                'timeout' => 30,
            ]);
            
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            
            $data = $response->toArray(false);
            $photos = $data['photos'] ?? [];
            
            if (empty($photos) || !isset($photos[0]['src'])) {
                return null;
            }
            
            $imageUrl = $photos[0]['src']['large'] ?? $photos[0]['src']['original'] ?? null;
            
            if (!$imageUrl || !is_string($imageUrl)) {
                return null;
            }

            $imageResponse = $this->httpClient->request('GET', $imageUrl, ['timeout' => 30]);
            if ($imageResponse->getStatusCode() !== 200) {
                return null;
            }

            $imageData = $imageResponse->getContent();
            if (empty($imageData) || strlen($imageData) < 1000) {
                return null;
            }

            $ext = 'jpg';
            if (preg_match('/\.(png|webp|gif)$/i', $imageUrl, $m)) {
                $ext = strtolower($m[1]);
            }

            $filename = 'pexels-' . uniqid() . '.' . $ext;
            $directory = $this->uploadsDirectory ?? 'public/uploads/produits';
            
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            $path = $directory . '/' . $filename;
            file_put_contents($path, $imageData);
            
            return 'uploads/produits/' . $filename;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Recherche des produits sur Amazon / API externe.
     */
    public function searchAndGetBestProduct(string $keyword): ?array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return null;
        }

        if ($this->rapidApiKey !== null && $this->rapidApiKey !== '') {
            $result = $this->trySearchRealTimeAmazon($keyword);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Recherche une image gratuite sur Pexels.
     */
    public function searchImagePexels(string $keyword): ?string
    {
        if (!$this->isPexelsConfigured()) {
            return null;
        }

        $keyword = trim($keyword);
        if ($keyword === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.pexels.com/v1/search', [
                'query' => ['query' => $keyword, 'per_page' => '1', 'locale' => 'fr-FR'],
                'headers' => ['Authorization' => $this->pexelsApiKey],
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $data = $response->toArray(false);
            $photos = $data['photos'] ?? [];
            if (empty($photos) || !isset($photos[0]['src']['original'])) {
                return null;
            }
            $url = $photos[0]['src']['original'] ?? $photos[0]['src']['large'] ?? null;
            return is_string($url) && str_starts_with($url, 'http') ? $url : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function trySearchRealTimeAmazon(string $keyword): ?array
    {
        try {
            $host = 'real-time-amazon-data.p.rapidapi.com';
            $response = $this->httpClient->request('GET', "https://{$host}/search", [
                'query' => ['query' => $keyword, 'page' => '1'],
                'headers' => ['x-rapidapi-key' => $this->rapidApiKey, 'x-rapidapi-host' => $host],
            ]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $data = $response->toArray(false);
            $products = $data['data']['products'] ?? $data['products'] ?? [];
            return $this->parseFirstProduct($products, $keyword);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseFirstProduct(array $products, string $keyword): ?array
    {
        if (empty($products) || !is_array($products[0] ?? null)) {
            return null;
        }
        $first = $products[0];
        $nom = $first['product_title'] ?? $first['title'] ?? $keyword;
        $prix = $this->extractPrice($first);
        $description = $first['product_description'] ?? $first['description'] ?? null;
        $imageUrl = $this->extractImageUrl($first);

        return [
            'nom' => is_string($nom) ? $nom : $keyword,
            'description' => is_string($description) ? $description : null,
            'prix' => $prix,
            'image_url' => is_string($imageUrl) ? $imageUrl : null,
        ];
    }

    public function enrichProduct(string $productName, string $originalQuery): ?array
    {
        $result = $this->searchAndGetBestProduct($productName ?: $originalQuery);
        if (!$result) {
            return null;
        }
        return array_filter([
            'prix' => $result['prix'] > 0 ? $result['prix'] : null,
            'description' => $result['description'],
        ], fn ($v) => $v !== null);
    }

    private function extractImageUrl(array $item): ?string
    {
        $candidates = [
            $item['product_photos'][0] ?? null,
            $item['product_photo'] ?? null,
            $item['thumbnail'] ?? null,
            $item['main_image'] ?? null,
            $item['image'] ?? null,
        ];
        foreach ($candidates as $url) {
            if (is_string($url) && str_starts_with($url, 'http')) {
                return $url;
            }
        }
        return null;
    }

    private function extractPrice(array $item): float
    {
        $raw = $item['product_price'] ?? $item['price'] ?? null;
        if ($raw === null) {
            return 0;
        }
        $clean = preg_replace('/[^\d.,]/', '', (string) $raw);
        $clean = str_replace(',', '.', $clean);
        return (float) $clean;
    }
}
