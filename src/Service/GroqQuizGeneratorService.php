<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Module;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GroqQuizGeneratorService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL = 'llama-3.3-70b-versatile';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un expert pédagogique en autisme et TSA. Génère un quiz de validation pour un module d'apprentissage.
Règles :
- Génère entre 5 et 8 questions
- Chaque question a exactement 4 réponses (une seule correcte)
- Les questions doivent tester la compréhension réelle du contenu fourni
- Réponds UNIQUEMENT en JSON valide, sans markdown
- Structure : { "questions": [ { "question": "...", "reponses": ["A","B","C","D"], "bonneReponse": 0 } ] }
- bonneReponse est l'index (0 à 3) de la bonne réponse
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
     * @return array{questions: array<int, array{question: string, reponses: array<int, string>, bonneReponse: int}>}|array{error: string}
     */
    public function generateForModule(Module $module): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Clé API Groq non configurée.'];
        }

        $contentToUse = implode("\n\n", array_filter([
            'TITRE : ' . ($module->getTitre() ?? ''),
            'DESCRIPTION : ' . ($module->getDescription() ?? ''),
            'CONTENU : ' . ($module->getContenu() ?? ''),
        ]));

        $contentToUse = trim($contentToUse);
        if (mb_strlen($contentToUse) < 50) {
            return ['error' => 'Le module n\'a pas assez de contenu pour générer un quiz.'];
        }

        $userPrompt = "Génère un quiz de validation basé sur ce module :\n\n" . $contentToUse;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => [
                    'model' => self::MODEL,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.6,
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
                'error' => sprintf('Erreur API (%d) : %s', $statusCode, $apiMessage),
            ];
        } catch (\Throwable $e) {
            return [
                'error' => 'Erreur : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{questions: array<int, array{question: string, reponses: array<int, string>, bonneReponse: int}>}|array{error: string}
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
            return ['error' => 'Impossible de parser la réponse de l\'IA.'];
        }

        $questions = $decoded['questions'] ?? [];
        if (!\is_array($questions) || count($questions) < 3) {
            return ['error' => 'Le quiz généré ne contient pas assez de questions valides.'];
        }

        $validQuestions = [];
        foreach ($questions as $q) {
            if (
                isset($q['question'], $q['reponses'], $q['bonneReponse'])
                && \is_string($q['question'])
                && \is_array($q['reponses'])
                && count($q['reponses']) >= 2
                && \is_int($q['bonneReponse'])
                && $q['bonneReponse'] >= 0
                && $q['bonneReponse'] < count($q['reponses'])
            ) {
                $validQuestions[] = [
                    'question' => $q['question'],
                    'reponses' => array_values(array_map('strval', $q['reponses'])),
                    'bonneReponse' => $q['bonneReponse'],
                ];
            }
        }

        if (count($validQuestions) < 3) {
            return ['error' => 'Le quiz généré ne contient pas assez de questions valides.'];
        }

        return ['questions' => $validQuestions];
    }
}
