<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MedecinRating;
use App\Entity\Medcin;
use App\Repository\MedcinRepository;
use App\Repository\MedecinRatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class MedecinRatingController extends AbstractController
{
    public function __construct(
        private readonly MedcinRepository $medecinRepository,
        private readonly MedecinRatingRepository $medecinRatingRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/rendez-vous/medecin/{id}/noter', name: 'medecin_rate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rate(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if ($user === null) {
            return $this->json(['success' => false, 'message' => 'Vous devez être connecté pour noter un médecin.'], 401);
        }

        $medecin = $this->medecinRepository->find($id);
        if ($medecin === null || !$medecin instanceof Medcin) {
            return $this->json(['success' => false, 'message' => 'Médecin introuvable.'], 404);
        }

        $token = $request->request->get('_token') ?? $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('medecin_rate', (string) $token)) {
            return $this->json(['success' => false, 'message' => 'Token de sécurité invalide.'], 403);
        }

        $note = (int) ($request->request->get('note') ?? $request->query->get('note', 0));
        if ($note < 1 || $note > 5) {
            return $this->json(['success' => false, 'message' => 'La note doit être entre 1 et 5.'], 400);
        }

        $existing = $this->medecinRatingRepository->findByMedecinAndUser($medecin, $user);
        if ($existing !== null) {
            $existing->setNote($note);
            $entity = $existing;
        } else {
            $entity = new MedecinRating();
            $entity->setMedecin($medecin);
            $entity->setUser($user);
            $entity->setNote($note);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();

        $stats = $this->medecinRatingRepository->getAverageAndCountByMedecin($medecin);

        return $this->json([
            'success' => true,
            'avg' => $stats['avg'],
            'count' => $stats['count'],
            'userNote' => $note,
        ]);
    }
}
