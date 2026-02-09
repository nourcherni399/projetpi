<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RendezVous;
use App\Form\RendezVousType;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/rendez-vous')]
final class RendezVousController extends AbstractController
{
    public function __construct(
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_rendez_vous_index', methods: ['GET'])]
    public function index(): Response
    {
        $rendezVous = $this->rendezVousRepository->findBy([], ['id' => 'DESC']);
        return $this->render('admin/rendez_vous/index.html.twig', ['rendez_vous_list' => $rendezVous]);
    }

    #[Route('/new', name: 'admin_rendez_vous_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $rendezVous = new RendezVous();
        $form = $this->createForm(RendezVousType::class, $rendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($rendezVous->getNotePatient() === null) {
                $rendezVous->setNotePatient('vide');
            }
            $this->entityManager->persist($rendezVous);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le rendez-vous a été créé avec succès.');

            return $this->redirectToRoute('admin_rendez_vous_index');
        }

        return $this->render('admin/rendez_vous/new.html.twig', [
            'rendez_vous' => $rendezVous,
            'form' => $form,
        ]);
    }
}
