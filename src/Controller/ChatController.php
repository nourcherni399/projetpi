<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HuggingFaceImageService;
use App\Service\TsaChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ChatController extends AbstractController
{
    public function __construct(
        private TsaChatService $tsaChat,
        private HuggingFaceImageService $hfImage
    ) {
    }

    #[Route('/api/chat', name: 'api_chat', methods: ['POST'])]
    public function api(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $message = trim((string) ($data['message'] ?? ''));
        $history = is_array($data['history'] ?? null) ? $data['history'] : [];
        $imageBase64 = isset($data['image_base64']) && \is_string($data['image_base64']) ? $data['image_base64'] : null;

        if ($message === '' && $imageBase64 === null) {
            return new JsonResponse(['reply' => 'Veuillez écrire un message ou joindre une photo.', 'image' => null], 400);
        }
        if ($message === '' && $imageBase64 !== null) {
            $message = "Quelle émotion ou situation voyez-vous dans cette photo ?";
        }

        try {
            $result = $this->tsaChat->chat($message, $history, $imageBase64);
            return new JsonResponse([
                'reply' => $result['reply'],
                'image' => $result['image'],
                'generated_image_url' => $result['generated_image_url'] ?? null,
                'pexels_url' => $result['pexels_url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $debug = $this->getParameter('kernel.environment') === 'dev'
                ? ' (' . $e->getFile() . ':' . $e->getLine() . ')'
                : '';
            return new JsonResponse([
                'reply' => 'Erreur serveur : ' . $msg . $debug,
                'image' => null,
            ], 200);
        }
    }

    #[Route('/api/chat/generate-image', name: 'api_chat_generate_image', methods: ['POST'])]
    public function generateImage(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?: [];
        $prompt = trim((string) ($data['prompt'] ?? ''));

        if ($prompt === '') {
            return new JsonResponse([
                'success' => false,
                'image_url' => null,
                'error' => 'Décrivez l’image à générer.',
            ], 400);
        }

        $result = $this->hfImage->generate($prompt);

        if ($result['success']) {
            return new JsonResponse([
                'success' => true,
                'image_url' => $result['image_url'],
                'prompt' => $prompt,
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'image_url' => null,
            'error' => $result['error'],
        ], 200);
    }
}
