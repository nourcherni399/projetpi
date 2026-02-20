<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\HuggingFaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/api/ai', name: 'admin_api_ai_')]
#[IsGranted('ROLE_ADMIN')]
final class AiController extends AbstractController
{
    public function __construct(
        private readonly HuggingFaceService $huggingFace,
    ) {
    }

    /**
     * Diagnostic : indique si la clé Hugging Face est détectée (GET, admin uniquement).
     */
    #[Route('/debug-key', name: 'debug_key', methods: ['GET'])]
    public function debugKey(): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $envLocal = $projectDir . \DIRECTORY_SEPARATOR . '.env.local';
        $env = $projectDir . \DIRECTORY_SEPARATOR . '.env';
        return new JsonResponse([
            'hasKey' => $this->huggingFace->hasApiKey(),
            'projectDir' => $projectDir,
            'env_local_exists' => is_file($envLocal),
            'env_exists' => is_file($env),
        ]);
    }

    /**
     * Vérifie que la clé Hugging Face permet bien la génération d'images (GET, admin uniquement).
     * Vous n'avez pas besoin de créer une autre clé : cette URL teste celle du .env.
     */
    #[Route('/verify-image-key', name: 'verify_image_key', methods: ['GET'])]
    public function verifyImageKey(): JsonResponse
    {
        if (!$this->huggingFace->hasApiKey()) {
            return new JsonResponse([
                'configured' => false,
                'valid' => false,
                'message' => 'Aucune clé dans .env. Ajoutez HUGGINGFACE_API_KEY=votre_token (sans # devant la ligne), puis : php bin/console cache:clear',
            ]);
        }
        $result = $this->huggingFace->testImageKey();
        return new JsonResponse([
            'configured' => true,
            'valid' => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    /**
     * Résume le texte envoyé en JSON { "text": "..." }.
     */
    #[Route('/summarize', name: 'summarize', methods: ['POST'])]
    public function summarize(Request $request): JsonResponse
    {
        try {
            $data = $this->decodeJson($request);
            $text = isset($data['text']) && \is_string($data['text']) ? trim($data['text']) : '';
            if ($text === '') {
                return new JsonResponse(['error' => 'Texte manquant.'], Response::HTTP_BAD_REQUEST);
            }
            $summary = $this->huggingFace->summarize($text);
            if ($summary === null) {
                $err = $this->huggingFace->getLastApiError();
                $msg = 'Résumé indisponible.';
                if ($err !== null && isset($err['message']) && $err['message'] !== '') {
                    $errMsg = \is_array($err['message']) ? (json_encode($err['message'], \JSON_UNESCAPED_UNICODE) ?: 'Erreur inconnue') : (string) $err['message'];
                    $msg = 'Hugging Face : ' . $errMsg;
                    if (($err['status'] ?? 0) === 401) {
                        $msg = 'Token invalide ou révoqué. Créez un nouveau token sur huggingface.co/settings/tokens avec la permission "Make calls to Inference Providers".';
                    } elseif (($err['status'] ?? 0) === 503) {
                        $msg = 'Modèle en chargement. ' . ($errMsg ?: 'Réessayez dans 20–30 secondes.');
                    }
                } elseif (!$this->huggingFace->hasApiKey()) {
                    $msg = 'Ajoutez HUGGINGFACE_API_KEY dans .env ou .env.local.';
                } else {
                    $msg = 'L\'API n\'a pas répondu. Vérifiez le token et la permission "Inference".';
                }
                return new JsonResponse(['error' => $msg], Response::HTTP_SERVICE_UNAVAILABLE);
            }
            return new JsonResponse(['summary' => $summary]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Erreur serveur : ' . $e->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Analyse du texte envoyé en JSON.
     * Body: { "text": "...", "context": "optionnel", "mode": "sentiment"|"category" }.
     * - text: obligatoire.
     * - context: optionnel, précise le cadre (ex. "Message participant événement famille") pour personnaliser l'analyse.
     * - mode: "sentiment" (défaut) = positif/négatif/neutre ; "category" = type de message (question_pratique, demande_info, remerciement, inquiétude, demande_modification, autre).
     */
    #[Route('/sentiment', name: 'sentiment', methods: ['POST'])]
    public function sentiment(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $text = isset($data['text']) && \is_string($data['text']) ? trim($data['text']) : '';
        if ($text === '') {
            return new JsonResponse(['error' => 'Texte manquant.'], Response::HTTP_BAD_REQUEST);
        }
        $context = isset($data['context']) && \is_string($data['context']) ? trim($data['context']) : null;
        $mode = isset($data['mode']) && \is_string($data['mode']) ? strtolower(trim($data['mode'])) : 'sentiment';

        if ($mode === 'category') {
            $result = $this->huggingFace->getMessageCategory($text, $context);
        } else {
            $result = $this->huggingFace->getSentiment($text, $context);
        }

        if ($result === []) {
            $msg = $this->huggingFace->hasApiKey()
                ? 'L\'API Hugging Face n\'a pas répondu. Vérifiez le token et la permission "Inference".'
                : 'Analyse indisponible. Ajoutez HUGGINGFACE_API_KEY dans .env ou .env.local.';
            return new JsonResponse(['error' => $msg], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $label = $result['label'] ?? ($mode === 'category' ? 'autre' : 'neutral');
        $labelFr = $mode === 'category'
            ? match ($label) {
                'question_pratique' => 'Question pratique',
                'demande_info' => 'Demande d\'info',
                'remerciement' => 'Remerciement',
                'inquiétude' => 'Inquiétude',
                'demande_modification' => 'Demande de modification',
                default => 'Autre',
            }
            : match ($label) {
                'positive' => 'positif',
                'negative' => 'négatif',
                default => 'neutre',
            };

        return new JsonResponse([
            'label' => $label,
            'label_fr' => $labelFr,
            'score' => $result['score'] ?? 0,
            'mode' => $mode === 'category' ? 'category' : 'sentiment',
        ]);
    }

    /**
     * Suggère une réponse à un message participant. Body: { "message": "..." }.
     */
    #[Route('/suggest-reply', name: 'suggest_reply', methods: ['POST'])]
    public function suggestReply(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $message = isset($data['message']) && \is_string($data['message']) ? trim($data['message']) : '';
        if ($message === '') {
            return new JsonResponse(['error' => 'Message manquant.'], Response::HTTP_BAD_REQUEST);
        }
        $suggestion = $this->huggingFace->suggestReply($message);
        if ($suggestion === null) {
            $err = $this->huggingFace->getLastApiError();
            return new JsonResponse([
                'error' => $err !== null && isset($err['message']) ? $err['message'] : 'Impossible de générer une suggestion.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        return new JsonResponse(['suggestion' => $suggestion]);
    }

    /**
     * Suggère une réponse en FALC (Facile à Lire et à Comprendre). Body: { "message": "..." }.
     */
    #[Route('/suggest-reply-falc', name: 'suggest_reply_falc', methods: ['POST'])]
    public function suggestReplyFalc(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $message = isset($data['message']) && \is_string($data['message']) ? trim($data['message']) : '';
        if ($message === '') {
            return new JsonResponse(['error' => 'Message manquant.'], Response::HTTP_BAD_REQUEST);
        }
        $suggestion = $this->huggingFace->suggestReplyFALC($message);
        if ($suggestion === null) {
            $err = $this->huggingFace->getLastApiError();
            return new JsonResponse([
                'error' => $err !== null && isset($err['message']) ? $err['message'] : 'Impossible de générer une suggestion FALC.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        return new JsonResponse(['suggestion' => $suggestion]);
    }

    /**
     * Détection des besoins d'adaptation (indices discrets pour l'admin). Body: { "text": "..." }.
     */
    #[Route('/detect-adaptation-needs', name: 'detect_adaptation_needs', methods: ['POST'])]
    public function detectAdaptationNeeds(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $text = isset($data['text']) && \is_string($data['text']) ? trim($data['text']) : '';
        if ($text === '') {
            return new JsonResponse(['error' => 'Texte manquant.'], Response::HTTP_BAD_REQUEST);
        }
        $result = $this->huggingFace->detectAdaptationNeeds($text);
        $labelsFr = [
            'debutant' => 'Débutant',
            'rassurer_cadre' => 'Rassurer sur le cadre',
            'sensoriel' => 'Sensoriel',
            'anxiete' => 'Anxiété',
            'horaire' => 'Préférence horaire',
            'calme' => 'Besoin de calme',
            'autre' => 'Autre',
        ];
        $labelsDisplay = array_map(static fn ($k) => $labelsFr[$k] ?? $k, $result['labels']);
        return new JsonResponse([
            'labels' => $result['labels'],
            'labels_fr' => $labelsDisplay,
            'hint' => $result['hint'],
        ]);
    }

    /**
     * Analyse complète en un coup : sentiment + type + recommandation. Body: { "text": "..." }.
     */
    #[Route('/analyze-complete', name: 'analyze_complete', methods: ['POST'])]
    public function analyzeComplete(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $text = isset($data['text']) && \is_string($data['text']) ? trim($data['text']) : '';
        if ($text === '') {
            return new JsonResponse(['error' => 'Texte manquant.'], Response::HTTP_BAD_REQUEST);
        }
        $result = $this->huggingFace->analyzeMessageComplete($text);
        if ($result === []) {
            $err = $this->huggingFace->getLastApiError();
            return new JsonResponse([
                'error' => $err !== null && isset($err['message']) ? $err['message'] : 'Analyse indisponible.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $sentimentFr = match ($result['sentiment'] ?? 'neutral') {
            'positive' => 'Positif',
            'negative' => 'Négatif',
            default => 'Neutre',
        };
        $categoryFr = match ($result['category'] ?? 'autre') {
            'question_pratique' => 'Question pratique',
            'demande_info' => 'Demande d\'info',
            'remerciement' => 'Remerciement',
            'inquiétude' => 'Inquiétude',
            'demande_modification' => 'Demande de modification',
            default => 'Autre',
        };
        return new JsonResponse([
            'sentiment' => $result['sentiment'],
            'sentiment_fr' => $sentimentFr,
            'category' => $result['category'],
            'category_fr' => $categoryFr,
            'recommandation' => $result['recommandation'] ?? '',
        ]);
    }

    /**
     * Suggestion de description à partir de lieu, thème, date. JSON: { "lieu", "thematique", "date" }.
     */
    #[Route('/suggest-description', name: 'suggest_description', methods: ['POST'])]
    public function suggestDescription(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $lieu = isset($data['lieu']) && \is_string($data['lieu']) ? trim($data['lieu']) : '';
        $thematique = isset($data['thematique']) && \is_string($data['thematique']) ? trim($data['thematique']) : '';
        $date = isset($data['date']) && \is_string($data['date']) ? trim($data['date']) : '';
        $suggestion = $this->huggingFace->suggestDescription($lieu, $thematique, $date);
        return new JsonResponse(['suggestion' => $suggestion]);
    }

    private function decodeJson(Request $request): array
    {
        try {
            $content = $request->getContent();
            if ($content === '' || $content === '{}') {
                return [];
            }
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            return \is_array($data) ? $data : [];
        } catch (\JsonException) {
            return [];
        }
    }
}
