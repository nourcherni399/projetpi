<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * Chat via l'API Groq (gratuit, rapide, Llama).
 * Clé API : https://console.groq.com/keys
 */
final class GroqChatService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.1-8b-instant';
    private const VISION_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function isConfigured(): bool
    {
        $key = trim($this->apiKey);
        return $key !== '' && $key !== '0';
    }

    /**
     * @param array<int, array{role: string, content: string}> $history
     */
    public function chat(string $userMessage, array $history = [], ?string $systemPrompt = null): string
    {
        if (!$this->isConfigured()) {
            return 'Le chatbot n’est pas configuré. Ajoutez GROQ_API_KEY dans .env (clé gratuite : https://console.groq.com/keys).';
        }

        $messages = [];
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($history as $item) {
            $role = $item['role'] === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $item['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . trim($this->apiKey),
                ],
                'json' => [
                    'model' => self::MODEL,
                    'messages' => $messages,
                    'max_tokens' => 1024,
                    'temperature' => 0.7,
                ],
            ]);

            $data = $response->toArray();
            $text = $data['choices'][0]['message']['content'] ?? null;

            if ($text === null || $text === '') {
                $this->logger?->warning('Groq API: réponse vide', ['response' => $data]);
                return 'Désolé, la réponse reçue est vide. Réessayez.';
            }

            return trim($text);
        } catch (ExceptionInterface $e) {
            $detail = $this->extractErrorDetail($e);
            $this->logger?->error('Groq API error', ['message' => $e->getMessage(), 'detail' => $detail]);

            if ($detail !== '' && (str_contains($detail, 'API key') || str_contains($detail, 'invalid') || str_contains($detail, '401'))) {
                return 'Clé API invalide. Vérifiez GROQ_API_KEY dans .env (https://console.groq.com/keys).';
            }
            if ($detail !== '' && (str_contains($detail, '429') || str_contains($detail, 'rate limit'))) {
                return 'Limite gratuite atteinte. Réessayez dans une minute.';
            }

            return 'Désolé, une erreur s’est produite. Réessayez dans un instant.';
        }
    }

    /**
     * Chat avec analyse d'image (vision). Utilise un modèle multimodale pour décrire précisément la photo.
     *
     * @param array<int, array{role: string, content: string}> $history
     */
    public function chatWithVision(string $userMessage, array $history, ?string $systemPrompt, string $imageBase64): string
    {
        if (!$this->isConfigured()) {
            return "Le chatbot n'est pas configuré. Ajoutez GROQ_API_KEY dans .env.";
        }

        $visionHint = "\n\nIMPORTANT : Quand on te montre une photo, décris PRÉCISÉMENT ce que tu vois (personne(s), posture, vêtements, expression ou attitude si visible, couleurs, contexte). Ne devine pas : base-toi uniquement sur ce qui est visible. Si le visage n'est pas visible, ne dis pas qu'il sourit.";
        $messages = [];
        if ($systemPrompt !== null && $systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt . $visionHint];
        }
        foreach ($history as $item) {
            $role = $item['role'] === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $item['content']];
        }

        $userContent = [
            ['type' => 'text', 'text' => $userMessage],
            ['type' => 'image_url', 'image_url' => ['url' => $imageBase64]],
        ];
        $messages[] = ['role' => 'user', 'content' => $userContent];

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . trim($this->apiKey),
                ],
                'json' => [
                    'model' => self::VISION_MODEL,
                    'messages' => $messages,
                    'max_tokens' => 1024,
                    'temperature' => 0.3,
                ],
            ]);

            $data = $response->toArray();
            $text = $data['choices'][0]['message']['content'] ?? null;

            if ($text === null || $text === '') {
                $this->logger?->warning('Groq Vision API: réponse vide', ['response' => $data]);
                return "Désolé, je n'ai pas pu analyser l'image. Réessayez.";
            }

            return trim($text);
        } catch (ExceptionInterface $e) {
            $detail = $this->extractErrorDetail($e);
            $this->logger?->error('Groq Vision API error', ['message' => $e->getMessage(), 'detail' => $detail]);
            return "Désolé, l'analyse d'image a échoué. Réessayez ou envoyez une image plus petite (max 3,5 Mo).";
        }
    }

    private function extractErrorDetail(ExceptionInterface $e): string
    {
        if (!method_exists($e, 'getResponse') || !$e->getResponse()) {
            return '';
        }
        try {
            $body = $e->getResponse()->getContent(false);
            $decoded = json_decode($body, true);
            return \is_string($decoded['error']['message'] ?? null) ? $decoded['error']['message'] : substr((string) $body, 0, 200);
        } catch (\Throwable) {
            return '';
        }
    }
}
