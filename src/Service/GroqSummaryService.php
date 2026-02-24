<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GroqSummaryService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    private const PROMPT = <<<'PROMPT'
Résume le contenu pédagogique suivant en 4 phrases maximum. Sois clair et adapte le résumé aux accompagnants.
Ne rédige que le résumé, sans préambule ni conclusion.
Texte à résumer :

%s
PROMPT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && trim($this->apiKey) !== '';
    }

    /**
     * @return array{summary: ?string, error: ?string}
     */
    public function summarize(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['summary' => null, 'error' => 'Aucun texte à résumer.'];
        }
        if (!$this->isConfigured()) {
            return ['summary' => null, 'error' => 'Clé API Groq non configurée.'];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'user', 'content' => sprintf(self::PROMPT, $text)],
                    ],
                    'max_tokens' => 150,
                    'temperature' => 0.3,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? null;

            return [
                'summary' => \is_string($content) ? trim($content) : null,
                'error' => null,
            ];
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = [];
            try {
                $body = $e->getResponse()->toArray(false);
            } catch (\Throwable) {
            }
            $apiMessage = $body['error']['message'] ?? $e->getMessage();

            return ['summary' => null, 'error' => sprintf('Erreur API (%d) : %s', $statusCode, $apiMessage)];
        } catch (\Throwable $e) {
            return ['summary' => null, 'error' => 'Erreur : ' . $e->getMessage()];
        }
    }
}
