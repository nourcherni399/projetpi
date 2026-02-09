<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Thematique;
use App\Form\ThematiqueType;
use App\Repository\ThematiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/admin/thematiques')]
final class ThematiqueController extends AbstractController
{
    public function __construct(
        private readonly ThematiqueRepository $thematiqueRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_thematique_index', methods: ['GET'])]
    public function index(): Response
    {
        $thematiques = $this->thematiqueRepository->findBy([], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);
        return $this->render('admin/thematique/index.html.twig', ['thematiques' => $thematiques]);
    }

    #[Route('/new', name: 'admin_thematique_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $thematique = new Thematique();
        $form = $this->createForm(ThematiqueType::class, $thematique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($thematique);
            $this->entityManager->flush();
            $this->addFlash('success', 'La thématique a été créée avec succès.');

            return $this->redirectToRoute('admin_thematique_index');
        }

        return $this->render('admin/thematique/new.html.twig', [
            'thematique' => $thematique,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_thematique_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $thematique = $this->thematiqueRepository->find($id);
        if ($thematique === null) {
            throw new NotFoundHttpException('Thématique introuvable.');
        }
        return $this->render('admin/thematique/show.html.twig', ['thematique' => $thematique]);
    }

    #[Route('/{id}/edit', name: 'admin_thematique_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $thematique = $this->thematiqueRepository->find($id);
        if ($thematique === null) {
            throw new NotFoundHttpException('Thématique introuvable.');
        }
        $form = $this->createForm(ThematiqueType::class, $thematique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'La thématique a été modifiée avec succès.');
            return $this->redirectToRoute('admin_thematique_show', ['id' => $thematique->getId()]);
        }

        return $this->render('admin/thematique/edit.html.twig', [
            'thematique' => $thematique,
            'form' => $form,
        ]);
    }
}
