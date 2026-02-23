<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\DictionaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class DictionaryController extends AbstractController
{
    public function __construct(
        private readonly DictionaryService $dictionaryService,
    ) {
    }

    #[Route('/dictionary/{word}', name: 'api_dictionary', requirements: ['word' => '[^/]+'], methods: ['GET'])]
    public function getDefinition(string $word, Request $request): JsonResponse
    {
        $lang = $request->query->get('lang');
        if ($lang === null || $lang === '') {
            $lang = $request->getSession()?->get('blog_locale', 'fr');
        }
        $lang = strtolower(trim((string) $lang));

        $result = $this->dictionaryService->getDefinition($word, $lang);

        if ($result === null) {
            return $this->json(['error' => 'Definition not found'], 404);
        }

        return $this->json($result);
    }
}
