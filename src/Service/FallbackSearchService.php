<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Recherche de secours sans clé API (pour afficher des résultats dans l'app quand Google n'est pas configuré).
 * Essaie SearXNG (JSON) puis DuckDuckGo HTML.
 */
final class FallbackSearchService
{
    /** @var string[] */
    private const SEARX_INSTANCES = [
        'https://search.bus-hit.me',
        'https://searx.be',
    ];

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; rv:109.0) Gecko/20100101 Firefox/115.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Recherche web et retourne une liste de résultats (titre, extrait, url).
     *
     * @return array{items: array<int, array{name: string, snippet: string, url: string}>, error: string|null}
     */
    public function search(string $query, int $maxResults = 15): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['items' => [], 'error' => 'Requête vide.'];
        }

        $result = $this->searchViaSearx($query, $maxResults);
        if ($result['items'] !== []) {
            return $result;
        }

        return $this->searchViaDuckDuckGoHtml($query, $maxResults);
    }

    /**
     * @return array{items: array<int, array{name: string, snippet: string, url: string}>, error: string|null}
     */
    private function searchViaSearx(string $query, int $maxResults): array
    {
        foreach (self::SEARX_INSTANCES as $baseUrl) {
            try {
                $url = $baseUrl . '/search?q=' . rawurlencode($query) . '&format=json';
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 10,
                    'headers' => [
                        'User-Agent' => self::USER_AGENT,
                        'Accept' => 'application/json',
                    ],
                ]);
                if ($response->getStatusCode() !== 200) {
                    continue;
                }
                $data = $response->toArray();
                $items = $data['results'] ?? [];
                if (!\is_array($items)) {
                    continue;
                }
                $results = [];
                foreach (array_slice($items, 0, $maxResults) as $item) {
                    $results[] = [
                        'name' => (string) ($item['title'] ?? ''),
                        'snippet' => (string) ($item['content'] ?? ''),
                        'url' => (string) ($item['url'] ?? ''),
                    ];
                }
                return ['items' => $results, 'error' => null];
            } catch (\Throwable $e) {
                continue;
            }
        }
        return ['items' => [], 'error' => null];
    }

    /**
     * Récupère les résultats via la page HTML DuckDuckGo et parse le HTML.
     *
     * @return array{items: array<int, array{name: string, snippet: string, url: string}>, error: string|null}
     */
    private function searchViaDuckDuckGoHtml(string $query, int $maxResults): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://html.duckduckgo.com/html/', [
                'query' => ['q' => $query],
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html',
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                return ['items' => [], 'error' => null];
            }
            $html = $response->getContent();
            return ['items' => $this->parseDuckDuckGoHtml($html, $maxResults), 'error' => null];
        } catch (\Throwable $e) {
            return ['items' => [], 'error' => null];
        }
    }

    /**
     * @return array<int, array{name: string, snippet: string, url: string}>
     */
    private function parseDuckDuckGoHtml(string $html, int $maxResults): array
    {
        $results = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, \LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);
        foreach (['result', 'results_links', 'web-result'] as $class) {
            $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')]");
            if ($nodes !== false && $nodes->length > 0) {
                break;
            }
        }
        if ($nodes === false || $nodes->length === 0) {
            $nodes = $xpath->query("//div[.//a[contains(@href, 'duckduckgo.com/l/')]]");
        }
        if ($nodes === false) {
            return [];
        }
        for ($i = 0; $i < min($nodes->length, $maxResults); $i++) {
            $node = $nodes->item($i);
            if ($node === null) {
                continue;
            }
            $links = $xpath->query(".//a[contains(@href, 'duckduckgo.com/l/') or contains(@href, 'uddg=')]", $node);
            $title = '';
            $snippet = '';
            $url = '';
            foreach ($links !== false ? $links : [] as $a) {
                $href = $a->getAttribute('href');
                $decoded = $this->decodeDuckDuckGoUrl($href);
                if ($decoded === '') {
                    continue;
                }
                $text = trim($a->textContent ?? '');
                if ($url === '' && strlen($text) > 3) {
                    $title = $text;
                    $url = $decoded;
                } elseif ($url !== '' && $snippet === '' && strlen($text) > 10) {
                    $snippet = $text;
                    break;
                }
            }
            if ($url !== '' && $title !== '') {
                $results[] = [
                    'name' => $title,
                    'snippet' => $snippet,
                    'url' => $url,
                ];
            }
        }
        return $results;
    }

    private function decodeDuckDuckGoUrl(string $href): string
    {
        if (str_contains($href, 'uddg=')) {
            parse_str(parse_url($href, \PHP_URL_QUERY) ?? '', $params);
            if (isset($params['uddg'])) {
                return urldecode($params['uddg']);
            }
        }
        if (str_contains($href, 'duckduckgo.com/l/')) {
            return $href;
        }
        return $href;
    }
}
