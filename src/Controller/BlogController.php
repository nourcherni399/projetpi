<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Commentaire;
use App\Entity\Module;
use App\Form\BlogType;
use App\Form\CommentaireType;
use App\Repository\FavorisArticleRepository;
use App\Repository\BlogRepository;
use App\Repository\FavorisModuleRepository;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly FavorisModuleRepository $favorisModuleRepository,
        private readonly FavorisArticleRepository $favorisArticleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    #[Route('', name: 'user_blog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Terme de recherche pour les modules
        $searchTerm = trim((string) $request->query->get('q', ''));

        // Récupérer toutes les catégories avec leurs modules
        $categoriesData = $this->getCategoriesWithModules($searchTerm);
        
        return $this->render('front/blog/index.html.twig', [
            'categories' => $categoriesData,
            'popular_articles' => $this->getPopularArticles(),
            'popular_tags' => ['Module', 'Autisme', 'TSA', 'Éducation', 'Communication', 'Témoignage'],
            'searchTerm' => $searchTerm,
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

        $savedModuleIds = [];
        $user = $this->getUser();
        if ($user !== null) {
            $savedModuleIds = $this->favorisModuleRepository->findModuleIdsByUser($user);
        }

        return $this->render('front/blog/categorie.html.twig', [
            'categorie' => $categorie,
            'modules' => $modules,
            'savedModuleIds' => $savedModuleIds,
        ]);
    }

    #[Route('/module/{id}', name: 'user_blog_module', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function module(Request $request, int $id): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        $searchTerm = trim((string) $request->query->get('q', ''));

        $commentaireForms = [];
        $articleForm = null;
        $user = $this->getUser();

        if ($user !== null) {
            foreach ($module->getBlogs() as $blog) {
                if (!$blog->isPublished()) {
                    continue;
                }

                if ($searchTerm !== '' && stripos($blog->getTitre() ?? '', $searchTerm) === false) {
                    continue;
                }

                $commentaire = new Commentaire();
                $form = $this->createForm(CommentaireType::class, $commentaire, [
                    'action' => $this->generateUrl('commentaire_ajouter_module', ['moduleId' => $blog->getId()]),
                    'method' => 'POST',
                ]);
                $commentaireForms[$blog->getId()] = $form->createView();
            }

            $newArticle = new Blog();
            $newArticle->setModule($module);
            $now = new \DateTime();
            $newArticle->setDateCreation($now);
            $newArticle->setDateModif($now);
            $newArticle->setUser($user);

            $articleFormObject = $this->createForm(BlogType::class, $newArticle, [
                'action' => $this->generateUrl('user_blog_module', ['id' => $module->getId()]),
                'method' => 'POST',
            ]);
            $articleFormObject->handleRequest($request);

            if ($articleFormObject->isSubmitted() && !$articleFormObject->isValid()) {
                $this->addFlash('error', 'Veuillez corriger les champs en rouge puis reessayer.');
            }

            if ($articleFormObject->isSubmitted() && $articleFormObject->isValid()) {
                $imageFile = $articleFormObject->get('image')->getData();
                if ($imageFile) {
                    $saved = $this->handleImageUpload($imageFile, $newArticle);
                    if (!$saved) {
                        $articleForm = $articleFormObject->createView();
                        $isModuleSaved = false;
                        if ($user !== null) {
                            $isModuleSaved = $this->favorisModuleRepository->findOneByUserAndModule($user, $module) !== null;
                        }
                        $savedArticleIds = [];
                        if ($user !== null) {
                            $savedArticleIds = $this->favorisArticleRepository->findBlogIdsByUser($user);
                        }

                        return $this->render('front/blog/module.html.twig', [
                            'module' => $module,
                            'commentaireForms' => $commentaireForms,
                            'searchTerm' => $searchTerm,
                            'articleForm' => $articleForm,
                            'isModuleSaved' => $isModuleSaved,
                            'savedArticleIds' => $savedArticleIds,
                        ]);
                    }
                }

                if ($newArticle->getImage() === null) {
                    $newArticle->setImage('');
                }

                $this->entityManager->persist($newArticle);
                $this->entityManager->flush();
                $this->addFlash('success', 'Votre article a ete enregistre.');

                return $this->redirectToRoute('user_blog_module', ['id' => $module->getId()]);
            }

            $articleForm = $articleFormObject->createView();
        }

        $isModuleSaved = false;
        if ($user !== null) {
            $isModuleSaved = $this->favorisModuleRepository->findOneByUserAndModule($user, $module) !== null;
        }
        $savedArticleIds = [];
        if ($user !== null) {
            $savedArticleIds = $this->favorisArticleRepository->findBlogIdsByUser($user);
        }

        return $this->render('front/blog/module.html.twig', [
            'module' => $module,
            'commentaireForms' => $commentaireForms,
            'searchTerm' => $searchTerm,
            'articleForm' => $articleForm,
            'isModuleSaved' => $isModuleSaved,
            'savedArticleIds' => $savedArticleIds,
        ]);
    }

    #[Route('/module/{id}/download', name: 'user_blog_module_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadModule(Module $module): Response
    {
        if (!$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        $contentLines = [
            'Titre du module : ' . ($module->getTitre() ?? ''),
            '',
            'Description :',
            $module->getDescription() ?? '',
            '',
            'Contenu :',
            $module->getContenu() ?? '',
        ];

        $content = implode("\n", $contentLines);

        $response = new Response($content);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('module-%d.txt', $module->getId())
        );

        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
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
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $saved = $this->handleImageUpload($imageFile, $blog);
                if (!$saved) {
                    return $this->render('front/blog/ecrire.html.twig', [
                        'module' => $module,
                        'form' => $form,
                    ]);
                }
            }

            if ($blog->getImage() === null) {
                $blog->setImage('');
            }
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
        $user = $this->getUser();
        if ($blog->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez modifier que vos propres articles.');
        }

        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $blog->setDateModif(new \DateTime());

            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $saved = $this->handleImageUpload($imageFile, $blog);
                if (!$saved) {
                    return $this->render('front/blog/edit_article.html.twig', [
                        'article' => $blog,
                        'form' => $form,
                    ]);
                }
            }

            if ($blog->getImage() === null) {
                $blog->setImage('');
            }
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
        $user = $this->getUser();
        if ($blog->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous ne pouvez supprimer que vos propres articles.');
        }

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

    #[Route('/article/{id}/download', name: 'user_blog_article_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadArticle(Blog $blog): Response
    {
        if (!$blog->isPublished() || !$blog->isVisible()) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $module = $blog->getModule();

        $contentLines = [
            'Titre de l\'article : ' . ($blog->getTitre() ?? ''),
            '',
            'Module : ' . ($module?->getTitre() ?? 'Sans module'),
            '',
            'Contenu :',
            $blog->getContenu() ?? '',
        ];

        $content = implode("\n", $contentLines);

        $response = new Response($content);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('article-%d.txt', $blog->getId())
        );

        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * Récupère toutes les catégories avec leurs modules
     */
    private function getCategoriesWithModules(string $searchTerm = ''): array
    {
        $categories = [];
        $searchTerm = trim($searchTerm);
        
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

            // Filtrer par titre de module si un terme de recherche est fourni
            if ($searchTerm !== '') {
                $modules = array_filter($modules, static function (Module $module) use ($searchTerm): bool {
                    $title = $module->getTitre() ?? '';
                    return stripos($title, $searchTerm) !== false;
                });
            }
            
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
     * Récupère les articles populaires (blogs)
     */
    private function getPopularArticles(): array
    {
        $blogs = $this->blogRepository->findBy(
            [
                'isPublished' => true,
                'isVisible' => true,
            ],
            ['dateCreation' => 'DESC'],
            5
        );

        $popular = [];
        foreach ($blogs as $blog) {
            $popular[] = [
                'id' => $blog->getId(),
                'title' => $blog->getTitre() ?? 'Article sans titre',
            ];
        }

        return $popular;
    }

    private function handleImageUpload(\Symfony\Component\HttpFoundation\File\UploadedFile $imageFile, Blog $blog): bool
    {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            $dir = $this->getParameter('uploads_blogs_directory');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $imageFile->move($dir, $newFilename);
            $blog->setImage('uploads/blog/' . $newFilename);
            return true;
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
            return false;
        }
    }



}
