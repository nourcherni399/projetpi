<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GroqHashtagGeneratorService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    private const PROMPT = <<<'PROMPT'
À partir de ce titre d'article sur l'autisme et l'accompagnement, génère 5 à 8 hashtags pertinents au format #mot.
Réponds UNIQUEMENT avec les hashtags séparés par des espaces, sans autre texte.
Exemple de réponse : #autisme #TSA #pictogrammes #témoignage #accompagnement

Titre : %s
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
     * @return array{hashtags: ?string, error: ?string}
     */
    public function generateFromTitle(string $titre): array
    {
        $titre = trim($titre);
        if ($titre === '') {
            return ['hashtags' => null, 'error' => 'Veuillez saisir un titre.'];
        }

        if (!$this->isConfigured()) {
            return ['hashtags' => null, 'error' => 'Clé API Groq non configurée.'];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'user', 'content' => sprintf(self::PROMPT, $titre)],
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
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            if ($content === '') {
                return ['hashtags' => null, 'error' => 'Aucun hashtag généré.'];
            }

            // Normaliser la réponse : s'assurer que les mots ont le format #mot
            $hashtags = preg_replace('/\s+/', ' ', $content);
            $hashtags = preg_replace('/#\s+/', '#', $hashtags);

            return ['hashtags' => $hashtags, 'error' => null];
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = [];
            try {
                $body = $e->getResponse()->toArray(false);
            } catch (\Throwable) {
            }
            $apiMessage = $body['error']['message'] ?? $e->getMessage();

            return [
                'hashtags' => null,
                'error' => sprintf('Erreur API (%d) : %s', $statusCode, $apiMessage),
            ];
        } catch (\Throwable $e) {
            return [
                'hashtags' => null,
                'error' => 'Erreur : ' . $e->getMessage(),
            ];
        }
    }
}
