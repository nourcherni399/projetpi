<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GroqBlogGeneratorService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    private const TYPE_LABELS = [
        'recommandation' => 'Recommandation',
        'plainte' => 'Plainte',
        'question' => 'Question',
        'experience' => 'Expérience',
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un expert en accompagnement des personnes avec autisme (TSA) et leurs familles. Génère un article de blog au format JSON strict avec exactement ces clés : "titre", "contenu".
- titre : court, accrocheur, adapté au type d'article, max 255 caractères
- contenu : texte développé adapté au type (recommandation / plainte / question / expérience), plusieurs paragraphes, ton bienveillant
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
     * @return array{titre: string, contenu: string, error?: string}
     */
    public function generate(string $prompt, string $type): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['titre' => '', 'contenu' => '', 'error' => 'Veuillez saisir un prompt.'];
        }

        if (!$this->isConfigured()) {
            return ['titre' => '', 'contenu' => '', 'error' => 'Clé API Groq non configurée.'];
        }

        $typeLabel = self::TYPE_LABELS[$type] ?? $type ?: 'Général';

        $userPrompt = sprintf(
            "Type d'article : %s\n\nSujet ou instructions : %s",
            $typeLabel,
            $prompt
        );

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'max_tokens' => 1500,
                    'temperature' => 0.5,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 60,
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseResponse($content);
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = [];
            try {
                $body = $e->getResponse()->toArray(false);
            } catch (\Throwable) {
            }
            $apiMessage = $body['error']['message'] ?? $e->getMessage();

            return [
                'titre' => '',
                'contenu' => '',
                'error' => sprintf('Erreur API (%d) : %s', $statusCode, $apiMessage),
            ];
        } catch (\Throwable $e) {
            return [
                'titre' => '',
                'contenu' => '',
                'error' => 'Erreur : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{titre: string, contenu: string, error?: string}
     */
    private function parseResponse(string $content): array
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
                'titre' => '',
                'contenu' => '',
                'error' => 'Impossible de parser la réponse de l\'IA.',
            ];
        }

        $titre = isset($decoded['titre']) ? (string) $decoded['titre'] : '';
        $contenu = isset($decoded['contenu']) ? (string) $decoded['contenu'] : '';

        $titre = mb_substr($titre, 0, 255);

        return [
            'titre' => $titre,
            'contenu' => $contenu,
        ];
    }
}
