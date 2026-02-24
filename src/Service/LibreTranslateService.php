<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LibreTranslateService
{
    private const MAX_CHARS_PER_REQUEST = 4500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $apiUrl = 'https://libretranslate.com',
        private readonly int $cacheTtl = 86400,
        private readonly ?string $apiKey = null,
    ) {
    }

    public function translate(string $text, string $source = 'fr', string $target = 'en'): string
    {
        $text = trim($text);
        if ($text === '' || $source === $target) {
            return $text;
        }

        $cacheKey = 'libretranslate_' . md5($text . $source . $target);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($text, $source, $target): string {
            $item->expiresAfter($this->cacheTtl);

            $chunks = $this->chunkText($text);
            $translated = [];

            foreach ($chunks as $chunk) {
                $result = $this->doTranslate($chunk, $source, $target);
                $translated[] = $result ?? $chunk;
            }

            return implode('', $translated);
        });
    }

    private function doTranslate(string $text, string $source, string $target): ?string
    {
        try {
            $body = [
                'q' => $text,
                'source' => $source,
                'target' => $target,
                'format' => 'text',
            ];
            if ($this->apiKey !== null && $this->apiKey !== '') {
                $body['api_key'] = $this->apiKey;
            }

            $response = $this->httpClient->request('POST', rtrim($this->apiUrl, '/') . '/translate', [
                'json' => $body,
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            $translated = $data['translatedText'] ?? null;

            return \is_string($translated) ? $translated : (\is_array($translated) ? implode('', $translated) : $text);
        } catch (\Throwable $e) {
            return $text;
        }
    }

    private function chunkText(string $text): array
    {
        if (mb_strlen($text) <= self::MAX_CHARS_PER_REQUEST) {
            return [$text];
        }

        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $current = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current) + mb_strlen($sentence) > self::MAX_CHARS_PER_REQUEST) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                if (mb_strlen($sentence) > self::MAX_CHARS_PER_REQUEST) {
                    $chunks[] = $sentence;
                } else {
                    $current = $sentence . ' ';
                }
            } else {
                $current .= $sentence . ' ';
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }
}
