<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Module;
use App\Form\BlogType;
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
        $modules = $this->moduleRepository->findPublishedOrderByDate();
        $data = $this->getBlogData($modules);
        return $this->render('front/blog/index.html.twig', [
            'featured' => $data['featured'],
            'articles' => $data['articles'],
            'categories' => $data['categories'],
            'popular_articles' => $data['popular_articles'],
            'popular_tags' => $data['popular_tags'],
        ]);
    }

    #[Route('/module/{id}', name: 'user_blog_module', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function module(Request $request, int $id): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        // Créer le formulaire d'article
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
            $this->addFlash('success', 'Votre article a été publié avec succès.');
            return $this->redirectToRoute('user_blog_module', ['id' => $id]);
        }

        return $this->render('front/blog/module.html.twig', [
            'module' => $module,
            'articleForm' => $form,
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

    /**
     * @param list<Module> $modules
     * @return array{
     *   featured: array{id: int, title: string, excerpt: string, author: string, author_initials: string, date: string, likes: int, comments: int, tags: list<string>, has_infographic: bool, infographic_title: string, infographic_description: string, infographic_stats: list<string>},
     *   articles: list<array{id: int, title: string, excerpt: string, author: string, author_initials: string, date: string, likes: int, comments: int, tags: list<string>, highlight: string|null, image_label: string, image_url: string|null}>,
     *   categories: list<array{name: string, count: int}>,
     *   popular_articles: list<array{id: int, title: string}>,
     *   popular_tags: list<string>
     * }
     */
    private function getBlogData(array $modules): array
    {
        $defaultFeatured = [
            'id' => 0,
            'title' => 'Blog & Témoignages',
            'excerpt' => 'Découvrez les modules et articles de la communauté.',
            'author' => 'AutiCare',
            'author_initials' => 'AG',
            'date' => (new \DateTime())->format('d F Y'),
            'likes' => 0,
            'comments' => 0,
            'tags' => ['Blog'],
            'has_infographic' => true,
            'infographic_title' => 'Troubles du spectre de l\'autisme (TSA)',
            'infographic_description' => 'Ce trouble neuro-développemental peut altérer le comportement social, la communication et le langage.',
            'infographic_stats' => ['Pas de cause unique identifiée', 'TOUCHE 1 personne sur 100 • 3 garçons pour 1 fille'],
        ];

        $featured = $defaultFeatured;
        $articles = [];
        $popular_articles = [];
        $niveauLabels = ['difficile' => 'Difficile', 'moyen' => 'Moyen', 'facile' => 'Facile'];

        foreach ($modules as $index => $module) {
            $popular_articles[] = ['id' => $module->getId(), 'title' => $module->getTitre() ?? ''];

            if ($index === 0) {
                $featured = [
                    'id' => $module->getId(),
                    'title' => $module->getTitre() ?? '',
                    'excerpt' => $module->getDescription() ?? '',
                    'author' => 'AutiCare',
                    'author_initials' => 'AG',
                    'date' => $module->getDateCreation() ? $module->getDateCreation()->format('d F Y') : '',
                    'likes' => 0,
                    'comments' => 0,
                    'tags' => ['Module', $niveauLabels[$module->getNiveau()] ?? $module->getNiveau()],
                    'has_infographic' => true,
                    'infographic_title' => $module->getTitre() ?? 'Troubles du spectre de l\'autisme (TSA)',
                    'infographic_description' => $module->getDescription() ?? 'Ce trouble neuro-développemental peut altérer le comportement social, la communication et le langage.',
                    'infographic_stats' => ['Niveau : ' . ($niveauLabels[$module->getNiveau()] ?? $module->getNiveau())],
                ];
            } else {
                $articles[] = [
                    'id' => $module->getId(),
                    'title' => $module->getTitre() ?? '',
                    'excerpt' => $module->getDescription() ?? '',
                    'author' => 'AutiCare',
                    'author_initials' => 'AG',
                    'date' => $module->getDateCreation() ? $module->getDateCreation()->format('d F Y') : '',
                    'likes' => 0,
                    'comments' => 0,
                    'tags' => ['Module', $niveauLabels[$module->getNiveau()] ?? $module->getNiveau()],
                    'highlight' => null,
                    'image_label' => 'Module',
                    'image_url' => $module->getImage() ?? null,
                ];
            }
        }

        return [
            'featured' => $featured,
            'articles' => $articles,
            'categories' => [
                ['name' => 'Facile', 'count' => \count(array_filter($modules, fn (Module $m) => $m->getNiveau() === 'facile'))],
                ['name' => 'Moyen', 'count' => \count(array_filter($modules, fn (Module $m) => $m->getNiveau() === 'moyen'))],
                ['name' => 'Difficile', 'count' => \count(array_filter($modules, fn (Module $m) => $m->getNiveau() === 'difficile'))],
            ],
            'popular_articles' => $popular_articles,
            'popular_tags' => ['Module', 'Autisme', 'TSA', 'Éducation', 'Communication'],
        ];
    }
}
