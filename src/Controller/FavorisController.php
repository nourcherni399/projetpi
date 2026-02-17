<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Favoris;
use App\Entity\FavorisArticle;
use App\Entity\FavorisModule;
use App\Entity\Blog;
use App\Entity\Module;
use App\Entity\Produit;
use App\Repository\BlogRepository;
use App\Repository\FavorisArticleRepository;
use App\Repository\FavorisModuleRepository;
use App\Repository\FavorisRepository;
use App\Repository\ModuleRepository;
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
        private readonly ModuleRepository $moduleRepository,
        private readonly BlogRepository $blogRepository,
        private readonly EntityManagerInterface $entityManager,
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
        if (!in_array($tab, ['all', 'saved', 'liked', 'history'], true)) {
            $tab = 'all';
        }

        $favoris = $this->favorisRepository->findByUser($user);
        $favorisArticles = $this->favorisArticleRepository->findByUser($user);
        $favorisModules = $this->favorisModuleRepository->findByUser($user);
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

        return $this->render('front/favoris/index.html.twig', [
            'favoris' => $favoris,
            'favorisArticles' => $favorisArticles,
            'favorisModules' => $favorisModules,
            'recentAdds' => $recentAdds,
            'activeTab' => $tab,
        ]);
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
            $this->addFlash('success', 'Module retiré des enregistrements.');
        } else {
            $saved = new FavorisModule();
            $saved->setUser($user);
            $saved->setModule($module);
            $this->entityManager->persist($saved);
            $this->addFlash('success', 'Module enregistré avec succès.');
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

        return $this->redirectToRoute('favoris_index', ['tab' => 'saved']);
    }
}
