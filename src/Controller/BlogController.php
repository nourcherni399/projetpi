<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Commentaire;
use App\Entity\Module;
use App\Entity\Ressource;
use App\Service\CloudinaryUploadService;
use App\Service\GroqBlogGeneratorService;
use App\Service\GroqHashtagGeneratorService;
use App\Service\GroqSpellCheckService;
use App\Service\GroqSummaryService;
use App\Service\LibreTranslateService;
use App\Service\PexelsService;
use App\Service\WikipediaService;
use App\Form\BlogType;
use App\Form\CommentaireType;
use App\Repository\BlogRepository;
use App\Repository\CommentaireReactionRepository;
use App\Repository\FavorisArticleRepository;
use App\Repository\FavorisModuleRepository;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/blog')]
final class BlogController extends AbstractController
{
    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly FavorisModuleRepository $favorisModuleRepository,
        private readonly FavorisArticleRepository $favorisArticleRepository,
        private readonly CommentaireReactionRepository $commentaireReactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly PexelsService $pexelsService,
        private readonly GroqBlogGeneratorService $groqBlogGenerator,
        private readonly GroqSpellCheckService $groqSpellCheck,
        private readonly WikipediaService $wikipediaService,
    ) {
    }

    #[Route('/apply-spell-check', name: 'user_blog_apply_spell_check', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function applySpellCheck(Request $request): Response
    {
        $articleId = (int) $request->request->get('article_id', 0);
        $correctedTitre = trim((string) $request->request->get('corrected_titre', ''));
        $correctedContenu = trim((string) $request->request->get('corrected_contenu', ''));
        if ($articleId <= 0) {
            $this->addFlash('error', 'Article invalide.');
            return $this->redirectToRoute('user_blog');
        }
        if (!$this->isCsrfTokenValid('apply_spell_check_' . $articleId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            $b = $this->blogRepository->find($articleId);
            if ($b?->getModule()) {
                return $this->redirectToRoute('user_blog_module', ['id' => $b->getModule()->getId()]);
            }
            return $this->redirectToRoute('user_blog');
        }
        $blog = $this->blogRepository->find($articleId);
        if (!$blog || ($blog->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN'))) {
            $this->addFlash('error', 'Article non trouvé ou accès refusé.');
            return $this->redirectToRoute('user_blog');
        }
        $request->getSession()->set('spell_check_draft_' . $articleId, [
            'titre' => $correctedTitre,
            'contenu' => $correctedContenu,
        ]);
        return $this->redirectToRoute('user_blog_edit_article', ['id' => $articleId]);
    }

    #[Route('/spell-check', name: 'user_blog_spell_check', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function spellCheck(Request $request): JsonResponse
    {
        $text = trim((string) $request->request->get('text', ''));
        if ($text === '') {
            return $this->json(['error' => 'Texte vide.'], 400);
        }
        $result = $this->groqSpellCheck->check($text);
        if (isset($result['error'])) {
            return $this->json($result, 400);
        }
        return $this->json($result);
    }

    #[Route('/generate', name: 'user_blog_generate', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function generate(Request $request): JsonResponse
    {
        $prompt = trim((string) $request->request->get('prompt', ''));
        $type = trim((string) $request->request->get('type', ''));

        if ($prompt === '') {
            return $this->json(['error' => 'Veuillez saisir un prompt.'], 400);
        }

        $result = $this->groqBlogGenerator->generate($prompt, $type);
        if (isset($result['error'])) {
            return $this->json($result, 400);
        }

        return $this->json($result);
    }

    #[Route('/generate-hashtags', name: 'user_blog_generate_hashtags', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function generateHashtags(Request $request, GroqHashtagGeneratorService $hashtagService): JsonResponse
    {
        $titre = trim((string) $request->request->get('titre', ''));
        if ($titre === '') {
            return $this->json(['error' => 'Veuillez saisir un titre.'], 400);
        }
        $result = $hashtagService->generateFromTitle($titre);
        if (isset($result['error'])) {
            return $this->json($result, 400);
        }
        return $this->json($result);
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
            'popular_articles' => $this->favorisArticleRepository->findMostFavoritedArticles(5),
            'popular_tags' => ['Module', 'Autisme', 'TSA', 'Éducation', 'Communication', 'Témoignage'],
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/categorie/{slug}', name: 'user_blog_categorie', methods: ['GET'])]
    public function categorie(Request $request, string $slug): Response
    {
        $searchTerm = trim((string) $request->query->get('q', ''));
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

        $categoriesData = $this->getCategoriesWithModules('');

        return $this->render('front/blog/categorie.html.twig', [
            'categorie' => $categorie,
            'modules' => $modules,
            'savedModuleIds' => $savedModuleIds,
            'searchTerm' => $searchTerm,
            'categories' => $categoriesData,
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
        $locale = $request->getSession()->get('blog_locale', 'fr');
        $wikiUrl = $this->wikipediaService->getArticleUrlForModule($module, $locale)
            ?? $this->wikipediaService->getFallbackUrl($locale);

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
                $pexelsUrl = trim((string) $articleFormObject->get('pexels_image_url')->getData());

                if ($imageFile) {
                    $saved = $this->handleImageUpload($imageFile, $newArticle);
                    if (!$saved) {
                        $articleForm = $articleFormObject->createView();
                        $isModuleSaved = false;
                        if ($user !== null) {
                            $isModuleSaved = $this->favorisModuleRepository->findOneByUserAndModule($user, $module) !== null;
                        }
                        $savedArticleIds = [];
                        $reactedCommentTypes = [];
                        if ($user !== null) {
                            $savedArticleIds = $this->favorisArticleRepository->findBlogIdsByUser($user);
                            $reactedCommentTypes = $this->commentaireReactionRepository->findReactionTypesByUser($user);
                        }

                        return $this->render('front/blog/module.html.twig', [
                            'module' => $module,
                            'commentaireForms' => $commentaireForms,
                            'searchTerm' => $searchTerm,
                            'articleForm' => $articleForm,
                            'isModuleSaved' => $isModuleSaved,
                            'savedArticleIds' => $savedArticleIds,
                            'reactedCommentTypes' => $reactedCommentTypes,
                            'wikiUrl' => $wikiUrl,
                        ]);
                    }
                } elseif ($pexelsUrl !== '' && filter_var($pexelsUrl, FILTER_VALIDATE_URL)) {
                    $targetDir = $this->getParameter('uploads_blogs_directory');
                    $filename = $this->pexelsService->downloadAndSave($pexelsUrl, $targetDir);
                    if ($filename !== null) {
                        $newArticle->setImage('uploads/blog/' . $filename);
                    } else {
                        $this->addFlash('error', 'Impossible de télécharger l\'image depuis Pexels.');
                        $articleForm = $articleFormObject->createView();
                        return $this->render('front/blog/module.html.twig', [
                            'module' => $module,
                            'commentaireForms' => $commentaireForms,
                            'searchTerm' => $searchTerm,
                            'articleForm' => $articleForm,
                            'isModuleSaved' => $this->favorisModuleRepository->findOneByUserAndModule($user, $module) !== null,
                            'savedArticleIds' => $this->favorisArticleRepository->findBlogIdsByUser($user),
                            'reactedCommentTypes' => $this->commentaireReactionRepository->findReactionTypesByUser($user),
                            'wikiUrl' => $wikiUrl,
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
        $reactedCommentTypes = [];
        if ($user !== null) {
            $savedArticleIds = $this->favorisArticleRepository->findBlogIdsByUser($user);
            $reactedCommentTypes = $this->commentaireReactionRepository->findReactionTypesByUser($user);
        }

        return $this->render('front/blog/module.html.twig', [
            'module' => $module,
            'commentaireForms' => $commentaireForms,
            'searchTerm' => $searchTerm,
            'articleForm' => $articleForm,
            'isModuleSaved' => $isModuleSaved,
            'savedArticleIds' => $savedArticleIds,
            'reactedCommentTypes' => $reactedCommentTypes,
            'wikiUrl' => $wikiUrl,
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

    #[Route('/module/{id}/summary', name: 'module_summary', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function moduleSummary(Module $module, Request $request, GroqSummaryService $groqService, LibreTranslateService $libreTranslate): JsonResponse
    {
        if (!$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        if (!$groqService->isConfigured()) {
            return $this->json([
                'summary' => 'Clé API non configurée. Ajoutez GROQ_API_KEY dans .env.local',
                'success' => false,
            ]);
        }

        $contentToSummarize = implode("\n\n", array_filter([
            $module->getTitre(),
            $module->getDescription(),
            $module->getContenu(),
        ]));

        $result = $groqService->summarize($contentToSummarize);

        $summary = $result['summary'];
        $error = $result['error'];

        if ($summary !== null) {
            $locale = $request->getSession()->get('blog_locale', 'fr');
            if ($locale !== 'fr') {
                $summary = $libreTranslate->translate($summary, 'fr', $locale);
            }
        }

        return $this->json([
            'summary' => $summary ?? ($error ?? 'Impossible de générer le résumé.'),
            'success' => $summary !== null,
        ]);
    }

    #[Route('/ressource/{id}/download', name: 'ressource_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadRessource(Ressource $ressource, CloudinaryUploadService $cloudinary, EntityManagerInterface $entityManager): Response
    {
        if (!$ressource->isActive()) {
            throw $this->createNotFoundException('Ressource non disponible.');
        }
        $module = $ressource->getModule();
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module non trouvé.');
        }

        $contenu = trim((string) $ressource->getContenu());

        try {
            if (str_starts_with($contenu, 'uploads/ressources/')) {
                $projectDir = $this->getParameter('kernel.project_dir');
                $localPath = $projectDir . '/public/' . $contenu;

                if (!file_exists($localPath)) {
                    throw $this->createNotFoundException('Fichier introuvable.');
                }

                $cloudinary->uploadFromPath($localPath, 'Telechargement');
            } elseif (preg_match('#^https?://#i', $contenu)) {
                $cloudinary->uploadFromUrl($contenu, 'Telechargement');
            } else {
                throw $this->createNotFoundException('Ressource invalide.');
            }
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }
            $this->addFlash('error', "Impossible de sauvegarder la ressource dans le cloud : " . $e->getMessage());
            return $this->redirectToRoute('user_blog_module', ['id' => $module->getId()]);
        }

        $this->addFlash('success', 'La ressource a été enregistrée dans votre dossier Cloudinary "Telechargement".');
        return $this->redirectToRoute('user_blog_module', ['id' => $module->getId()]);
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
            $pexelsUrl = trim((string) $form->get('pexels_image_url')->getData());

            if ($imageFile) {
                $saved = $this->handleImageUpload($imageFile, $blog);
                if (!$saved) {
                    return $this->render('front/blog/ecrire.html.twig', [
                        'module' => $module,
                        'form' => $form,
                    ]);
                }
            } elseif ($pexelsUrl !== '' && filter_var($pexelsUrl, FILTER_VALIDATE_URL)) {
                $targetDir = $this->getParameter('uploads_blogs_directory');
                $filename = $this->pexelsService->downloadAndSave($pexelsUrl, $targetDir);
                if ($filename !== null) {
                    $blog->setImage('uploads/blog/' . $filename);
                } else {
                    $this->addFlash('error', 'Impossible de télécharger l\'image depuis Pexels.');
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

        $sessionKey = 'spell_check_draft_' . $blog->getId();
        $session = $request->getSession();
        if ($request->isMethod('GET') && $session->has($sessionKey)) {
            $draft = $session->get($sessionKey);
            $session->remove($sessionKey);
            if (is_array($draft)) {
                if (isset($draft['titre'])) {
                    $blog->setTitre($draft['titre']);
                }
                if (isset($draft['contenu'])) {
                    $blog->setContenu($draft['contenu']);
                }
            }
        }

        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $blog->setDateModif(new \DateTime());

            $imageFile = $form->get('image')->getData();
            $pexelsUrl = trim((string) $form->get('pexels_image_url')->getData());

            if ($imageFile) {
                $saved = $this->handleImageUpload($imageFile, $blog);
                if (!$saved) {
                    return $this->render('front/blog/edit_article.html.twig', [
                        'article' => $blog,
                        'form' => $form,
                    ]);
                }
            } elseif ($pexelsUrl !== '' && filter_var($pexelsUrl, FILTER_VALIDATE_URL)) {
                $targetDir = $this->getParameter('uploads_blogs_directory');
                $filename = $this->pexelsService->downloadAndSave($pexelsUrl, $targetDir);
                if ($filename !== null) {
                    $blog->setImage('uploads/blog/' . $filename);
                } else {
                    $this->addFlash('error', 'Impossible de télécharger l\'image depuis Pexels.');
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

        $commentaire = new Commentaire();
        $commentaireForm = $this->createForm(CommentaireType::class, $commentaire);

        $reactedCommentTypes = [];
        $user = $this->getUser();
        if ($user !== null) {
            $reactedCommentTypes = $this->commentaireReactionRepository->findReactionTypesByUser($user);
        }

        return $this->render('front/blog/show_article.html.twig', [
            'article' => $blog,
            'module' => $blog->getModule(),
            'commentaireForm' => $commentaireForm->createView(),
            'reactedCommentTypes' => $reactedCommentTypes,
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
