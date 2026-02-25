<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * Appel à l'API Google Gemini (gratuite avec quota).
 * Clé API : https://aistudio.google.com/app/apikey
 */
final class GeminiChatService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Envoie un message et retourne la réponse de l'IA.
     *
     * @param string $userMessage Message de l'utilisateur
     * @param array<int, array{role: string, content: string}> $history Historique [['role' => 'user'|'model', 'content' => '...'], ...]
     */
    public function chat(string $userMessage, array $history = []): string
    {
        if ($this->apiKey === '' || $this->apiKey === '0') {
            return 'Le chatbot n’est pas configuré. Ajoutez GEMINI_API_KEY dans votre fichier .env (clé gratuite : https://aistudio.google.com/app/apikey).';
        }

        $systemPrompt = 'Tu es l’assistant virtuel d’AutiCare, une plateforme d’écoute, d’aide et d’accompagnement. '
            . 'Réponds en français, avec bienveillance et clarté. Reste concis. '
            . 'Pour les questions médicales ou de santé, invite à consulter un professionnel.';

        // Format attendu par Gemini REST : contents avec "parts" (et optionnellement "role" en multi-turn)
        $contents = [];
        if ($history === []) {
            $contents[] = [
                'parts' => [['text' => $systemPrompt . "\n\nMessage de l'utilisateur : " . $userMessage]],
            ];
        } else {
            foreach ($history as $item) {
                $role = $item['role'] === 'user' ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $item['content']]],
                ];
            }
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $userMessage]],
            ];
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL . '?key=' . $this->apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => 1024,
                        'temperature' => 0.7,
                    ],
                ],
            ]);

            $data = $response->toArray();

            $candidate = $data['candidates'][0] ?? null;
            if (!$candidate) {
                $reason = $data['promptFeedback']['blockReason'] ?? $data['error']['message'] ?? 'réponse vide';
                $reason = \is_string($reason) ? $reason : json_encode($reason);
                $this->logger?->warning('Gemini API: pas de candidat', ['response' => $data]);
                return 'Désolé, la requête n’a pas pu être traitée (' . $reason . '). Réessayez.';
            }
            $text = $candidate['content']['parts'][0]['text'] ?? null;
            if ($text === null || $text === '') {
                $this->logger?->warning('Gemini API: réponse sans texte', ['response' => $data]);
                return 'Désolé, la réponse reçue est vide. Réessayez.';
            }

            return trim($text);
        } catch (ExceptionInterface $e) {
            $detail = '';
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                try {
                    $body = $e->getResponse()->getContent(false);
                    $decoded = json_decode($body, true);
                    if (isset($decoded['error']['message'])) {
                        $detail = $decoded['error']['message'];
                    } elseif (\is_string($body)) {
                        $detail = substr($body, 0, 200);
                    }
                } catch (\Throwable) {
                }
            }
            $this->logger?->error('Gemini API error', [
                'message' => $e->getMessage(),
                'detail' => $detail ?: null,
            ]);

            if ($detail !== '') {
                if (str_contains($detail, 'API key') || str_contains($detail, 'invalid') || str_contains($detail, '403')) {
                    return 'Clé API invalide ou expirée. Vérifiez GEMINI_API_KEY dans .env (créez une clé sur https://aistudio.google.com/app/apikey).';
                }
                if (str_contains($detail, '429') || str_contains($detail, 'quota') || str_contains($detail, 'Resource has been exhausted')) {
                    return 'Quota gratuit dépassé. Réessayez plus tard.';
                }
            }

            return 'Désolé, une erreur s’est produite. Réessayez dans un instant.';
        }
    }
}
