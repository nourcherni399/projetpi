<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GroqSpellCheckService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un correcteur orthographique et grammatical pour le français.
Analyse le texte suivant et retourne un JSON strict avec exactement :
- "corrections" : liste d'objets { "original": "texte fautif", "suggestion": "correction" }
- "corrected_text" : le texte intégral corrigé (sans les fautes)

Si aucune faute : {"corrections": [], "corrected_text": "texte inchangé"}

Réponds UNIQUEMENT avec le JSON, sans markdown ni texte avant/après.
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
     * @return array{corrections: array<int, array{original: string, suggestion: string}>, corrected_text: string, error?: string}
     */
    public function check(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['corrections' => [], 'corrected_text' => '', 'error' => 'Texte vide.'];
        }

        if (!$this->isConfigured()) {
            return ['corrections' => [], 'corrected_text' => $text, 'error' => 'Clé API Groq non configurée.'];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => 'Texte à analyser :' . "\n\n" . $text],
                    ],
                    'max_tokens' => 2048,
                    'temperature' => 0.2,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseResponse($content, $text);
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = [];
            try {
                $body = $e->getResponse()->toArray(false);
            } catch (\Throwable) {
            }
            $apiMessage = $body['error']['message'] ?? $e->getMessage();

            return [
                'corrections' => [],
                'corrected_text' => $text,
                'error' => sprintf('Erreur API (%d) : %s', $statusCode, $apiMessage),
            ];
        } catch (\Throwable $e) {
            return [
                'corrections' => [],
                'corrected_text' => $text,
                'error' => 'Erreur : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{corrections: array<int, array{original: string, suggestion: string}>, corrected_text: string, error?: string}
     */
    private function parseResponse(string $content, string $fallbackText): array
    {
        $content = trim($content);

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $content, $m)) {
            $content = trim($m[1]);
        }
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $content = $m[0];
        }

        $decoded = json_decode($content, true);
        if (!\is_array($decoded)) {
            return [
                'corrections' => [],
                'corrected_text' => $fallbackText,
                'error' => 'Impossible de parser la réponse de l\'IA.',
            ];
        }

        $corrections = [];
        if (isset($decoded['corrections']) && \is_array($decoded['corrections'])) {
            foreach ($decoded['corrections'] as $item) {
                if (\is_array($item) && isset($item['original'], $item['suggestion'])) {
                    $corrections[] = [
                        'original' => (string) $item['original'],
                        'suggestion' => (string) $item['suggestion'],
                    ];
                }
            }
        }

        $correctedText = isset($decoded['corrected_text']) ? (string) $decoded['corrected_text'] : $fallbackText;

        return [
            'corrections' => $corrections,
            'corrected_text' => $correctedText,
        ];
    }
}
