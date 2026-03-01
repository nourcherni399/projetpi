<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour valider les URLs YouTube et récupérer les métadonnées via l'API YouTube Data v3.
 * Si YOUTUBE_API_KEY est vide, seule la validation du format d'URL est effectuée.
 */
final class YoutubeVideoService
{
    private const YOUTUBE_API_URL = 'https://www.googleapis.com/youtube/v3/videos';
    private const YOUTUBE_SEARCH_URL = 'https://www.googleapis.com/youtube/v3/search';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
    ) {
    }

    /**
     * Vérifie si l'URL est une URL YouTube valide.
     */
    public function isYoutubeUrl(string $url): bool
    {
        $url = trim($url);
        return (bool) preg_match(
            '#(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#i',
            $url
        );
    }

    /**
     * Extrait l'ID vidéo d'une URL YouTube.
     */
    public function extractVideoId(string $url): ?string
    {
        $url = trim($url);
        if (preg_match('#(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Valide la vidéo YouTube via l'API et retourne les métadonnées.
     *
     * @return array{valid: bool, title?: string, thumbnail?: string, duration?: string, error?: string}
     */
    public function validateAndGetMetadata(string $url): array
    {
        $videoId = $this->extractVideoId($url);
        if ($videoId === null) {
            return ['valid' => false, 'error' => 'URL YouTube invalide.'];
        }

        if ($this->apiKey === '' || !strlen(trim($this->apiKey))) {
            return ['valid' => true];
        }

        try {
            $response = $this->httpClient->request('GET', self::YOUTUBE_API_URL, [
                'query' => [
                    'part' => 'snippet,contentDetails,status',
                    'id' => $videoId,
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['items']) || empty($data['items'])) {
                return ['valid' => false, 'error' => 'Cette vidéo YouTube n\'existe pas ou a été supprimée.'];
            }

            $item = $data['items'][0];
            $status = $item['status'] ?? [];

            $privacyStatus = $status['privacyStatus'] ?? 'unknown';
            if ($privacyStatus !== 'public') {
                return [
                    'valid' => false,
                    'error' => 'Cette vidéo est privée ou non disponible. Seules les vidéos publiques sont acceptées.',
                ];
            }

            $embeddable = $status['embeddable'] ?? true;
            if (!$embeddable) {
                return [
                    'valid' => false,
                    'error' => 'Cette vidéo n\'autorise pas l\'intégration sur des sites externes.',
                ];
            }

            $snippet = $item['snippet'] ?? [];
            $contentDetails = $item['contentDetails'] ?? [];

            $thumbnails = $snippet['thumbnails'] ?? [];
            $thumbnail = $thumbnails['medium']['url'] ?? $thumbnails['default']['url'] ?? null;

            $duration = $contentDetails['duration'] ?? null;
            if ($duration !== null && class_exists(\DateInterval::class)) {
                try {
                    $duration = $this->formatIso8601Duration($duration);
                } catch (\Throwable) {
                    $duration = null;
                }
            }

            return [
                'valid' => true,
                'title' => $snippet['title'] ?? null,
                'thumbnail' => $thumbnail,
                'duration' => $duration,
            ];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, '404') || str_contains($message, '403')) {
                return ['valid' => false, 'error' => 'Cette vidéo YouTube n\'existe pas ou n\'est pas accessible.'];
            }

            return [
                'valid' => false,
                'error' => 'Impossible de vérifier la vidéo YouTube. Vérifiez votre connexion ou réessayez plus tard.',
            ];
        }
    }

    /**
     * Recherche des vidéos YouTube par mot-clé via l'API search.list.
     *
     * @return array{videos: array<int, array{id: string, url: string, title: string, thumbnail: ?string, channelTitle: string}>, error?: string}
     */
    public function search(string $query, int $maxResults = 10): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['videos' => []];
        }

        if ($this->apiKey === '' || !strlen(trim($this->apiKey))) {
            return [
                'videos' => [],
                'error' => 'La recherche YouTube nécessite une clé API. Configurez YOUTUBE_API_KEY dans .env.local.',
            ];
        }

        try {
            $response = $this->httpClient->request('GET', self::YOUTUBE_SEARCH_URL, [
                'query' => [
                    'part' => 'snippet',
                    'type' => 'video',
                    'q' => $query,
                    'maxResults' => min(max(1, $maxResults), 15),
                    'key' => $this->apiKey,
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return ['videos' => []];
            }

            $videos = [];
            foreach ($data['items'] as $item) {
                $videoId = $item['id']['videoId'] ?? null;
                if ($videoId === null) {
                    continue;
                }

                $snippet = $item['snippet'] ?? [];
                $thumbnails = $snippet['thumbnails'] ?? [];
                $thumbnail = $thumbnails['medium']['url'] ?? $thumbnails['default']['url'] ?? null;

                $videos[] = [
                    'id' => $videoId,
                    'url' => 'https://www.youtube.com/watch?v=' . $videoId,
                    'title' => $snippet['title'] ?? '',
                    'thumbnail' => $thumbnail,
                    'channelTitle' => $snippet['channelTitle'] ?? '',
                ];
            }

            return ['videos' => $videos];
        } catch (\Throwable $e) {
            return [
                'videos' => [],
                'error' => 'Impossible de rechercher sur YouTube. Vérifiez votre connexion et la clé API.',
            ];
        }
    }

    private function formatIso8601Duration(string $iso): string
    {
        $interval = new \DateInterval($iso);
        $parts = [];
        if ($interval->h > 0) {
            $parts[] = $interval->h . ' h';
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i . ' min';
        }
        if ($interval->s > 0 || empty($parts)) {
            $parts[] = $interval->s . ' s';
        }

        return implode(' ', $parts);
    }
}
