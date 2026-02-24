<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Recherche d’images gratuites via l’API Pexels.
 * Clé API : https://www.pexels.com/api/
 */
final class PexelsImageService
{
    private const API_URL = 'https://api.pexels.com/v1/search';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {
    }

    public function isConfigured(): bool
    {
        $key = trim($this->apiKey);
        return $key !== '' && $key !== '0';
    }

    /** Mots français → anglais pour des recherches Pexels pertinentes (sujet de la photo) */
    private const FR_TO_EN = [
        'enfant' => 'child', 'enfants' => 'children', 'calme' => 'calm', 'parc' => 'park',
        'émotions' => 'emotions', 'routine' => 'routine', 'famille' => 'family',
        'communication' => 'communication', 'quotidien' => 'everyday', 'autonomie' => 'independence',
        'support' => 'support', 'visuel' => 'visual', 'thérapeutique' => 'therapeutic',
        'émotion' => 'emotion', 'heureux' => 'happy', 'triste' => 'sad', 'serein' => 'peaceful',
        'cartes' => 'cards', 'carte' => 'card', 'support' => 'support', 'exemples' => 'examples',
    ];

    private const STOP_WORDS = ['une', 'un', 'des', 'dans', 'pour', 'style', 'image', 'dun', 'du', 'la', 'le', 'les', 'et', 'ou', 'avec', 'par', 'sur', 'qui', 'que', 'quoi'];

    /**
     * Construit une requête de recherche courte en anglais à partir d’une description française,
     * pour que Pexels renvoie des photos qui correspondent au sujet (ex. "enfant calme parc" → "calm child park").
     */
    public function buildSearchQueryFromFrench(string $frenchDescription): string
    {
        $text = mb_strtolower($frenchDescription);
        $text = preg_replace('/[^\p{L}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $en = [];
        $seen = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '' || \in_array($w, self::STOP_WORDS, true)) {
                continue;
            }
            $english = null;
            foreach (self::FR_TO_EN as $fr => $tr) {
                if ($w === $fr || str_starts_with($w, $fr) || str_starts_with($fr, $w)) {
                    $english = $tr;
                    break;
                }
            }
            if ($english !== null && !isset($seen[$english])) {
                $seen[$english] = true;
                $en[] = $english;
            }
        }
        $query = implode(' ', array_slice($en, 0, 5));
        return $query !== '' ? $query : 'calm child park';
    }

    /**
     * Recherche une photo et retourne l’URL de la première image (format moyen).
     * Pour une description en français, utilisez searchFirstPhotoForFrench() pour de meilleurs résultats.
     * @return string|null URL de l’image ou null en cas d’erreur / aucun résultat
     */
    public function searchFirstPhoto(string $query): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $query = trim($query);
        if ($query === '') {
            return null;
        }

        try {
            // On demande plusieurs résultats et on choisit une photo au hasard
            // pour éviter d'afficher toujours la même image pour une requête donnée.
            $response = $this->httpClient->request('GET', self::API_URL, [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => trim($this->apiKey),
                ],
                'query' => [
                    'query' => $query,
                    'per_page' => 10,
                    'page' => random_int(1, 5),
                    'locale' => 'en-US',
                ],
            ]);

            $data = $response->toArray();
            $photos = $data['photos'] ?? [];

            if ($photos === []) {
                return null;
            }

            // Choisir une photo aléatoire parmi les résultats disponibles
            $index = \count($photos) > 1 ? random_int(0, \count($photos) - 1) : 0;
            $chosen = $photos[$index];
            $src = $chosen['src'] ?? [];
            return $src['medium'] ?? $src['large'] ?? $src['original'] ?? null;
        } catch (ExceptionInterface $e) {
            return null;
        }
    }
}
