<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service pour interagir avec l'API Google Books (recherche et détails).
 */
final class GoogleBooksService
{
    private const API_BASE = 'https://www.googleapis.com/books/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey = '',
    ) {
    }

    /**
     * Recherche de livres par titre, auteur ou mot-clé.
     *
     * @return array{items: array<int, array{id: string, title: string, authors: array<string>, thumbnail: ?string, publishedDate: ?string, description: ?string}>, error?: string}
     */
    public function search(string $query, int $maxResults = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['items' => []];
        }

        if ($this->apiKey === '' || !strlen(trim($this->apiKey))) {
            return [
                'items' => [],
                'error' => 'La recherche de livres nécessite une clé API. Configurez GOOGLE_BOOKS_API_KEY dans .env.local.',
            ];
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE . '/volumes', [
                'query' => [
                    'q' => $query,
                    'maxResults' => min(max(1, $maxResults), 40),
                    'key' => $this->apiKey,
                    'printType' => 'books',
                    'langRestrict' => 'fr,en',
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['items'])) {
                return ['items' => []];
            }

            $items = [];
            foreach ($data['items'] as $item) {
                $volumeInfo = $item['volumeInfo'] ?? [];
                $imageLinks = $volumeInfo['imageLinks'] ?? [];
                $thumbnail = $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? null;
                if ($thumbnail !== null && str_starts_with($thumbnail, 'http:')) {
                    $thumbnail = 'https:' . substr($thumbnail, 5);
                }

                $items[] = [
                    'id' => $item['id'] ?? '',
                    'title' => $volumeInfo['title'] ?? 'Sans titre',
                    'authors' => $volumeInfo['authors'] ?? [],
                    'thumbnail' => $thumbnail,
                    'publishedDate' => $volumeInfo['publishedDate'] ?? null,
                    'description' => $volumeInfo['description'] ?? null,
                    'previewLink' => $volumeInfo['previewLink'] ?? null,
                    'infoLink' => $volumeInfo['infoLink'] ?? null,
                ];
            }

            return ['items' => $items];
        } catch (\Throwable $e) {
            return [
                'items' => [],
                'error' => 'Impossible de rechercher. Vérifiez votre connexion et la clé API.',
            ];
        }
    }

    /**
     * Récupère les détails complets d'un livre par son ID.
     *
     * @return array{id: string, title: string, authors: array<string>, publisher: ?string, publishedDate: ?string, isbn: ?string, description: ?string, pageCount: ?int, thumbnail: ?string, previewLink: ?string, infoLink: ?string}|null
     */
    public function getVolume(string $volumeId): ?array
    {
        $volumeId = trim($volumeId);
        if ($volumeId === '') {
            return null;
        }

        if ($this->apiKey === '' || !strlen(trim($this->apiKey))) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_BASE . '/volumes/' . $volumeId, [
                'query' => [
                    'key' => $this->apiKey,
                ],
            ]);

            $item = $response->toArray();

            if (!isset($item['volumeInfo'])) {
                return null;
            }

            $volumeInfo = $item['volumeInfo'];
            $industryIds = $volumeInfo['industryIdentifiers'] ?? [];
            $isbn = null;
            foreach ($industryIds as $id) {
                $type = $id['type'] ?? '';
                if (str_contains($type, 'ISBN')) {
                    $isbn = $id['identifier'] ?? null;
                    break;
                }
            }

            $imageLinks = $volumeInfo['imageLinks'] ?? [];
            $thumbnail = $imageLinks['thumbnail'] ?? $imageLinks['smallThumbnail'] ?? $imageLinks['medium'] ?? null;
            if ($thumbnail !== null && str_starts_with($thumbnail, 'http:')) {
                $thumbnail = 'https:' . substr($thumbnail, 5);
            }

            return [
                'id' => $item['id'] ?? $volumeId,
                'title' => $volumeInfo['title'] ?? 'Sans titre',
                'authors' => $volumeInfo['authors'] ?? [],
                'publisher' => $volumeInfo['publisher'] ?? null,
                'publishedDate' => $volumeInfo['publishedDate'] ?? null,
                'isbn' => $isbn,
                'description' => $volumeInfo['description'] ?? null,
                'pageCount' => isset($volumeInfo['pageCount']) ? (int) $volumeInfo['pageCount'] : null,
                'thumbnail' => $thumbnail,
                'previewLink' => $volumeInfo['previewLink'] ?? null,
                'infoLink' => $volumeInfo['infoLink'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
