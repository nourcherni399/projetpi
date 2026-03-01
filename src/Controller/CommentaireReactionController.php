<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\CommentaireReaction;
use App\Repository\CommentaireReactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commentaire', name: 'commentaire_')]
final class CommentaireReactionController extends AbstractController
{
    public function __construct(
        private readonly CommentaireReactionRepository $commentaireReactionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/reaction/{id}', name: 'reaction_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleReaction(Request $request, Commentaire $commentaire): Response
    {
        $user = $this->getUser();
        if (!$user) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Non connecté', 'login_url' => $this->generateUrl('app_login')], 401);
            }

            return $this->redirectToRoute('app_login');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('commentaire_reaction_' . $commentaire->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Requête invalide.');

            return $this->redirectBack($request);
        }

        $type = (string) $request->request->get('type', 'like');
        if (!in_array($type, CommentaireReaction::TYPES, true)) {
            $type = 'like';
        }

        $existing = $this->commentaireReactionRepository->findOneByUserAndCommentaire($user, $commentaire);
        $userReaction = null;

        if ($existing instanceof CommentaireReaction) {
            if ($existing->getType() === $type) {
                // Same reaction → toggle off (remove)
                $this->entityManager->remove($existing);
            } else {
                // Different reaction → update type
                $existing->setType($type);
                $userReaction = $type;
            }
        } else {
            // No reaction yet → create
            $reaction = new CommentaireReaction();
            $reaction->setUser($user);
            $reaction->setCommentaire($commentaire);
            $reaction->setType($type);
            $this->entityManager->persist($reaction);
            $userReaction = $type;
        }

        $this->entityManager->flush();

        $counts = $this->commentaireReactionRepository->countByTypeForCommentaire($commentaire);
        $total = array_sum($counts);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'userReaction' => $userReaction,
                'counts' => $counts,
                'total' => $total,
            ]);
        }

        return $this->redirectBack($request);
    }

    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('user_blog');
    }
}