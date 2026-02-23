<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WiktionaryService
{
    private const API_URL = 'https://fr.wiktionary.org/w/api.php';
    private const USER_AGENT = 'AutiCare/1.0 (https://auticare.fr)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Récupère la définition d'un mot en français via l'API Wiktionary.
     *
     * @return array{word: string, definition: string, phonetic: ?string, partOfSpeech: ?string, example: ?string, source: string}|null
     */
    public function getDefinition(string $word): ?array
    {
        $word = trim($word);
        if ($word === '') {
            return null;
        }

        // Normaliser le mot : première lettre en minuscule pour Wiktionary
        $searchWord = mb_strtolower($word);

        try {
            $url = self::API_URL . '?' . http_build_query([
                'action' => 'query',
                'titles' => $searchWord,
                'prop' => 'revisions',
                'rvprop' => 'content',
                'rvslots' => 'main',
                'format' => 'json',
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => self::USER_AGENT],
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            $pages = $data['query']['pages'] ?? [];

            foreach ($pages as $page) {
                if (isset($page['missing'])) {
                    return null;
                }

                $revisions = $page['revisions'] ?? [];
                if (count($revisions) === 0) {
                    return null;
                }

                $content = $revisions[0]['slots']['main']['*'] ?? null;
                if ($content === null) {
                    return null;
                }

                $definition = $this->extractFirstFrenchDefinition($content);
                if ($definition === null) {
                    return null;
                }

                return [
                    'word' => $page['title'] ?? $word,
                    'definition' => $definition,
                    'phonetic' => null,
                    'partOfSpeech' => null,
                    'example' => null,
                    'source' => 'wiktionary',
                ];
            }
        } catch (\Exception) {
            return null;
        }

        return null;
    }

    /**
     * Extrait la première définition française du contenu wikitext.
     */
    private function extractFirstFrenchDefinition(string $wikitext): ?string
    {
        // Chercher la section française : == {{langue|fr}} ==
        if (!preg_match('/==\s*\{\{langue\|fr\}\}\s*==(.*?)(?==\s*\{\{langue\||$)/s', $wikitext, $frSection)) {
            return null;
        }

        $frContent = $frSection[1];

        // Extraire la première définition : ligne commençant par # (pas #* qui sont des exemples)
        // Format: # {{lexique|...}} Définition textuelle
        if (preg_match('/^#\s+(.+?)$/m', $frContent, $defMatch)) {
            $raw = trim($defMatch[1]);
            $cleaned = $this->cleanWikitext($raw);
            if ($cleaned !== '' && mb_strlen($cleaned) > 15) {
                return $cleaned;
            }
        }

        return null;
    }

    /**
     * Nettoie le wikitext pour obtenir du texte lisible.
     */
    private function cleanWikitext(string $text): string
    {
        // Supprimer les templates {{...}}
        $text = preg_replace('/\{\{[^}]*\}\}/', '', $text) ?? $text;
        // Supprimer les liens [[mot|texte]] -> texte
        $text = preg_replace('/\[\[([^\]|]+)\|([^\]]+)\]\]/', '$2', $text) ?? $text;
        // Supprimer les liens simples [[mot]] -> mot
        $text = preg_replace('/\[\[([^\]]+)\]\]/', '$1', $text) ?? $text;
        // Supprimer les apostrophes wiki ''
        $text = preg_replace("/''+/", '', $text) ?? $text;
        // Supprimer les balises HTML
        $text = strip_tags($text);
        // Normaliser les espaces
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }
}
