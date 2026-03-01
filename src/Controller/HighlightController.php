<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserHighlight;
use App\Repository\UserHighlightRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/highlight')]
class HighlightController extends AbstractController
{
    public function __construct(
        private readonly UserHighlightRepository $highlightRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/list', name: 'api_highlight_list', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $targetType = $request->query->get('targetType');
        $targetId = (int) $request->query->get('targetId', 0);
        if (!\in_array($targetType, [UserHighlight::TARGET_ARTICLE, UserHighlight::TARGET_MODULE], true) || $targetId <= 0) {
            return $this->json(['error' => 'Paramètres invalides'], Response::HTTP_BAD_REQUEST);
        }

        $highlights = $this->highlightRepository->findByUserAndTarget($user, $targetType, $targetId);
        $data = array_map(fn (UserHighlight $h) => [
            'id' => $h->getId(),
            'startOffset' => $h->getStartOffset(),
            'endOffset' => $h->getEndOffset(),
            'color' => $h->getColor(),
        ], $highlights);

        return $this->json(['highlights' => $data]);
    }

    #[Route('/save', name: 'api_highlight_save', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function save(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Données invalides'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->isCsrfTokenValid('highlight_save', (string) ($data['_token'] ?? ''))) {
            return $this->json(['error' => 'Jeton invalide'], Response::HTTP_FORBIDDEN);
        }

        $targetType = $data['targetType'] ?? null;
        $targetId = isset($data['targetId']) ? (int) $data['targetId'] : 0;
        $startOffset = isset($data['startOffset']) ? (int) $data['startOffset'] : 0;
        $endOffset = isset($data['endOffset']) ? (int) $data['endOffset'] : 0;
        $color = $data['color'] ?? 'yellow';

        if (!\in_array($targetType, [UserHighlight::TARGET_ARTICLE, UserHighlight::TARGET_MODULE], true)
            || $targetId <= 0
            || $startOffset < 0
            || $endOffset <= $startOffset) {
            return $this->json(['error' => 'Paramètres invalides'], Response::HTTP_BAD_REQUEST);
        }

        $highlight = new UserHighlight();
        $highlight->setUser($user);
        $highlight->setTargetType($targetType);
        $highlight->setTargetId($targetId);
        $highlight->setStartOffset($startOffset);
        $highlight->setEndOffset($endOffset);
        $highlight->setColor($color);

        $this->entityManager->persist($highlight);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'id' => $highlight->getId(),
            'startOffset' => $highlight->getStartOffset(),
            'endOffset' => $highlight->getEndOffset(),
            'color' => $highlight->getColor(),
        ]);
    }

    #[Route('/delete/{id}', name: 'api_highlight_delete', requirements: ['id' => '\d+'], methods: ['DELETE', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $highlight = $this->highlightRepository->find($id);
        if ($highlight === null || $highlight->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Surlignage introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($highlight);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
