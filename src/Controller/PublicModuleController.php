<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ModuleRepository;
use App\Service\WikipediaService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/m')]
final class PublicModuleController extends AbstractController
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
        private readonly WikipediaService $wikipediaService,
    ) {
    }

    #[Route('/{id}', name: 'module_public', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        $locale = $request->getSession()->get('blog_locale', 'fr');
        $wikiUrl = $this->wikipediaService->getArticleUrlForModule($module, $locale)
            ?? $this->wikipediaService->getFallbackUrl($locale);

        return $this->render('front/blog/module_public.html.twig', [
            'module' => $module,
            'wikiUrl' => $wikiUrl,
        ]);
    }
}
