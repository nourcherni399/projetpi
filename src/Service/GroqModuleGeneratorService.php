<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GroqModuleGeneratorService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un expert pédagogique spécialisé dans l'autisme et l'accompagnement. Génère un module éducatif au format JSON strict avec exactement ces clés : "titre", "description", "contenu".
- titre : court, accrocheur, max 255 caractères
- description : 1 à 2 phrases résumant le module, max 255 caractères
- contenu : texte détaillé pédagogique adapté à la catégorie, plusieurs paragraphes
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
     * @return array{titre: string, description: string, contenu: string, error?: string}
     */
    public function generate(string $prompt, string $categorieLabel): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['titre' => '', 'description' => '', 'contenu' => '', 'error' => 'Veuillez saisir un prompt.'];
        }

        if (!$this->isConfigured()) {
            return ['titre' => '', 'description' => '', 'contenu' => '', 'error' => 'Clé API Groq non configurée.'];
        }

        $userPrompt = sprintf(
            "Catégorie du module : %s\n\nSujet ou instructions : %s",
            $categorieLabel !== '' ? $categorieLabel : 'Général',
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
                'description' => '',
                'contenu' => '',
                'error' => sprintf('Erreur API (%d) : %s', $statusCode, $apiMessage),
            ];
        } catch (\Throwable $e) {
            return [
                'titre' => '',
                'description' => '',
                'contenu' => '',
                'error' => 'Erreur : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{titre: string, description: string, contenu: string, error?: string}
     */
    private function parseResponse(string $content): array
    {
        $content = trim($content);

        // Extraire le JSON (potentiellement entouré de markdown ```json ... ```)
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
                'description' => '',
                'contenu' => '',
                'error' => 'Impossible de parser la réponse de l\'IA.',
            ];
        }

        $titre = isset($decoded['titre']) ? (string) $decoded['titre'] : '';
        $description = isset($decoded['description']) ? (string) $decoded['description'] : '';
        $contenu = isset($decoded['contenu']) ? (string) $decoded['contenu'] : '';

        // Tronquer si nécessaire (contraintes du module)
        $titre = mb_substr($titre, 0, 255);
        $description = mb_substr($description, 0, 255);

        return [
            'titre' => $titre,
            'description' => $description,
            'contenu' => $contenu,
        ];
    }
}