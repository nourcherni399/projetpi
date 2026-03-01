<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Favoris;
use App\Entity\FavorisArticle;
use App\Entity\FavorisModule;
use App\Entity\ModuleBookmark;
use App\Entity\Blog;
use App\Entity\Module;
use App\Entity\Produit;
use App\Repository\BlogRepository;
use App\Repository\FavorisArticleRepository;
use App\Repository\FavorisModuleRepository;
use App\Repository\FavorisRepository;
use App\Repository\ModuleBookmarkRepository;
use App\Repository\ModuleRepository;
use App\Service\GoogleBooksService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/favoris', name: 'favoris_')]
final class FavorisController extends AbstractController
{
    public function __construct(
        private readonly FavorisRepository $favorisRepository,
        private readonly FavorisArticleRepository $favorisArticleRepository,
        private readonly FavorisModuleRepository $favorisModuleRepository,
        private readonly ModuleBookmarkRepository $moduleBookmarkRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly BlogRepository $blogRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GoogleBooksService $googleBooksService,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $tab = (string) $request->query->get('tab', 'all');
        if (!in_array($tab, ['all', 'saved', 'liked', 'bibliotheque'], true)) {
            $tab = 'all';
        }

        $favoris = $this->favorisRepository->findByUser($user);
        $favorisArticles = $this->favorisArticleRepository->findByUser($user);
        $favorisModules = $this->favorisModuleRepository->findByUser($user);
        $moduleBookmarks = $this->moduleBookmarkRepository->findByUser($user);
        $recentModules = $this->moduleRepository->findBy(['isPublished' => true], ['dateCreation' => 'DESC'], 20);
        $recentArticles = $this->blogRepository->findBy(['isPublished' => true, 'isVisible' => true], ['dateCreation' => 'DESC'], 20);

        $recentAdds = [];
        foreach ($recentModules as $module) {
            $recentAdds[] = [
                'type' => 'module',
                'date' => $module->getDateCreation(),
                'module' => $module,
            ];
        }
        foreach ($recentArticles as $article) {
            $recentAdds[] = [
                'type' => 'article',
                'date' => $article->getDateCreation(),
                'article' => $article,
            ];
        }

        usort($recentAdds, static function (array $a, array $b): int {
            $dateA = $a['date'] instanceof \DateTimeInterface ? $a['date']->getTimestamp() : 0;
            $dateB = $b['date'] instanceof \DateTimeInterface ? $b['date']->getTimestamp() : 0;

            return $dateB <=> $dateA;
        });

        // Favoris : fusion modules + articles (cœur), triés par date, affichage comme Tous
        $favorisItems = [];
        foreach ($favorisModules as $fm) {
            $favorisItems[] = [
                'type' => 'module',
                'date' => $fm->getCreatedAt(),
                'module' => $fm->getModule(),
                'favoriModule' => $fm,
            ];
        }
        foreach ($favorisArticles as $fa) {
            $favorisItems[] = [
                'type' => 'article',
                'date' => $fa->getCreatedAt(),
                'article' => $fa->getBlog(),
                'favoriArticle' => $fa,
            ];
        }
        usort($favorisItems, static function (array $a, array $b): int {
            $dateA = $a['date'] instanceof \DateTimeInterface ? $a['date']->getTimestamp() : 0;
            $dateB = $b['date'] instanceof \DateTimeInterface ? $b['date']->getTimestamp() : 0;

            return $dateB <=> $dateA;
        });

        $bookSearchQuery = '';
        $bookResults = ['items' => [], 'error' => null];
        if ($tab === 'bibliotheque') {
            $bookSearchQuery = trim((string) $request->query->get('q', ''));
            $searchQuery = $bookSearchQuery !== '' ? $bookSearchQuery : 'autisme';
            $bookResults = $this->googleBooksService->search($searchQuery, 20);
        }

        return $this->render('front/favoris/index.html.twig', [
            'favoris' => $favoris,
            'favorisArticles' => $favorisArticles,
            'favorisModules' => $favorisModules,
            'moduleBookmarks' => $moduleBookmarks,
            'favorisItems' => $favorisItems,
            'recentAdds' => $recentAdds,
            'activeTab' => $tab,
            'bookSearchQuery' => $bookSearchQuery,
            'bookResults' => $bookResults,
        ]);
    }

    #[Route('/books/{id}', name: 'books_detail', methods: ['GET'], requirements: ['id' => '[a-zA-Z0-9_-]+'])]
    public function bookDetail(string $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non autorisé'], 401);
        }

        $book = $this->googleBooksService->getVolume($id);
        if ($book === null) {
            return new JsonResponse(['error' => 'Livre non trouvé'], 404);
        }

