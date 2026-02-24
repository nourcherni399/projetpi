<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Module;
use App\Enum\CategorieModule;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WikipediaService
{
    private const WIKI_API = 'https://%s.wikipedia.org/w/api.php';
    private const USER_AGENT = 'AutiCare/1.0 (http://127.0.0.1:8000/; emna1340@gmail.com)';

    /** Pages génériques à éviter lorsqu'une page plus spécifique existe */
    private const GENERIC_PAGES = [
        'fr' => ['Autisme', 'Autism'],
        'en' => ['Autism'],
        'es' => ['Autismo'],
        'de' => ['Autismus'],
        'it' => ['Autismo'],
        'pt' => ['Autismo'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Recherche un article Wikipedia pertinent pour un module (titre + catégorie + extrait contenu).
     */
    public function getArticleUrlForModule(Module $module, string $locale = 'fr'): ?string
    {
        $searchTerms = $this->buildSearchTermsForModule($module, $locale);
        $wikiLang = $this->mapLocaleToWikiLang($locale);
        $genericTitles = self::GENERIC_PAGES[$wikiLang] ?? self::GENERIC_PAGES['fr'];

        foreach ($searchTerms as $term) {
            if (trim($term) === '') {
                continue;
            }
            $url = $this->searchWikipedia($term, $wikiLang);
            if ($url !== null) {
                // Si on a une page très générique et qu'on pourrait avoir mieux, on continue
                $pageTitle = $this->extractTitleFromUrl($url, $wikiLang);
                $isGeneric = $pageTitle !== null && \in_array($pageTitle, $genericTitles, true);
                if (!$isGeneric || count($searchTerms) === 1) {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * @deprecated Utiliser getArticleUrlForModule pour des liens spécifiques par module
     */
    public function getArticleUrl(string $moduleTitle, string $locale = 'fr'): ?string
    {
        $searchTerm = trim($moduleTitle) . ' ' . ($this->getAutismKeyword($locale));
        return $this->searchWikipedia($searchTerm, $this->mapLocaleToWikiLang($locale));
    }

    private function buildSearchTermsForModule(Module $module, string $locale): array
    {
        $terms = [];
        $autismKw = $this->getAutismKeyword($locale);
        $categorieKw = $this->getCategoryKeywords($module->getCategorie(), $locale);
        $titre = trim($module->getTitre() ?? '');
        $description = $this->extractKeywords(($module->getDescription() ?? ''));
        $contenu = $this->extractKeywords(($module->getContenu() ?? ''));

        // 1. Titre + autisme (priorité)
        if ($titre !== '') {
            $terms[] = $titre . ' ' . $autismKw;
            $terms[] = $autismKw . ' ' . $titre;
        }

        // 2. Titre + catégorie + autisme
        if ($titre !== '' && $categorieKw !== '') {
            $terms[] = $titre . ' ' . $categorieKw . ' ' . $autismKw;
        }

        // 3. Mots-clés du contenu/description (max 4-5 mots significatifs)
        $contentKeywords = array_slice(array_unique(array_merge($description, $contenu)), 0, 5);
        if (count($contentKeywords) > 0) {
            $terms[] = implode(' ', $contentKeywords) . ' ' . $autismKw;
        }

        // 4. Catégorie seule + autisme
        if ($categorieKw !== '') {
            $terms[] = $categorieKw . ' ' . $autismKw;
        }

        return array_unique(array_filter($terms));
    }

    private function getCategoryKeywords(CategorieModule $categorie, string $locale): string
    {
        $map = [
            'COMPRENDRE_TSA' => ['fr' => 'TSA trouble spectre autistique', 'en' => 'autism spectrum disorder'],
            'AUTONOMIE' => ['fr' => 'autonomie', 'en' => 'autonomy independence'],
            'COMMUNICATION' => ['fr' => 'communication', 'en' => 'communication'],
            'EMOTIONS' => ['fr' => 'émotions gestion', 'en' => 'emotions regulation'],
            'VIE_QUOTIDIENNE' => ['fr' => 'vie quotidienne', 'en' => 'daily living'],
            'ACCOMPAGNEMENT' => ['fr' => 'accompagnement', 'en' => 'support care'],
        ];
        $keywords = $map[$categorie->value] ?? [];
        return $keywords[$locale] ?? $keywords['fr'] ?? '';
    }

    private function extractKeywords(string $text): array
    {
        $text = strip_tags($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?? [];
        $stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'et', 'ou', 'de', 'du', 'à', 'en', 'pour', 'avec', 'dans', 'sur', 'ce', 'cette', 'ces', 'que', 'qui', 'par', 'au', 'aux', 'ne', 'pas', 'est', 'sont', 'a', 'the', 'and', 'or', 'of', 'in', 'to', 'for', 'with', 'on', 'that', 'this', 'it', 'is', 'are', 'be'];
        return array_values(array_filter($words, static fn (string $w) => mb_strlen($w) >= 3 && !\in_array(mb_strtolower($w), $stopWords, true)));
    }

    private function getAutismKeyword(string $locale): string
    {
        $keywords = [
            'fr' => 'autisme', 'en' => 'autism', 'es' => 'autismo',
            'de' => 'Autismus', 'it' => 'autismo', 'pt' => 'autismo',
            'ar' => 'توحد', 'ru' => 'аутизм', 'zh' => '自闭症',
        ];
        return $keywords[$locale] ?? 'autism';
    }

    private function searchWikipedia(string $searchTerm, string $wikiLang): ?string
    {
        $url = sprintf(
            self::WIKI_API . '?action=query&list=search&srsearch=%s&srlimit=5&format=json',
            $wikiLang,
            rawurlencode($searchTerm)
        );

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => self::USER_AGENT],
                'timeout' => 5,
            ]);
            $data = $response->toArray();
            $results = $data['query']['search'] ?? [];
            if (count($results) === 0) {
                return null;
            }
            $title = $results[0]['title'] ?? null;
            if ($title === null) {
                return null;
            }
            $encoded = rawurlencode(str_replace(' ', '_', $title));
            return sprintf('https://%s.wikipedia.org/wiki/%s', $wikiLang, $encoded);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractTitleFromUrl(string $url, string $wikiLang): ?string
    {
        $prefix = sprintf('https://%s.wikipedia.org/wiki/', $wikiLang);
        if (!str_starts_with($url, $prefix)) {
            return null;
        }
        $title = substr($url, strlen($prefix));
        return str_replace('_', ' ', urldecode($title));
    }

    private function mapLocaleToWikiLang(string $locale): string
    {
        $map = ['ar' => 'ar', 'de' => 'de', 'en' => 'en', 'es' => 'es', 'fr' => 'fr', 'it' => 'it', 'pt' => 'pt', 'ru' => 'ru', 'zh' => 'zh'];
        return $map[$locale] ?? 'fr';
    }

    public function getFallbackUrl(string $locale = 'fr'): string
    {
        $lang = $this->mapLocaleToWikiLang($locale);
        $slug = $lang === 'fr' ? 'Autisme' : 'Autism';
        return sprintf('https://%s.wikipedia.org/wiki/%s', $lang, $slug);
    }
}
