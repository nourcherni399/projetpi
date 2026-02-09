<?php

declare(strict_types=1);

namespace App\Controller;

<<<<<<< HEAD
use App\Repository\DisponibiliteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
=======
use App\Entity\Disponibilite;
use App\Form\DisponibiliteType;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/disponibilites')]
final class DisponibiliteController extends AbstractController
{
    public function __construct(
        private readonly DisponibiliteRepository $disponibiliteRepository,
<<<<<<< HEAD
=======
        private readonly EntityManagerInterface $entityManager,
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    ) {
    }

    #[Route('', name: 'admin_disponibilite_index', methods: ['GET'])]
    public function index(): Response
    {
<<<<<<< HEAD
        $disponibilites = $this->disponibiliteRepository->findAll();
        return $this->render('admin/disponibilite/index.html.twig', ['disponibilites' => $disponibilites]);
    }
}
=======
        $disponibilites = $this->disponibiliteRepository->findBy([], ['id' => 'DESC']);
        return $this->render('admin/disponibilite/index.html.twig', ['disponibilites' => $disponibilites]);
    }

    #[Route('/new', name: 'admin_disponibilite_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $disponibilite = new Disponibilite();
        $form = $this->createForm(DisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($disponibilite);
            $this->entityManager->flush();
            $this->addFlash('success', 'La disponibilité a été créée avec succès.');

            return $this->redirectToRoute('admin_disponibilite_index');
        }

        return $this->render('admin/disponibilite/new.html.twig', [
            'disponibilite' => $disponibilite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_disponibilite_show', methods: ['GET'])]
    public function show(Disponibilite $disponibilite): Response
    {
        return $this->render('admin/disponibilite/show.html.twig', [
            'disponibilite' => $disponibilite,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_disponibilite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Disponibilite $disponibilite): Response
    {
        $form = $this->createForm(DisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'La disponibilité a été mise à jour avec succès.');

            return $this->redirectToRoute('admin_disponibilite_index');
        }

        return $this->render('admin/disponibilite/edit.html.twig', [
            'disponibilite' => $disponibilite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_disponibilite_delete', methods: ['POST'])]
    public function delete(Request $request, Disponibilite $disponibilite): Response
    {
        $id = $disponibilite->getId();
        if ($id === null) {
            return $this->redirectToRoute('admin_disponibilite_index');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('admin_disponibilite_delete_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_disponibilite_index');
        }

        $this->entityManager->remove($disponibilite);
        $this->entityManager->flush();
        $this->addFlash('success', 'La disponibilité a été supprimée avec succès.');

        return $this->redirectToRoute('admin_disponibilite_index');
    }
}
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
