<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LibreTranslateService
{
    private const MAX_CHARS_PER_REQUEST = 4500;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
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
            $chunks = $this->chunkText($text);
            $translated = [];
            foreach ($chunks as $chunk) {
                $result = $this->doTranslate($chunk, $source, $target);
                $translated[] = $result ?? $chunk;
            }
            $result = implode('', $translated);

            if ($result !== $text) {
                $item->expiresAfter($this->cacheTtl);
                return $result;
            }
            $item->expiresAfter(60);
            return $text;
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
                'timeout' => 15,
            ]);

            $data = $response->toArray();
            $translated = $data['translatedText'] ?? null;

            $result = \is_string($translated) ? $translated : (\is_array($translated) ? implode('', $translated) : null);
            if ($result !== null && $result !== $text) {
                return $result;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('LibreTranslate échec: {msg}', ['msg' => $e->getMessage(), 'url' => $this->apiUrl]);
        }

        return $this->translateViaMyMemory($text, $source, $target) ?? $text;
    }

    private function translateViaMyMemory(string $text, string $source, string $target): ?string
    {
        try {
            $maxChunk = 450;
            $chunks = mb_strlen($text) <= $maxChunk ? [$text] : $this->chunkText($text, $maxChunk);
            $translated = [];
            foreach ($chunks as $chunk) {
                $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
                    'q' => $chunk,
                    'langpair' => $source . '|' . $target,
                ]);
                $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);
                $data = $response->toArray();
                $result = $data['responseData']['translatedText'] ?? null;
                if ($result === null || $result === $chunk) {
                    return null;
                }
                $translated[] = $result;
            }
            return implode('', $translated);
        } catch (\Throwable $e) {
            $this->logger->warning('MyMemory échec: {msg}', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    private function chunkText(string $text, ?int $maxChars = null): array
    {
        $limit = $maxChars ?? self::MAX_CHARS_PER_REQUEST;
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $current = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current) + mb_strlen($sentence) > $limit) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                if (mb_strlen($sentence) > $limit) {
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
