<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PexelsService
{
    private const API_BASE = 'https://api.pexels.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $pexelsApiKey,
    ) {
    }

    /**
     * Recherche des photos sur Pexels.
     *
     * @return array{photos: array<int, array{id: int, src_medium: string, src_large: string, photographer: string}>, error: ?string}
     */
    public function search(string $query, int $perPage = 10): array
    {
        if (trim($query) === '' || $this->pexelsApiKey === '') {
            return ['photos' => [], 'error' => 'Configuration invalide'];
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE . '/search', [
                'query' => [
                    'query' => $query,
                    'per_page' => $perPage,
                ],
                'headers' => [
                    'Authorization' => $this->pexelsApiKey,
                ],
            ]);

            $data = $response->toArray();
            $photos = [];

            foreach ($data['photos'] ?? [] as $photo) {
                $photos[] = [
                    'id' => $photo['id'] ?? 0,
                    'src_medium' => $photo['src']['medium'] ?? $photo['src']['large'] ?? '',
                    'src_large' => $photo['src']['large'] ?? $photo['src']['original'] ?? '',
                    'photographer' => $photo['photographer'] ?? 'Inconnu',
                ];
            }

            return ['photos' => $photos, 'error' => null];
        } catch (\Throwable $e) {
            return [
                'photos' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Télécharge une image depuis une URL et la sauvegarde localement.
     */
    public function downloadAndSave(string $imageUrl, string $targetDir): ?string
    {
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', $imageUrl);
            $content = $response->getContent();

            if (empty($content)) {
                return null;
            }

            $extension = 'jpg';
            if (preg_match('/\.(jpe?g|png|gif|webp)$/i', parse_url($imageUrl, PHP_URL_PATH) ?? '', $m)) {
                $extension = strtolower($m[1]);
                if ($extension === 'jpeg') {
                    $extension = 'jpg';
                }
            }

            $filename = 'pexels-' . uniqid() . '.' . $extension;

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $filepath = $targetDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($filepath, $content);

            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }
}