        return new JsonResponse($book);
    }

    #[Route('/ajouter/{id}', name: 'add', methods: ['POST'])]
    public function add(Produit $produit, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si le produit est déjà dans les favoris
        $existingFavori = $this->favorisRepository->findOneByUserAndProduit($user, $produit);
        
        if ($existingFavori) {
            $this->addFlash('info', 'Ce produit est déjà dans vos favoris.');
            return $this->redirectToRoute('user_products_index');
        }

        // Créer le nouveau favori
        $favori = new Favoris();
        $favori->setUser($user);
        $favori->setProduit($produit);

        $this->entityManager->persist($favori);
        $this->entityManager->flush();

        $this->addFlash('success', 'Produit ajouté aux favoris!');
        
        // Rediriger vers la page d'origine ou la liste des produits
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }
        
        return $this->redirectToRoute('user_products_index');
    }

    #[Route('/supprimer/{id}', name: 'remove', methods: ['POST'])]
    public function remove(Produit $produit, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $favori = $this->favorisRepository->findOneByUserAndProduit($user, $produit);
        
        if (!$favori) {
            $this->addFlash('error', 'Ce produit n\'est pas dans vos favoris.');
            return $this->redirectToRoute('favoris_index');
        }

        $this->entityManager->remove($favori);
        $this->entityManager->flush();

        $this->addFlash('success', 'Produit retiré des favoris!');
        
        // Rediriger vers la page d'origine ou la liste des favoris
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }
        
        return $this->redirectToRoute('favoris_index');
    }

    #[Route('/module/toggle/{id}', name: 'module_toggle', methods: ['POST'])]
    public function toggleModule(Module $module, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_module_favori_' . $module->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Requête invalide.');
            return $this->redirectToRoute('user_blog');
        }

        $existing = $this->favorisModuleRepository->findOneByUserAndModule($user, $module);
        $isSaved = false;
        if ($existing instanceof FavorisModule) {
            $this->entityManager->remove($existing);
            $this->addFlash('success', 'Module retiré des favoris.');
        } else {
            $saved = new FavorisModule();
            $saved->setUser($user);
            $saved->setModule($module);
            $this->entityManager->persist($saved);
            $this->addFlash('success', 'Module ajouté aux favoris.');
            $isSaved = true;
        }

        $this->entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['saved' => $isSaved]);
        }

        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('favoris_index', ['tab' => 'liked']);
    }

    #[Route('/module/bookmark/{id}', name: 'module_bookmark_toggle', methods: ['POST'])]
    public function toggleModuleBookmark(Module $module, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_module_bookmark_' . $module->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Requête invalide.');
            return $this->redirectToRoute('user_blog');
        }

        $existing = $this->moduleBookmarkRepository->findOneByUserAndModule($user, $module);
        $isSaved = false;
        if ($existing instanceof ModuleBookmark) {
            $this->entityManager->remove($existing);
            $this->addFlash('success', 'Module retiré des enregistrements.');
        } else {
            $bookmark = new ModuleBookmark();
            $bookmark->setUser($user);
            $bookmark->setModule($module);
            $this->entityManager->persist($bookmark);
            $this->addFlash('success', 'Module enregistré.');
            $isSaved = true;
        }

        $this->entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['saved' => $isSaved]);
        }

        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('favoris_index', ['tab' => 'saved']);
    }

    #[Route('/article/toggle/{id}', name: 'article_toggle', methods: ['POST'])]
    public function toggleArticle(Blog $article, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle_article_favori_' . $article->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Token CSRF invalide'], 403);
            }
            $this->addFlash('error', 'Requête invalide.');

            return $this->redirectToRoute('user_blog');
        }

        if ($article->getUser() !== null && $article->getUser()->getId() === $user->getId()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Vous ne pouvez pas enregistrer votre propre article.'], 403);
            }
            $this->addFlash('error', 'Vous ne pouvez pas enregistrer votre propre article.');

            return $this->redirectToRoute('user_blog_module', ['id' => $article->getModule()->getId()]);
        }

        $existing = $this->favorisArticleRepository->findOneByUserAndBlog($user, $article);
        $isSaved = false;
        if ($existing instanceof FavorisArticle) {
            $this->entityManager->remove($existing);
            $this->addFlash('success', 'Article retiré des favoris.');
        } else {
            $saved = new FavorisArticle();
            $saved->setUser($user);
            $saved->setBlog($article);
            $this->entityManager->persist($saved);
            $this->addFlash('success', 'Article ajouté aux favoris.');
            $isSaved = true;
        }

        $this->entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['saved' => $isSaved]);
        }

        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('favoris_index', ['tab' => 'liked']);
    }
}
