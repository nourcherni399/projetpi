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
        
        if ($medecin === null) {
            $this->addFlash('error', 'Médecin non trouvé.');
            return $this->redirectToRoute('login');
        }
        
        // Récupérer les statistiques
        $stats = [
            'total_rdv' => $this->rendezVousRepository->countByMedecin($medecin),
            'rdv_today' => $this->rendezVousRepository->countTodayByMedecin($medecin),
            'total_notes' => $this->noteRepository->countByMedecin($medecin),
            'total_patients' => $this->rendezVousRepository->countDistinctPatientsByMedecin($medecin),
            'upcoming_rdv' => $this->rendezVousRepository->findUpcomingByMedecin($medecin, 5),
            'recent_notes' => $this->noteRepository->findRecentByMedecin($medecin, 3),
        ];
        
        return $this->render('doctor/dashboard.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'stats' => $stats,
        ]));
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
        
        if ($medecin === null) {
            $this->addFlash('error', 'Médecin non trouvé.');
            return $this->redirectToRoute('login');
        }
        
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
        
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('login');
        }

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
        
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('login');
        }

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

    #[Route('/medecin/notes/{id}/edit', name: 'doctor_notes_edit', methods: ['GET', 'POST'])]
    public function editNote(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('login');
        }

        $note = $this->noteRepository->find($id);
        if ($note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note non trouvée.');
            return $this->redirectToRoute('doctor_notes');
        }

        $patients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
        $form = $this->createForm(NoteType::class, $note, ['patients' => $patients]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $note->setDateModification(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Note modifiée avec succès.');
            return $this->redirectToRoute('doctor_notes');
        }

        return $this->render('doctor/notes/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'note' => $note,
            'form' => $form,
            'patients' => $patients,
        ]));
    }

    #[Route('/medecin/notes/{id}/delete', name: 'doctor_notes_delete', methods: ['POST'])]
    public function deleteNote(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('login');
        }

        $note = $this->noteRepository->find($id);
        if ($note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note non trouvée.');
            return $this->redirectToRoute('doctor_notes');
        }

        if ($this->isCsrfTokenValid('delete' . $note->getId(), $request->request->get('_token'))) {
            try {
                $this->entityManager->remove($note);
                $this->entityManager->flush();
                $this->addFlash('success', 'Note supprimée avec succès.');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression. Veuillez réessayer.');
            }
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('doctor_notes');
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
        
        if ($user === null) {
            return null;
        }
        
        // Si l'utilisateur est déjà une instance de Medcin, le retourner directement
        if ($user instanceof Medcin) {
            return $user;
        }
        
        // Si l'utilisateur est de type User avec rôle MEDECIN, essayer de récupérer l'entité Medcin
        if ($user instanceof User && $user->getRole() === \App\Enum\UserRole::MEDECIN) {
            // Recharger l'utilisateur depuis la base de données avec la bonne classe
            $medecin = $this->entityManager->getRepository(Medcin::class)->find($user->getId());
            
            if ($medecin === null) {
                return null;
            }
            
            return $medecin;
        }
        
        return null;
    }

    /**
     * Calcule les statistiques pour les rendez-vous
     */
    private function calculateRendezVousStats(array $rendezVous, Medcin $medecin): array
    {
        $total = count($rendezVous);
        $confirmes = 0;
        $enAttente = 0;
        $annules = 0;
        $aujourdhui = 0;
        $thisWeek = 0;
        $thisMonth = 0;
        
        $today = new \DateTime('today');
        $weekStart = new \DateTime('monday this week');
        $weekEnd = new \DateTime('sunday this week');
        $monthStart = new \DateTime('first day of this month');
        $monthEnd = new \DateTime('last day of this month');
        
        foreach ($rendezVous as $rdv) {
            // Statuts
            switch ($rdv->getStatus()?->value) {
                case 'confirmer':
                    $confirmes++;
                    break;
                case 'annuler':
                    $annules++;
                    break;
                case 'en_attente':
                    $enAttente++;
                    break;
            }
            
            // Périodes
            $dateRdv = $rdv->getDateRdv();
            if ($dateRdv) {
                // Valider que la date est raisonnable (entre 1900 et 2100)
                $year = (int)$dateRdv->format('Y');
                if ($year < 1900 || $year > 2100) {
                    continue; // Ignorer les dates invalides
                }
                
                if ($dateRdv->format('Y-m-d') === $today->format('Y-m-d')) {
                    $aujourdhui++;
                }
                if ($dateRdv >= $weekStart && $dateRdv <= $weekEnd) {
                    $thisWeek++;
                }
                if ($dateRdv >= $monthStart && $dateRdv <= $monthEnd) {
                    $thisMonth++;
                }
            }
        }
        
        // Taux de confirmation
        $tauxConfirmation = $total > 0 ? round(($confirmes / $total) * 100, 1) : 0;
        
        return [
            'total' => $total,
            'confirmes' => $confirmes,
            'en_attente' => $enAttente,
            'annules' => $annules,
            'aujourdhui' => $aujourdhui,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'taux_confirmation' => $tauxConfirmation,
        ];
    }

    /**
     * Calcule les statistiques pour les notes
     */
    private function calculateNotesStats(array $notes, Medcin $medecin): array
    {
        $total = count($notes);
        $thisWeek = 0;
        $thisMonth = 0;
        $patientsWithNotes = [];
        $totalLength = 0;
        
        $today = new \DateTime('today');
        $weekStart = new \DateTime('monday this week');
        $weekEnd = new \DateTime('sunday this week');
        $monthStart = new \DateTime('first day of this month');
        $monthEnd = new \DateTime('last day of this month');
        
        foreach ($notes as $note) {
            // Compter les patients avec des notes
            if ($note->getPatient()) {
                $patientId = $note->getPatient()->getId();
                $patientsWithNotes[$patientId] = true;
            }
            
            // Longueur moyenne des notes
            $content = $note->getContenu();
            if ($content) {
                $totalLength += strlen($content);
            }
            
            // Périodes
            $dateCreation = $note->getDateCreation();
            if ($dateCreation) {
                if ($dateCreation >= $weekStart && $dateCreation <= $weekEnd) {
                    $thisWeek++;
                }
                if ($dateCreation >= $monthStart && $dateCreation <= $monthEnd) {
                    $thisMonth++;
                }
            }
        }
        
        $patientsCount = count($patientsWithNotes);
        $averageLength = $total > 0 ? round($totalLength / $total, 0) : 0;
        
        return [
            'total' => $total,
            'patients_with_notes' => $patientsCount,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'average_length' => $averageLength,
            'recent_activity' => $thisWeek > 0,
        ];
    }

    /**
     * Calcule les statistiques pour les notifications
     */
    private function calculateNotificationsStats(array $notifications, array $demandesRdv, Medcin $medecin): array
    {
        $totalNotifications = count($notifications);
        $unreadNotifications = 0;
        $todayNotifications = 0;
        $thisWeekNotifications = 0;
        
        $totalDemandes = count($demandesRdv);
        $urgentDemandes = 0;
        
        $today = new \DateTime('today');
        $weekStart = new \DateTime('monday this week');
        $weekEnd = new \DateTime('sunday this week');
        
        // Statistiques des notifications
        foreach ($notifications as $notif) {
            if (!$notif->isLu()) {
                $unreadNotifications++;
            }
            
            $createdAt = $notif->getCreatedAt();
            if ($createdAt) {
                if ($createdAt->format('Y-m-d') === $today->format('Y-m-d')) {
                    $todayNotifications++;
                }
                if ($createdAt >= $weekStart && $createdAt <= $weekEnd) {
                    $thisWeekNotifications++;
                }
            }
        }
        
        // Statistiques des demandes
        foreach ($demandesRdv as $demande) {
            $dateRdv = $demande->getDateRdv();
            if ($dateRdv && $dateRdv <= $today->modify('+2 days')) {
                $urgentDemandes++;
            }
        }
        
        return [
            'total_notifications' => $totalNotifications,
            'unread_notifications' => $unreadNotifications,
            'today_notifications' => $todayNotifications,
            'this_week_notifications' => $thisWeekNotifications,
            'total_demandes' => $totalDemandes,
            'urgent_demandes' => $urgentDemandes,
            'unread_rate' => $totalNotifications > 0 ? round(($unreadNotifications / $totalNotifications) * 100, 1) : 0,
        ];
    }
}