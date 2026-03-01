<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service pour analyser les images de produits avec l'IA (Groq LLaVA ou OpenAI GPT-4 Vision)
 */
final class ImageAnalysisService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?string $groqApiKey = null,
        private readonly ?string $openaiApiKey = null,
        private readonly ?string $geminiApiKey = null,
    ) {
    }

    /**
     * Analyse une image et retourne les informations du produit
     * 
     * @param string $imagePath Chemin local de l'image ou URL
     * @return array{success: bool, nom: ?string, description: ?string, categorie: ?string, image_keywords: ?string, error: ?string}
     */
    public function analyzeProductImage(string $imagePath): array
    {
        $this->logger->info('ImageAnalysisService: analyzing image', [
            'path' => $imagePath,
            'hasGeminiKey' => !empty($this->geminiApiKey),
            'hasOpenaiKey' => !empty($this->openaiApiKey),
            'hasGroqKey' => !empty($this->groqApiKey),
        ]);

        $errors = [];

        // PRIORITÉ 1: Google Gemini
        if (!empty($this->geminiApiKey)) {
            $result = $this->analyzeWithGemini($imagePath);
            if ($result['success']) {
                return $result;
            }
            $errors[] = 'Gemini: ' . ($result['error'] ?? 'unknown');
            $this->logger->warning('Gemini vision failed', ['error' => $result['error'] ?? 'unknown']);
        }

        // PRIORITÉ 2: OpenAI GPT-4 Vision
        if (!empty($this->openaiApiKey)) {
            $result = $this->analyzeWithOpenAI($imagePath);
            if ($result['success']) {
                return $result;
            }
            $errors[] = 'OpenAI: ' . ($result['error'] ?? 'unknown');
            $this->logger->warning('OpenAI vision failed', ['error' => $result['error'] ?? 'unknown']);
        }
        
        return [
            'success' => false,
            'nom' => null,
            'description' => null,
            'categorie' => null,
            'image_keywords' => null,
            'error' => !empty($errors) ? implode(' | ', $errors) : 'Aucune API de vision configurée. Ajoutez GEMINI_API_KEY dans .env.local',
        ];
    }

    /**
     * Analyse avec Google Gemini (gratuit et excellent pour la vision)
     */
    private function analyzeWithGemini(string $imagePath): array
    {
        try {
            $imageBase64 = $this->getImageBase64($imagePath);
            if (!$imageBase64) {
                return ['success' => false, 'error' => 'Impossible de lire l\'image'];
            }

            // Détecter le type MIME
            $mimeType = 'image/jpeg';
            if (str_contains($imagePath, '.png')) {
                $mimeType = 'image/png';
            } elseif (str_contains($imagePath, '.webp')) {
                $mimeType = 'image/webp';
            } elseif (str_contains($imagePath, '.gif')) {
                $mimeType = 'image/gif';
            }

            $prompt = <<<PROMPT
Analyse cette image de produit et réponds en JSON UNIQUEMENT avec ce format exact:
{
    "nom": "nom court du produit (2-4 mots)",
    "description": "description courte et simple, une phrase facile à comprendre (langage simple, pas de jargon)",
    "categorie": "une parmi: sensoriels, bien_etre_relaxation, jeux_therapeutiques_developpement, education_apprentissage, communication_langage, vie_quotidienne, bruit_et_environnement",
    "image_keywords": "2-3 mots clés en ANGLAIS pour rechercher ce produit (ex: plush toy blue)"
}
Réponds UNIQUEMENT avec le JSON, sans texte avant ou après.
PROMPT;

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $imageBase64,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 500,
                ],
            ];

            $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=' . $this->geminiApiKey;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->logger->info('Gemini API response', ['code' => $httpCode, 'response' => substr($response, 0, 500)]);

            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? 'Erreur API Gemini (code: ' . $httpCode . ')';
                return ['success' => false, 'error' => $errorMsg];
            }

            $data = json_decode($response, true);
            $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            return $this->parseAnalysisResponse($content);
        } catch (\Throwable $e) {
            $this->logger->error('Gemini Vision error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyse avec Together AI (Llama Vision)
     */
    private function analyzeWithTogether(string $imagePath): array
    {
        try {
            $imageBase64 = $this->getImageBase64($imagePath);
            if (!$imageBase64) {
                return ['success' => false, 'error' => 'Impossible de lire l\'image'];
            }

            // Détecter le type MIME
            $mimeType = 'image/jpeg';
            if (str_contains($imagePath, '.png')) {
                $mimeType = 'image/png';
            } elseif (str_contains($imagePath, '.webp')) {
                $mimeType = 'image/webp';
            }

            $prompt = <<<PROMPT
Analyse cette image de produit et réponds en JSON UNIQUEMENT avec ce format exact:
{
    "nom": "nom court du produit (2-4 mots)",
    "description": "description courte et simple, une phrase facile à comprendre (langage simple, pas de jargon)",
    "categorie": "une parmi: sensoriels, bien_etre_relaxation, jeux_therapeutiques_developpement, education_apprentissage, communication_langage, vie_quotidienne, bruit_et_environnement",
    "image_keywords": "2-3 mots clés en ANGLAIS pour rechercher ce produit (ex: plush toy blue)"
}
Réponds UNIQUEMENT avec le JSON, sans texte avant ou après.
PROMPT;

            $payload = [
                'model' => 'meta-llama/Llama-3.2-11B-Vision-Instruct-Turbo',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                        ],
                    ],
                ],
                'max_tokens' => 500,
                'temperature' => 0.3,
            ];

            $ch = curl_init('https://api.together.xyz/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->togetherApiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $this->logger->info('Together API response', ['code' => $httpCode, 'response' => substr($response, 0, 500)]);

            if ($httpCode !== 200) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? 'Erreur API Together (code: ' . $httpCode . ')';
                return ['success' => false, 'error' => $errorMsg];
            }

            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseAnalysisResponse($content);
        } catch (\Throwable $e) {
            $this->logger->error('Together Vision error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyse avec le modèle texte Groq (fallback si vision non disponible)
     */
    private function analyzeWithGroqText(string $productHint): array
    {
        try {
            $prompt = <<<PROMPT
À partir du nom de fichier ou indice suivant : "{$productHint}"
Devine le produit et réponds en JSON UNIQUEMENT avec ce format exact:
{
    "nom": "nom court du produit (2-4 mots)",
    "description": "description courte et simple, une phrase facile à comprendre (langage simple, pas de jargon)",
    "categorie": "une parmi: sensoriels, bien_etre_relaxation, jeux_therapeutiques_developpement, education_apprentissage, communication_langage, vie_quotidienne, bruit_et_environnement",
    "image_keywords": "2-3 mots clés en ANGLAIS pour rechercher ce produit (ex: plush toy blue)"
}
Réponds UNIQUEMENT avec le JSON, sans texte avant ou après.
PROMPT;

            $payload = [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500,
                'temperature' => 0.3,
            ];

            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->groqApiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->warning('Groq Text API error', ['code' => $httpCode, 'response' => $response]);
                return ['success' => false, 'error' => 'Erreur API Groq Text'];
            }

            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseAnalysisResponse($content);
        } catch (\Throwable $e) {
            $this->logger->error('Groq Text error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyse avec Groq LLaVA (modèle de vision gratuit)
     */
    private function analyzeWithGroq(string $imagePath): array
    {
        try {
            $imageBase64 = $this->getImageBase64($imagePath);
            if (!$imageBase64) {
                return ['success' => false, 'error' => 'Impossible de lire l\'image'];
            }

            $prompt = <<<PROMPT
Analyse cette image de produit et réponds en JSON UNIQUEMENT avec ce format exact:
{
    "nom": "nom court du produit (2-4 mots)",
    "description": "description courte et simple, une phrase facile à comprendre (langage simple, pas de jargon)",
    "categorie": "une parmi: sensoriels, bien_etre_relaxation, jeux_therapeutiques_developpement, education_apprentissage, communication_langage, vie_quotidienne, bruit_et_environnement",
    "image_keywords": "2-3 mots clés en ANGLAIS pour rechercher ce produit (ex: plush toy blue)"
}
Réponds UNIQUEMENT avec le JSON, sans texte avant ou après.
PROMPT;

            $payload = [
                'model' => 'llama-3.2-11b-vision-preview',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$imageBase64}"]],
                        ],
                    ],
                ],
                'max_tokens' => 500,
                'temperature' => 0.3,
            ];

            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->groqApiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->warning('Groq Vision API error', ['code' => $httpCode, 'response' => $response]);
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error']['message'] ?? 'Erreur API Groq (code: ' . $httpCode . ')';
                return ['success' => false, 'error' => $errorMsg];
            }

            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseAnalysisResponse($content);
        } catch (\Throwable $e) {
            $this->logger->error('Groq Vision error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyse avec OpenAI GPT-4 Vision
     */
    private function analyzeWithOpenAI(string $imagePath): array
    {
        try {
            $imageBase64 = $this->getImageBase64($imagePath);
            if (!$imageBase64) {
                return ['success' => false, 'error' => 'Impossible de lire l\'image'];
            }

            $prompt = <<<PROMPT
Analyse cette image de produit et réponds en JSON UNIQUEMENT avec ce format exact:
{
    "nom": "nom court du produit (2-4 mots)",
    "description": "description courte et simple, une phrase facile à comprendre (langage simple, pas de jargon)",
    "categorie": "une parmi: sensoriels, bien_etre_relaxation, jeux_therapeutiques_developpement, education_apprentissage, communication_langage, vie_quotidienne, bruit_et_environnement",
    "image_keywords": "2-3 mots clés en ANGLAIS pour rechercher ce produit (ex: plush toy blue)"
}
Réponds UNIQUEMENT avec le JSON, sans texte avant ou après.
PROMPT;

            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$imageBase64}"]],
                        ],
                    ],
                ],
                'max_tokens' => 500,
                'temperature' => 0.3,
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->openaiApiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $this->logger->warning('OpenAI Vision API error', ['code' => $httpCode, 'response' => $response]);
                return ['success' => false, 'error' => 'Erreur API OpenAI'];
            }

            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';

            return $this->parseAnalysisResponse($content);
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI Vision error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parse la réponse JSON de l'IA
     */
    private function parseAnalysisResponse(string $content): array
    {
        // Nettoyer le contenu (enlever les backticks markdown si présents)
        $content = trim($content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/i', '', $content);
        $content = trim($content);

        $data = json_decode($content, true);

        if (!is_array($data) || empty($data['nom'])) {
            $this->logger->warning('Invalid analysis response', ['content' => $content]);
            return [
                'success' => false,
                'error' => 'Réponse invalide de l\'IA',
            ];
        }

        return [
            'success' => true,
            'nom' => $data['nom'] ?? null,
            'description' => $data['description'] ?? null,
            'categorie' => $this->normalizeCategorie($data['categorie'] ?? 'vie_quotidienne'),
            'image_keywords' => $data['image_keywords'] ?? null,
            'error' => null,
        ];
    }

    /**
     * Convertit une image en base64
     */
    private function getImageBase64(string $imagePath): ?string
    {
        try {
            // Si c'est une URL
            if (str_starts_with($imagePath, 'http')) {
                $content = @file_get_contents($imagePath);
                if ($content === false) {
                    return null;
                }
                return base64_encode($content);
            }

            // Si c'est un chemin local
            if (!file_exists($imagePath)) {
                // Essayer avec le préfixe public/
                if (file_exists('public/' . $imagePath)) {
                    $imagePath = 'public/' . $imagePath;
                } else {
                    return null;
                }
            }

            $content = file_get_contents($imagePath);
            if ($content === false) {
                return null;
            }

            return base64_encode($content);
        } catch (\Throwable $e) {
            $this->logger->error('Error reading image', ['path' => $imagePath, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function normalizeCategorie(string $value): string
    {
        $value = mb_strtolower(trim($value));
        
        $mapping = [
            'sensoriels' => 'sensoriels',
            'sensoriel' => 'sensoriels',
            'bruit' => 'bruit_et_environnement',
            'bruit_et_environnement' => 'bruit_et_environnement',
            'education' => 'education_apprentissage',
            'éducation' => 'education_apprentissage',
            'education_apprentissage' => 'education_apprentissage',
            'communication' => 'communication_langage',
            'communication_langage' => 'communication_langage',
            'jeux' => 'jeux_therapeutiques_developpement',
            'jeux_therapeutiques_developpement' => 'jeux_therapeutiques_developpement',
            'bien_etre' => 'bien_etre_relaxation',
            'bien_etre_relaxation' => 'bien_etre_relaxation',
            'relaxation' => 'bien_etre_relaxation',
            'detente' => 'bien_etre_relaxation',
            'détente' => 'bien_etre_relaxation',
            'vie_quotidienne' => 'vie_quotidienne',
            'quotidien' => 'vie_quotidienne',
        ];

        return $mapping[$value] ?? 'vie_quotidienne';
    }
}