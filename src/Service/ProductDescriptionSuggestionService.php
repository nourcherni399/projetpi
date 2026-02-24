<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Suggère une description de produit à partir du nom via Groq.
 */
final class ProductDescriptionSuggestionService
{
    public function __construct(
        private readonly ?string $groqApiKey = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return !empty($this->groqApiKey);
    }

    public function suggestDescription(string $nom): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $nom = trim($nom);
        if ($nom === '') {
            return null;
        }

        $prompt = <<<PROMPT
Pour le produit e-commerce suivant, génère UNE SEULE phrase de description courte et simple, facile à comprendre.
Nom du produit : {$nom}

Règles :
- Une phrase seulement (max 15-20 mots)
- Langage simple, pas de jargon
- Explique à quoi sert le produit de façon claire
- Réponds UNIQUEMENT avec la description, sans guillemets ni préambule
PROMPT;

        $payload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 100,
            'temperature' => 0.5,
        ];

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->groqApiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return null;
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null || !is_string($content)) {
            return null;
        }

        return trim($content);
    }
}
