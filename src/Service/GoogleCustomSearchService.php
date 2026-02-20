<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Recherche web mondiale via Google Custom Search JSON API (quota gratuit ~100 requêtes/jour).
 * Utilisé pour "Trouver des idées" (snippets pour l'IA) et "Recherche mondiale d'événements à venir".
 * Nécessite GOOGLE_CSE_API_KEY et GOOGLE_CSE_CX (Programmable Search Engine avec "Search the entire web").
 */
final class GoogleCustomSearchService
{
    private const API_URL = 'https://customsearch.googleapis.com/customsearch/v1';
    private const MAX_SNIPPETS_LENGTH = 4000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey = null,
        private readonly ?string $searchEngineId = null,
    ) {
    }

    public function isConfigured(): bool
    {
        $key = $this->apiKey !== null && $this->apiKey !== '' ? trim($this->apiKey) : null;
        $cx = $this->searchEngineId !== null && $this->searchEngineId !== '' ? trim($this->searchEngineId) : null;
        return $key !== null && $cx !== null;
    }

    /**
     * Recherche mondiale d'événements à venir.
     * Envoie une requête à Google Custom Search (recherche web classique) et affiche les pages trouvées.
     *
     * @return array{items: array<int, array{name: string, snippet: string, url: string}>, error: string|null, searchQuery: string}
     */
    public function searchUpcomingEvents(string $query, string $period = '2025'): array
    {
        $query = trim($query);
        if ($query === '' || !$this->isConfigured()) {
            return ['items' => [], 'error' => $query === '' ? 'Requête vide.' : null, 'searchQuery' => ''];
        }
        $searchQuery = $query . ' ' . $period . ' upcoming events';
        if (str_contains(mb_strtolower($query), 'événement') === false && str_contains(mb_strtolower($query), 'event') === false) {
            $searchQuery = 'upcoming events ' . $query . ' ' . $period;
        }
        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'key' => trim($this->apiKey),
                    'cx' => trim($this->searchEngineId),
                    'q' => $searchQuery,
                    'num' => 15,
                ],
                'timeout' => 15,
            ]);
            $data = $response->toArray();
            $results = [];
            if (isset($data['items']) && \is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $results[] = [
                        'name' => (string) ($item['title'] ?? ''),
                        'snippet' => (string) ($item['snippet'] ?? ''),
                        'url' => (string) ($item['link'] ?? ''),
                    ];
                }
            }
            return ['items' => $results, 'error' => null, 'searchQuery' => $searchQuery];
        } catch (\Throwable $e) {
            $raw = $e->getMessage();
            if (str_contains($raw, '400') || str_contains($raw, 'Bad Request')) {
                $message = 'Requête Google refusée (400). Vérifiez que la clé API (GOOGLE_CSE_API_KEY) et l’ID du moteur (GOOGLE_CSE_CX) sont corrects, que l’API « Custom Search » est activée dans la Google Cloud Console, et que la facturation est activée sur le projet (même pour le quota gratuit).';
            } elseif (str_contains($raw, '401') || str_contains($raw, '403')) {
                $message = 'Clé API Google invalide ou désactivée. Vérifiez GOOGLE_CSE_API_KEY et le moteur (GOOGLE_CSE_CX).';
            } elseif (str_contains($raw, '429')) {
                $message = 'Quota Google dépassé (~100 requêtes/jour). Réessayez demain.';
            } elseif (str_contains($raw, 'customsearch.googleapis.com') || str_contains($raw, 'key=')) {
                $message = 'Erreur lors de l’appel à Google Custom Search. Vérifiez GOOGLE_CSE_API_KEY, GOOGLE_CSE_CX et l’activation de l’API dans la Google Cloud Console.';
            } else {
                $message = 'Erreur de recherche : ' . $raw;
            }
            return ['items' => [], 'error' => $message, 'searchQuery' => $searchQuery];
        }
    }

    /**
     * Recherche web et retourne les extraits concaténés (pour l'IA "Trouver des idées").
     * Sans clé Google, retourne un corpus statique.
     *
     * @return array{text: string, fromGoogle: bool}
     */
    public function searchAndGetSnippets(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['text' => $this->getFallbackCorpus(), 'fromGoogle' => false];
        }
        if (!$this->isConfigured()) {
            return ['text' => $this->getFallbackCorpus(), 'fromGoogle' => false];
        }
        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'query' => [
                    'key' => trim($this->apiKey),
                    'cx' => trim($this->searchEngineId),
                    'q' => $query,
                    'num' => 10,
                ],
                'timeout' => 15,
            ]);
            $data = $response->toArray();
            $snippets = [];
            $len = 0;
            if (isset($data['items']) && \is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $title = (string) ($item['title'] ?? '');
                    $snippet = (string) ($item['snippet'] ?? '');
                    $block = $title !== '' ? $title . "\n" . $snippet : $snippet;
                    if ($len + mb_strlen($block) > self::MAX_SNIPPETS_LENGTH) {
                        break;
                    }
                    $snippets[] = $block;
                    $len += mb_strlen($block);
                }
            }
            $text = implode("\n\n", $snippets);
            if ($text === '') {
                return ['text' => $this->getFallbackCorpus(), 'fromGoogle' => false];
            }
            return ['text' => $text, 'fromGoogle' => true];
        } catch (\Throwable $e) {
            return ['text' => $this->getFallbackCorpus(), 'fromGoogle' => false];
        }
    }

    private function getFallbackCorpus(): string
    {
        return <<<'TEXT'
Exemples d'événements qui fonctionnent bien pour les familles et l'inclusion (autisme, handicaps) :
- Ateliers sensoriels : activités de découverte des sens (toucher, odeurs, sons) dans un cadre apaisant, souvent très appréciés des enfants et des familles.
- Cafés des parents : moments d'échange entre familles et professionnels, partage d'expériences, entraide.
- Sorties adaptées : visites de musées, fermes, parcs avec créneaux dédiés (moins de monde, personnel formé).
- Groupes de parole fratrie : pour les frères et sœurs d'enfants en situation de handicap.
- Sensibilisation en milieu scolaire : interventions en classe pour expliquer les différences et favoriser l'inclusion.
- Journées portes ouvertes des structures : découvrir les lieux et les activités avant de s'engager.
- Ateliers créatifs (peinture, musique, théâtre) adaptés : cadre bienveillant, consignes claires, pause possible.
TEXT;
    }
}
