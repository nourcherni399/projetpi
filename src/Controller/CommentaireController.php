<?php

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Commentaire;
use App\Form\CommentaireType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commentaire')]
final class CommentaireController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
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
            $user = $this->getUser();
            
            // Associer le commentaire au blog et à l'utilisateur
            $commentaire->setBlog($blog);
            $commentaire->setUser($user);
            $commentaire->setIsPublished(true); // Auto-publier pour l'instant
            
            $this->entityManager->persist($commentaire);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre commentaire a été ajouté avec succès !');
        } else {
            // Si le formulaire n'est pas valide, ajouter un message d'erreur
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        // Rediriger vers la page de l'article ou du module
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
            $user = $this->getUser();
            
            // Associer le commentaire à l'article trouvé
            $commentaire->setBlog($blog);
            $commentaire->setUser($user);
            $commentaire->setIsPublished(true); // Auto-publier pour l'instant
            
            $this->entityManager->persist($commentaire);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre commentaire a été ajouté avec succès !');
        } else {
            // Si le formulaire n'est pas valide, ajouter un message d'erreur
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        // Rediriger vers la page de l'article
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

        return $this->redirectToRoute('user_blog_show_article', ['id' => $blogId]);
    }
}
