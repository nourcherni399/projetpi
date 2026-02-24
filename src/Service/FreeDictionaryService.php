<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FreeDictionaryService
{
    private const API_URL = 'https://api.dictionaryapi.dev/api/v2/entries/en/%s';
    private const USER_AGENT = 'AutiCare/1.0 (https://auticare.fr)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Récupère la définition d'un mot en anglais via l'API Free Dictionary.
     *
     * @return array{word: string, definition: string, phonetic: ?string, partOfSpeech: ?string, example: ?string, source: string}|null
     */
    public function getDefinition(string $word): ?array
    {
        $word = trim($word);
        if ($word === '') {
            return null;
        }

        $url = sprintf(self::API_URL, rawurlencode($word));

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                ],
                'timeout' => 8,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger?->info('Free Dictionary API returned status {status} for word "{word}"', [
                    'status' => $statusCode,
                    'word' => $word,
                ]);
                return null;
            }

            $data = $response->toArray();

            // API returns {"title":"No Definitions Found","message":"..."} for unknown words
            if (isset($data['title']) && isset($data['message'])) {
                return null;
            }

            if (!\is_array($data) || !isset($data[0]) || !\is_array($data[0])) {
                return null;
            }

            $entry = $data[0];
            $wordText = $entry['word'] ?? $word;
            $phonetic = null;

            if (isset($entry['phonetic']) && $entry['phonetic'] !== '') {
                $phonetic = $entry['phonetic'];
            } elseif (isset($entry['phonetics']) && is_array($entry['phonetics'])) {
                foreach ($entry['phonetics'] as $p) {
                    if (!empty($p['text'])) {
                        $phonetic = $p['text'];
                        break;
                    }
                }
            }

            $definition = null;
            $partOfSpeech = null;
            $example = null;

            $meanings = $entry['meanings'] ?? [];
            foreach ($meanings as $meaning) {
                $defs = $meaning['definitions'] ?? [];
                foreach ($defs as $def) {
                    if (!empty($def['definition'])) {
                        $definition = trim($def['definition']);
                        $partOfSpeech = $meaning['partOfSpeech'] ?? null;
                        $example = isset($def['example']) ? trim($def['example']) : null;
                        break 2;
                    }
                }
            }

            if ($definition === null) {
                return null;
            }

            return [
                'word' => $wordText,
                'definition' => $definition,
                'phonetic' => $phonetic,
                'partOfSpeech' => $partOfSpeech,
                'example' => $example,
                'source' => 'free-dictionary',
            ];
        } catch (\Exception $e) {
            $this->logger?->warning('Free Dictionary API error for word "{word}": {message}', [
                'word' => $word,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return null;
        }
    }
}