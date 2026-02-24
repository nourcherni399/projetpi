<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Commentaire;
use App\Form\CommentaireType;
use App\Service\ProfanityFilterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/commentaire')]
final class CommentaireController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly ProfanityFilterService $profanityFilter,
    ) {
    }

    #[Route('/check-profanity', name: 'commentaire_check_profanity', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkProfanity(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true) ?: [];
        $text = trim((string) ($data['text'] ?? $request->request->get('text') ?? ''));

        $hasBadWords = $text !== '' && $this->profanityFilter->containsBadWords($text);

        return new JsonResponse(['hasBadWords' => $hasBadWords]);
    }

    #[Route('/ajouter/{blogId}', name: 'commentaire_ajouter', requirements: ['blogId' => '\d+'], methods: ['POST'])]
    public function ajouter(Request $request, int $blogId): Response
    {
        $blog = $this->entityManager->getRepository(Blog::class)->find($blogId);
        if (!$blog) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $commentaire = new Commentaire();
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contenu = trim((string) ($commentaire->getContenu() ?? ''));
            if ($contenu !== '' && $this->profanityFilter->containsBadWords($contenu)) {
                $this->addFlash('warning', 'Votre commentaire contient des termes inappropriés. Veuillez le reformuler.');
                $redirect = $request->request->get('_redirect');
                if ($redirect && filter_var($redirect, FILTER_VALIDATE_URL)) {
                    return $this->redirect($redirect);
                }
                return $this->redirectToRoute('user_blog_show_article', ['id' => $blogId]);
            }

            $user = $this->getUser();
            $mediaFile = $form->get('mediaFile')->getData();

            if ($mediaFile instanceof UploadedFile && !$this->handleMediaUpload($mediaFile, $commentaire)) {
                $this->addFlash('error', "Erreur lors de l'upload.");
            } else {
                $commentaire->setBlog($blog);
                $commentaire->setUser($user);
                $commentaire->setIsPublished(true);
                $this->entityManager->persist($commentaire);
                $this->entityManager->flush();
                $this->addFlash('success', 'Votre commentaire a été ajouté avec succès !');
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        $redirect = $request->request->get('_redirect');
        if ($redirect && filter_var($redirect, FILTER_VALIDATE_URL)) {
            return $this->redirect($redirect);
        }
        return $this->redirectToRoute('user_blog_show_article', ['id' => $blogId]);
    }

    #[Route('/ajouter-module/{moduleId}', name: 'commentaire_ajouter_module', requirements: ['moduleId' => '\d+'], methods: ['POST'])]
    public function ajouterModule(Request $request, int $moduleId): Response
    {
        // Récupérer le premier article publié du module pour associer le commentaire
        $blog = $this->entityManager->getRepository(Blog::class)->find($moduleId);
        if (!$blog) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $commentaire = new Commentaire();
        $form = $this->createForm(CommentaireType::class, $commentaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contenu = trim((string) ($commentaire->getContenu() ?? ''));
            if ($contenu !== '' && $this->profanityFilter->containsBadWords($contenu)) {
                $this->addFlash('warning', 'Votre commentaire contient des termes inappropriés. Veuillez le reformuler.');
                $redirect = $request->request->get('_redirect');
                if ($redirect && filter_var($redirect, FILTER_VALIDATE_URL)) {
                    return $this->redirect($redirect);
                }
                return $this->redirectToRoute('user_blog_show_article', ['id' => $blog->getId()]);
            }

            $user = $this->getUser();
            $mediaFile = $form->get('mediaFile')->getData();

            if ($mediaFile instanceof UploadedFile && !$this->handleMediaUpload($mediaFile, $commentaire)) {
                $this->addFlash('error', "Erreur lors de l'upload.");
            } else {
                $commentaire->setBlog($blog);
                $commentaire->setUser($user);
                $commentaire->setIsPublished(true);
                $this->entityManager->persist($commentaire);
                $this->entityManager->flush();
                $this->addFlash('success', 'Votre commentaire a été ajouté avec succès !');
            }
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        $redirect = $request->request->get('_redirect');
        if ($redirect && filter_var($redirect, FILTER_VALIDATE_URL)) {
            return $this->redirect($redirect);
        }
        return $this->redirectToRoute('user_blog_show_article', ['id' => $blog->getId()]);
    }

    #[Route('/supprimer/{id}', name: 'commentaire_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function supprimer(Request $request, Commentaire $commentaire): Response
    {
        $user = $this->getUser();
        
        // Vérifier si l'utilisateur est l'auteur du commentaire ou un admin
        if ($commentaire->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres commentaires');
        }

        $blogId = $commentaire->getBlog()->getId();

        if ($this->isCsrfTokenValid('delete' . $commentaire->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($commentaire);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le commentaire a été supprimé avec succès');
        }

        $redirect = $request->request->get('_redirect');
        if ($redirect && filter_var($redirect, FILTER_VALIDATE_URL)) {
            return $this->redirect($redirect);
        }
        return $this->redirectToRoute('user_blog_show_article', ['id' => $blogId]);
    }

    private function handleMediaUpload(UploadedFile $mediaFile, Commentaire $commentaire): bool
    {
        $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $extension = $mediaFile->guessExtension() ?: $mediaFile->getClientOriginalExtension();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;

        try {
            $dir = $this->getParameter('uploads_commentaires_directory');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $mediaFile->move($dir, $newFilename);
            $commentaire->setMedia('uploads/commentaires/' . $newFilename);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}