<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Thematique;
use App\Form\ThematiqueType;
use App\Repository\ThematiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
<<<<<<< HEAD
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
=======
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770

#[Route('/admin/thematiques')]
final class ThematiqueController extends AbstractController
{
    public function __construct(
        private readonly ThematiqueRepository $thematiqueRepository,
        private readonly EntityManagerInterface $entityManager,
<<<<<<< HEAD
=======
        private readonly SluggerInterface $slugger,
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    ) {
    }

    #[Route('', name: 'admin_thematique_index', methods: ['GET'])]
<<<<<<< HEAD
    public function index(): Response
    {
        $thematiques = $this->thematiqueRepository->findBy([], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);
        return $this->render('admin/thematique/index.html.twig', ['thematiques' => $thematiques]);
=======
    public function index(Request $request): Response
    {
        $q = $request->query->get('q');
        $q = \is_string($q) ? trim($q) : '';
        $thematiques = $this->thematiqueRepository->search($q === '' ? null : $q);
        return $this->render('admin/thematique/index.html.twig', [
            'thematiques' => $thematiques,
            'q' => $q,
        ]);
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    }

    #[Route('/new', name: 'admin_thematique_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $thematique = new Thematique();
<<<<<<< HEAD
        $form = $this->createForm(ThematiqueType::class, $thematique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
=======
        $form = $this->createForm(ThematiqueType::class, $thematique, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUpload($form->get('imageFile')->getData(), $thematique);
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
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
<<<<<<< HEAD
=======

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
        $form = $this->createForm(ThematiqueType::class, $thematique, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('imageFile')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                $this->handleImageUpload($uploadedFile, $thematique);
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'La thématique a été modifiée avec succès.');
            return $this->redirectToRoute('admin_thematique_show', ['id' => $thematique->getId()]);
        }

        return $this->render('admin/thematique/edit.html.twig', [
            'thematique' => $thematique,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_thematique_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $thematique = $this->thematiqueRepository->find($id);
        if ($thematique === null) {
            throw new NotFoundHttpException('Thématique introuvable.');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('admin_thematique_delete_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_thematique_show', ['id' => $id]);
        }

        $this->entityManager->remove($thematique);
        $this->entityManager->flush();
        $this->addFlash('success', 'La thématique a été supprimée. Les événements associés sont désormais sans thématique.');

        return $this->redirectToRoute('admin_thematique_index');
    }

    private function handleImageUpload(?UploadedFile $file, Thematique $thematique): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid('', true) . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/thematiques';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        try {
            $file->move($uploadDir, $newFilename);
            $thematique->setImage('/uploads/thematiques/' . $newFilename);
        } catch (FileException $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
        }
    }
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
}
