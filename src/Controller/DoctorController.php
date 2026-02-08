<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use App\Entity\Notification;
use App\Entity\Note;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\StatusRendezVous;
use App\Entity\User;
use App\Form\DoctorDisponibiliteType;
use App\Form\NoteType;
use App\Form\ProfileType;
use App\Repository\DisponibiliteRepository;
use App\Repository\NotificationRepository;
use App\Repository\NoteRepository;
use App\Repository\RendezVousRepository;
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
        private readonly NoteRepository $noteRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly RendezVousRepository $rendezVousRepository,
    ) {
    }

    private function getDoctorTemplateVars(?Medcin $medecin): array
    {
        if ($medecin === null) {
            return ['notif_count' => 0];
        }
        return ['notif_count' => $this->notificationRepository->countUnreadByDestinataire($medecin)];
    }

    #[Route('/medecin', name: 'doctor_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $medecin = $this->getMedecin();
        return $this->render('doctor/dashboard.html.twig', $this->getDoctorTemplateVars($medecin));
    }

    #[Route('/medecin/mon-profil', name: 'doctor_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $medecin = $this->getMedecin();
        $form = $this->createForm(ProfileType::class, $user, ['data_class' => $user::class]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('doctor_profile');
        }
        return $this->render('doctor/profile/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'user' => $user,
            'form' => $form,
        ]));
    }

    #[Route('/medecin/disponibilites', name: 'doctor_availability', methods: ['GET', 'POST'])]
    public function availability(Request $request): Response
    {
        $medecin = $this->getMedecin();
        $search = (string) $request->query->get('q', '');
        $search = \trim($search);

        $disponibilites = $this->disponibiliteRepository->findForListing($medecin);
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $disponibilites = \array_values(\array_filter($disponibilites, static function (Disponibilite $d) use ($lower): bool {
                $jour = $d->getJour()?->value ?? '';
                $heureDebut = $d->getHeureDebut()?->format('H:i') ?? '';
                $heureFin = $d->getHeureFin()?->format('H:i') ?? '';
                $duree = (string) $d->getDuree();

                return \str_contains(mb_strtolower($jour), $lower)
                    || \str_contains(mb_strtolower($heureDebut), $lower)
                    || \str_contains(mb_strtolower($heureFin), $lower)
                    || \str_contains(mb_strtolower($duree), $lower);
            }));
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
            return $this->redirectToRoute('doctor_availability', ['q' => $search]);
        }

        return $this->render('doctor/availability/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'disponibilites' => $disponibilites,
            'medecin' => $medecin,
            'form' => $form,
            'search' => $search,
        ]));
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

        return $this->render('doctor/availability/new.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'form' => $form,
        ]));
    }

    #[Route('/medecin/disponibilites/{id}/modifier', name: 'doctor_availability_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editAvailability(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        $disponibilite = $this->disponibiliteRepository->find($id);
        $canEdit = $disponibilite !== null && (
            ($medecin === null && $disponibilite->getMedecin() === null)
            || ($medecin !== null && $disponibilite->getMedecin() === $medecin)
        );

        if (!$canEdit) {
            $this->addFlash('error', 'Créneau introuvable.');
            return $this->redirectToRoute('doctor_availability');
        }

        $form = $this->createForm(DoctorDisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité mise à jour.');
            return $this->redirectToRoute('doctor_availability');
        }

        return $this->render('doctor/availability/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'form' => $form,
            'disponibilite' => $disponibilite,
        ]));
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

    #[Route('/medecin/notes', name: 'doctor_notes', methods: ['GET', 'POST'])]
    public function notes(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('login');
        }

        $patients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
        $notes = $this->noteRepository->findByMedecinOrderByDate($medecin);

        $note = new Note();
        $note->setMedecin($medecin);
        $form = $this->createForm(NoteType::class, $note, ['patients' => $patients]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note->setMedecin($medecin);
            $this->entityManager->persist($note);
            $this->entityManager->flush();
            $this->addFlash('success', 'Note enregistrée.');
            return $this->redirectToRoute('doctor_notes');
        }

        return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'notes' => $notes,
            'form' => $form,
            'patients' => $patients,
        ]));
    }

    #[Route('/medecin/rendez-vous', name: 'doctor_rendezvous', methods: ['GET'])]
    public function rendezvous(): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('login');
        }

        $rendezVous = $this->rendezVousRepository->findByMedecinOrderByIdDesc($medecin);

        return $this->render('doctor/rendezvous/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'rendez_vous' => $rendezVous,
        ]));
    }

    #[Route('/medecin/notifications', name: 'doctor_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('login');
        }
        $notifications = $this->notificationRepository->findByDestinataireOrderByCreatedDesc($medecin);
        $demandesRdv = $this->rendezVousRepository->findEnAttenteByMedecin($medecin);
        return $this->render('doctor/notifications/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'notifications' => $notifications,
            'demandes_rdv' => $demandesRdv,
        ]));
    }

    #[Route('/medecin/rendez-vous/{id}/accepter', name: 'doctor_rendezvous_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousAccept(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('login');
        }
        $rdv = $this->rendezVousRepository->find($id);
        if ($rdv === null || $rdv->getMedecin() !== $medecin || $rdv->getStatus() !== StatusRendezVous::EN_ATTENTE) {
            $this->addFlash('error', 'Demande introuvable.');
            return $this->redirectToRoute('doctor_notifications');
        }
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_rdv_accept_' . $id, $token)) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('doctor_notifications');
        }
        $rdv->setStatus(StatusRendezVous::CONFIRMER);
        $this->entityManager->flush();

        $patient = $rdv->getPatient();
        if ($patient instanceof Patient) {
            $notif = new Notification();
            $notif->setDestinataire($patient);
            $notif->setType(Notification::TYPE_RDV_ACCEPTE);
            $notif->setRendezVous($rdv);
            $this->entityManager->persist($notif);
            $this->entityManager->flush();
        }

        $this->markDoctorNotificationForRdvAsRead($medecin, $rdv);
        $this->addFlash('success', 'Rendez-vous accepté. Le patient a été notifié.');
        return $this->redirectToRoute('doctor_notifications');
    }

    #[Route('/medecin/rendez-vous/{id}/refuser', name: 'doctor_rendezvous_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousRefuse(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('login');
        }
        $rdv = $this->rendezVousRepository->find($id);
        if ($rdv === null || $rdv->getMedecin() !== $medecin || $rdv->getStatus() !== StatusRendezVous::EN_ATTENTE) {
            $this->addFlash('error', 'Demande introuvable.');
            return $this->redirectToRoute('doctor_notifications');
        }
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_rdv_refuse_' . $id, $token)) {
            $this->addFlash('error', 'Jeton invalide.');
            return $this->redirectToRoute('doctor_notifications');
        }
        $rdv->setStatus(StatusRendezVous::ANNULER);
        $this->entityManager->flush();

        $patient = $rdv->getPatient();
        if ($patient instanceof Patient) {
            $notif = new Notification();
            $notif->setDestinataire($patient);
            $notif->setType(Notification::TYPE_RDV_REFUSE);
            $notif->setRendezVous($rdv);
            $this->entityManager->persist($notif);
            $this->entityManager->flush();
        }

        $this->markDoctorNotificationForRdvAsRead($medecin, $rdv);
        $this->addFlash('success', 'Demande refusée. Le patient a été notifié.');
        return $this->redirectToRoute('doctor_notifications');
    }

    private function markDoctorNotificationForRdvAsRead(Medcin $medecin, RendezVous $rdv): void
    {
        $notifs = $this->notificationRepository->findByDestinataireOrderByCreatedDesc($medecin);
        foreach ($notifs as $n) {
            if ($n->getRendezVous() === $rdv && $n->getType() === Notification::TYPE_DEMANDE_RDV) {
                $n->setLu(true);
                $this->entityManager->flush();
                break;
            }
        }
    }

    private function getMedecin(): ?Medcin
    {
        $user = $this->getUser();
        return $user instanceof Medcin ? $user : null;
    }
}