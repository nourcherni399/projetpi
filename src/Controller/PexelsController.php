<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PexelsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/pexels')]
final class PexelsController extends AbstractController
{
    public function __construct(
        private readonly PexelsService $pexelsService,
    ) {
    }

    #[Route('/search', name: 'pexels_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (strlen($query) < 2) {
            return $this->json(['photos' => [], 'error' => 'Recherche trop courte'], 400);
        }

        $result = $this->pexelsService->search($query);

        if ($result['error'] !== null) {
            return $this->json($result, 500);
        }

        return $this->json($result);
    }
}