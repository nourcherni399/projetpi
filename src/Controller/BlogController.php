<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Commentaire;
use App\Entity\Module;
use App\Form\BlogType;
use App\Form\CommentaireType;
use App\Repository\BlogRepository;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'user_blog', methods: ['GET'])]
    public function index(): Response
    {
        // Récupérer toutes les catégories avec leurs modules
        $categoriesData = $this->getCategoriesWithModules();
        
        return $this->render('front/blog/index.html.twig', [
            'categories' => $categoriesData,
            'popular_articles' => $this->getPopularArticles(),
            'popular_tags' => ['Module', 'Autisme', 'TSA', 'Éducation', 'Communication', 'Témoignage'],
        ]);
    }

    #[Route('/categorie/{slug}', name: 'user_blog_categorie', methods: ['GET'])]
    public function categorie(string $slug): Response
    {
        // Trouver la catégorie par son slug
        $categorie = null;
        $modules = [];
        
        try {
            $catEnum = \App\Enum\CategorieModule::from($slug);
            $categorie = [
                'name' => $catEnum->label(),
                'slug' => $slug,
                'image' => $this->getCategorieImage($slug),
            ];
            
            $modules = $this->moduleRepository->findBy([
                'categorie' => $catEnum,
                'isPublished' => true
            ], ['dateCreation' => 'DESC']);
            
        } catch (\ValueError $e) {
            throw $this->createNotFoundException('Catégorie introuvable.');
        }

        return $this->render('front/blog/categorie.html.twig', [
            'categorie' => $categorie,
            'modules' => $modules,
        ]);
    }

    #[Route('/module/{id}', name: 'user_blog_module', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function module(Request $request, int $id): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        $articleForm = null;
        $commentaireForm = null;
        $user = $this->getUser();
        
        // Créer le formulaire d'article SEULEMENT si l'utilisateur est connecté
        if ($user !== null) {
            $blog = new Blog();
            $blog->setModule($module);
            $now = new \DateTime();
            $blog->setDateCreation($now);
            $blog->setDateModif($now);
            $blog->setUser($user);

            $form = $this->createForm(BlogType::class, $blog);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $blog->setImage($blog->getImage() ?? '');
                $this->entityManager->persist($blog);
                $this->entityManager->flush();
                $this->addFlash('success', 'Votre article a été publié avec succès.');
                return $this->redirectToRoute('user_blog_module', ['id' => $id]);
            }
            
            $articleForm = $form->createView();
            
            // Créer aussi un formulaire de commentaire pour le module
            $commentaire = new Commentaire();
            $commentaire->setBlog(null); // Pas d'article spécifique
            $commentaireForm = $this->createForm(CommentaireType::class, $commentaire);
        }

        return $this->render('front/blog/module.html.twig', [
            'module' => $module,
            'articleForm' => $articleForm, // null si pas connecté
            'commentaireForm' => $commentaireForm ? $commentaireForm->createView() : null
        ]);
    }

    #[Route('/module/{id}/ecrire', name: 'user_blog_ecrire', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function ecrire(Request $request, int $id): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        $blog = new Blog();
        $blog->setModule($module);
        $now = new \DateTime();
        $blog->setDateCreation($now);
        $blog->setDateModif($now);
        $user = $this->getUser();
        if ($user !== null) {
            $blog->setUser($user);
        }

        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $blog->setImage($blog->getImage() ?? '');
            $this->entityManager->persist($blog);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre article a été enregistré.');
            return $this->redirectToRoute('user_blog_module', ['id' => $id]);
        }

        return $this->render('front/blog/ecrire.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/article/{id}/edit', name: 'user_blog_edit_article', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editArticle(Request $request, Blog $blog): Response
    {
        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $blog->setDateModif(new \DateTime());
            $blog->setImage($blog->getImage() ?? '');
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'article a été modifié avec succès.');
            return $this->redirectToRoute('user_blog_module', ['id' => $blog->getModule()->getId()]);
        }

        return $this->render('front/blog/edit_article.html.twig', [
            'article' => $blog,
            'form' => $form,
        ]);
    }

    #[Route('/article/{id}/delete', name: 'user_blog_delete_article', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteArticle(Request $request, Blog $blog): Response
    {
        if ($this->isCsrfTokenValid('delete' . $blog->getId(), $request->request->get('_token'))) {
            $moduleId = $blog->getModule()->getId();
            $this->entityManager->remove($blog);
            $this->entityManager->flush();
            $this->addFlash('success', 'L\'article a été supprimé avec succès.');
            return $this->redirectToRoute('user_blog_module', ['id' => $moduleId]);
        }

        return $this->redirectToRoute('user_blog_module', ['id' => $blog->getModule()->getId()]);
    }

    #[Route('/article/{id}', name: 'user_blog_show_article', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showArticle(Blog $blog): Response
    {
        // Vérifier si l'article est publié et visible
        if (!$blog->isPublished() || !$blog->isVisible()) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        // Créer le formulaire de commentaire
        $commentaire = new Commentaire();
        $commentaireForm = $this->createForm(CommentaireType::class, $commentaire);

        return $this->render('front/blog/show_article.html.twig', [
            'article' => $blog,
            'module' => $blog->getModule(),
            'commentaireForm' => $commentaireForm->createView()
        ]);
    }

    /**
     * Récupère toutes les catégories avec leurs modules
     */
    private function getCategoriesWithModules(): array
    {
        $categories = [];
        
        // Liste de toutes les catégories de l'énumération (sauf EMPTY)
        $categorieEnums = [
            \App\Enum\CategorieModule::COMPRENDRE_TSA,
            \App\Enum\CategorieModule::AUTONOMIE,
            \App\Enum\CategorieModule::COMMUNICATION,
            \App\Enum\CategorieModule::EMOTIONS,
            \App\Enum\CategorieModule::VIE_QUOTIDIENNE,
            \App\Enum\CategorieModule::ACCOMPAGNEMENT,
        ];
        
        foreach ($categorieEnums as $catEnum) {
            $modules = $this->moduleRepository->findBy([
                'categorie' => $catEnum,
                'isPublished' => true
            ], ['dateCreation' => 'DESC']);
            
            if (count($modules) > 0) {
                $categories[] = [
                    'name' => $catEnum->label(),
                    'slug' => $catEnum->value,
                    'count' => count($modules),
                    'image' => $this->getCategorieImage($catEnum->value),
                    'modules' => array_slice($modules, 0, 3), // Prendre les 3 premiers modules
                ];
            }
        }
        
        return $categories;
    }
    
    /**
     * Récupère une image par défaut pour chaque catégorie
     */
    private function getCategorieImage(string $slug): string
    {
        $images = [
            'COMPRENDRE_TSA' => 'images/categories/comprendre-tsa.jpg',
            'AUTONOMIE' => 'images/categories/autonomie.jpg',
            'COMMUNICATION' => 'images/categories/communication.jpg',
            'EMOTIONS' => 'images/categories/emotions.jpg',
            'VIE_QUOTIDIENNE' => 'images/categories/vie-quotidienne.jpg',
            'ACCOMPAGNEMENT' => 'images/categories/accompagnement.jpg',
        ];
        
        return $images[$slug] ?? 'images/logo.png';
    }
    
    /**
     * Récupère les articles populaires
     */
    private function getPopularArticles(): array
    {
        $modules = $this->moduleRepository->findBy(['isPublished' => true], ['dateCreation' => 'DESC'], 5);
        
        $popular = [];
        foreach ($modules as $module) {
            $popular[] = [
                'id' => $module->getId(),
                'title' => $module->getTitre() ?? 'Module sans titre',
            ];
        }
        
        return $popular;
    }




}
