<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Génération d'images à partir d'un texte via Hugging Face (gratuit).
 * Token : https://huggingface.co/settings/tokens (permission "Inference")
 */
final class HuggingFaceImageService
{
    // API Inference (gratuit). Modèle v1-5 souvent disponible sans file d’attente.
    private const MODEL = 'runwayml/stable-diffusion-v1-5';
    private const API_URL = 'https://api-inference.huggingface.co/models/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiToken,
        private string $uploadDir
    ) {
    }

    public function isConfigured(): bool
    {
        $t = trim($this->apiToken);
        return $t !== '' && $t !== '0';
    }

    /**
     * Génère une image à partir du prompt, la sauvegarde et retourne l'URL publique.
     * @return array{success: bool, image_url: string|null, error: string|null}
     */
    public function generate(string $prompt): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'image_url' => null,
                'error' => 'Génération d’images non configurée. Ajoutez HF_TOKEN dans .env (https://huggingface.co/settings/tokens, permission Inference).',
            ];
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['success' => false, 'image_url' => null, 'error' => 'Décrivez l’image à générer.'];
        }

        // Pour un contexte TSA : on peut préfixer pour des images apaisantes et adaptées.
        // Pour le quiz Rorschach (inkblot), ne pas ajouter "style illustration" pour garder des taches abstraites.
        $fullPrompt = $prompt;
        $lower = mb_strtolower($prompt);
        if (!str_contains($lower, 'enfant') && !str_contains($lower, 'personne') && !str_contains($lower, 'inkblot') && !str_contains($lower, 'rorschach')) {
            $fullPrompt = 'Image douce et bienveillante, style illustration, ' . $prompt;
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL . self::MODEL, [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . trim($this->apiToken),
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode(['inputs' => $fullPrompt]),
            ]);

            $content = $response->getContent();
            $contentType = $response->getHeaders()['content-type'][0] ?? '';

            if (str_contains($contentType, 'application/json')) {
                $data = json_decode($content, true);
                $error = $data['error'] ?? $content;
                $msg = \is_string($error) ? $error : json_encode($error);
                if (stripos($msg, 'loading') !== false) {
                    $sec = $data['estimated_time'] ?? 30;
                    $msg = 'Le modèle est en cours de chargement. Réessayez dans ' . (int) $sec . ' secondes.';
                }
                return ['success' => false, 'image_url' => null, 'error' => $msg];
            }

            if (strlen($content) < 100) {
                return ['success' => false, 'image_url' => null, 'error' => 'Réponse trop courte de l’API. Réessayez dans un instant (modèle peut être en chargement).'];
            }

            $dir = rtrim($this->uploadDir, '/\\');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $filename = 'tsa_' . uniqid('', true) . '.png';
            $path = $dir . '/' . $filename;
            file_put_contents($path, $content);

            return [
                'success' => true,
                'image_url' => '/uploads/tsa_generated/' . $filename,
                'error' => null,
            ];
        } catch (ExceptionInterface $e) {
            $detail = '';
            $statusCode = null;
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                try {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $body = $e->getResponse()->getContent(false);
                    $decoded = json_decode($body, true);
                    $detail = \is_string($decoded['error'] ?? null) ? $decoded['error'] : substr($body, 0, 200);
                } catch (\Throwable) {
                }
            }
            $msg = $detail !== '' ? $detail : $e->getMessage();
            if ($statusCode === 404 || stripos($msg, 'not found') !== false || stripos($msg, 'no longer supported') !== false) {
                $msg = 'L’API Hugging Face a changé. Vérifiez que HF_TOKEN a la permission « Inference » (pas seulement « Inference Providers ») sur https://huggingface.co/settings/tokens. Si le problème continue, la génération d’images gratuite peut être temporairement indisponible.';
            }
            return ['success' => false, 'image_url' => null, 'error' => $msg];
        }
    }
}
