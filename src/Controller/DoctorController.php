<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use App\Form\DoctorDisponibiliteType;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class DoctorController extends AbstractController
{
    public function __construct(
        private readonly DisponibiliteRepository $disponibiliteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/medecin', name: 'doctor_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('doctor/dashboard.html.twig');
    }

    #[Route('/medecin/disponibilites', name: 'doctor_availability', methods: ['GET', 'POST'])]
    public function availability(Request $request): Response
    {
        $medecin = $this->getMedecin();
        $disponibilites = $this->disponibiliteRepository->findForListing($medecin);

        $disponibilite = new Disponibilite();
        $disponiilite->setMedecin($medecin);
        $form = $this->createForm(DoctorDisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $disponibilite->setMedecin($medecin);
            $this->entityManager->persist($disponibilite);
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité enregistrée.');
            return $this->redirectToRoute('doctor_availability');
        }

        return $this->render('doctor/availability/index.html.twig', [
            'disponibilites' => $disponibilites,
            'medecin' => $medecin,
            'form' => $form,
        ]);
    }

    #[Route('/medecin/disponibilites/nouvelle', name: 'doctor_availability_new', methods: ['GET', 'POST'])]
    public function newAvailability(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('login');
        }

        $disponibilite = new Disponibilite();
        $disponibilite->setMedecin($medecin);
        $form = $this->createForm(DoctorDisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $disponibilite->setMedecin($medecin);
            $this->entityManager->persist($disponibilite);
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité enregistrée.');
            return $this->redirectToRoute('doctor_availability');
        }

        return $this->render('doctor/availability/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/medecin/disponibilites/{id}/supprimer', name: 'doctor_availability_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAvailability(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        $disponibilite = $this->disponibiliteRepository->find($id);
        $canDelete = $disponibilite !== null && (
            ($medecin === null && $disponibilite->getMedecin() === null)
            || ($medecin !== null && $disponibilite->getMedecin() === $medecin)
        );
        if (!$canDelete) {
            $this->addFlash('error', 'Créneau introuvable.');
            return $this->redirectToRoute('doctor_availability');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_availability_delete_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_availability');
        }

        $this->entityManager->remove($disponibilite);
        $this->entityManager->flush();
        $this->addFlash('success', 'Créneau supprimé.');

        return $this->redirectToRoute('doctor_availability');
    }

    private function getMedecin(): ?Medcin
    {
        $user = $this->getUser();
        return $user instanceof Medcin ? $user : null;
    }
}
