<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
<<<<<<< HEAD
use App\Form\DoctorDisponibiliteType;
use App\Repository\DisponibiliteRepository;
use Doctrine\ORM\EntityManagerInterface;
=======
use App\Entity\Notification;
use App\Entity\Note;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\StatusRendezVous;
use App\Form\DoctorDisponibiliteType;
use App\Form\NoteType;
use App\Repository\DisponibiliteRepository;
use App\Repository\NotificationRepository;
use App\Repository\NoteRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class DoctorController extends AbstractController
{
    public function __construct(
        private readonly DisponibiliteRepository $disponibiliteRepository,
        private readonly EntityManagerInterface $entityManager,
<<<<<<< HEAD
    ) {
    }

    #[Route('/medecin', name: 'doctor_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        return $this->render('doctor/dashboard.html.twig');
=======
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
        
        // Calculer les statistiques réelles avec PHP
        $stats = [];
        if ($medecin !== null) {
            // Total des rendez-vous ce mois
            $currentMonth = new \DateTime('first day of this month');
            $totalRdv = $this->rendezVousRepository->countByMedecinAndDateRange($medecin, $currentMonth, new \DateTime());
            
            // Rendez-vous aujourd'hui
            $today = new \DateTime('today');
            $todayRdv = $this->rendezVousRepository->countByMedecinAndDate($medecin, $today);
            
            // Patients uniques (actifs)
            $uniquePatients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
            $activePatients = count($uniquePatients);
            
            // Taux de completion (rendez-vous confirmés / total)
            $confirmedRdv = $this->rendezVousRepository->countByMedecinAndStatus($medecin, \App\Enum\StatusRendezVous::CONFIRMER);
            $totalAllRdv = $this->rendezVousRepository->countByMedecin($medecin);
            $completionRate = $totalAllRdv > 0 ? round(($confirmedRdv / $totalAllRdv) * 100) : 0;
            
            // Rendez-vous en attente
            $pendingRdv = $this->rendezVousRepository->countByMedecinAndStatus($medecin, \App\Enum\StatusRendezVous::EN_ATTENTE);
            
            // Rendez-vous de cette semaine
            $weekStart = new \DateTime('monday this week');
            $weekEnd = new \DateTime('sunday this week');
            $weekRdv = $this->rendezVousRepository->countByMedecinAndDateRange($medecin, $weekStart, $weekEnd);
            
            // Statistiques avancées avec PHP
            $lastMonth = new \DateTime('first day of last month');
            $lastMonthEnd = new \DateTime('last day of last month');
            $lastMonthRdv = $this->rendezVousRepository->countByMedecinAndDateRange($medecin, $lastMonth, $lastMonthEnd);
            
            // Tendance mensuelle
            $monthlyTrend = $totalRdv > 0 ? round((($totalRdv - $lastMonthRdv) / $lastMonthRdv) * 100) : 0;
            
            // Rendez-vous annulés
            $cancelledRdv = $this->rendezVousRepository->countByMedecinAndStatus($medecin, \App\Enum\StatusRendezVous::ANNULER);
            
            // Rendez-vous par jour de la semaine (dernière semaine)
            $weekStats = [];
            for ($i = 0; $i < 7; $i++) {
                $day = new \DateTime('monday this week');
                $day->modify("+$i days");
                $dayRdv = $this->rendezVousRepository->countByMedecinAndDate($medecin, $day);
                $weekStats[] = [
                    'day' => $day->format('D'),
                    'count' => $dayRdv,
                    'percentage' => $weekRdv > 0 ? round(($dayRdv / $weekRdv) * 100) : 0
                ];
            }
            
            // Top 5 patients les plus actifs
            $topPatients = [];
            foreach ($uniquePatients as $patient) {
                $patientRdv = $this->rendezVousRepository->countByPatientAndMedecin($patient, $medecin);
                $topPatients[] = [
                    'patient' => $patient,
                    'count' => $patientRdv
                ];
            }
            usort($topPatients, function($a, $b) {
                return $b['count'] - $a['count'];
            });
            $topPatients = array_slice($topPatients, 0, 5);
            
            // Heures les plus populaires
            $hourStats = [];
            $allRdv = $this->rendezVousRepository->findByMedecinOrderByIdDesc($medecin);
            foreach ($allRdv as $rdv) {
                if ($rdv->getDisponibilite() && $rdv->getDisponibilite()->getHeureDebut()) {
                    $hour = $rdv->getDisponibilite()->getHeureDebut()->format('H');
                    if (!isset($hourStats[$hour])) {
                        $hourStats[$hour] = 0;
                    }
                    $hourStats[$hour]++;
                }
            }
            ksort($hourStats);
            
            $stats = [
                'total_rdv' => $totalRdv,
                'today_rdv' => $todayRdv,
                'active_patients' => $activePatients,
                'completion_rate' => $completionRate,
                'pending_rdv' => $pendingRdv,
                'week_rdv' => $weekRdv,
                'confirmed_rdv' => $confirmedRdv,
                'total_all_rdv' => $totalAllRdv,
                'last_month_rdv' => $lastMonthRdv,
                'monthly_trend' => $monthlyTrend,
                'cancelled_rdv' => $cancelledRdv,
                'week_stats' => $weekStats,
                'top_patients' => $topPatients,
                'hour_stats' => $hourStats
            ];
        }
        
        return $this->render('doctor/dashboard.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'stats' => $stats
        ]));
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    }

    #[Route('/medecin/disponibilites', name: 'doctor_availability', methods: ['GET', 'POST'])]
    public function availability(Request $request): Response
    {
        $medecin = $this->getMedecin();
<<<<<<< HEAD
        $disponibilites = $this->disponibiliteRepository->findForListing($medecin);
=======
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
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770

        $disponibilite = new Disponibilite();
        $disponibilite->setMedecin($medecin);
        $form = $this->createForm(DoctorDisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $disponibilite->setMedecin($medecin);
            $this->entityManager->persist($disponibilite);
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité enregistrée.');
<<<<<<< HEAD
            return $this->redirectToRoute('doctor_availability');
        }

        return $this->render('doctor/availability/index.html.twig', [
            'disponibilites' => $disponibilites,
            'medecin' => $medecin,
            'form' => $form,
        ]);
=======
            return $this->redirectToRoute('doctor_availability', ['q' => $search]);
        }

        return $this->render('doctor/availability/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'disponibilites' => $disponibilites,
            'medecin' => $medecin,
            'form' => $form,
            'search' => $search,
        ]));
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
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

<<<<<<< HEAD
        return $this->render('doctor/availability/new.html.twig', [
            'form' => $form,
        ]);
=======
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
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
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

<<<<<<< HEAD
=======
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

    #[Route('/medecin/notes/pdf', name: 'doctor_notes_pdf', methods: ['GET'])]
    public function downloadNotesPdf(): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('login');
        }

        // Récupérer toutes les notes du médecin
        $notes = $this->noteRepository->findByMedecinOrderByDate($medecin);

        // Configuration de DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        // Créer le PDF
        $dompdf = new Dompdf($options);

        // Générer le HTML pour le PDF
        $html = $this->renderView('doctor/notes/pdf.html.twig', [
            'notes' => $notes,
            'medecin' => $medecin,
            'date' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nom du fichier
        $filename = 'notes_' . $medecin->getNom() . '_' . date('Y-m-d') . '.pdf';

        // Retourner la réponse PDF
        return new Response(
            $dompdf->stream($filename, ['Attachment' => true]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
    }

    #[Route('/demo/notes', name: 'demo_notes', methods: ['GET', 'POST'])]
    public function demoNotes(Request $request): Response
    {
        // Si c'est une requête POST pour ajouter une note
        if ($request->isMethod('POST')) {
            $content = $request->request->get('content');
            $patientName = $request->request->get('patient_name');
            $patientEmail = $request->request->get('patient_email');
            
            if ($content && $patientName) {
                // Créer une nouvelle note
                $note = new Note();
                $note->setContenu($content);
                $note->setDateCreation(new \DateTime());
                
                // Créer un médecin fictif pour la démo
                $medecin = new class {
                    public function getId() { return 1; }
                    public function getPrenom() { return 'Jean'; }
                    public function getNom() { return 'Dupont'; }
                };
                
                // Créer un patient fictif pour la démo
                $patient = new class {
                    public function getId() { return rand(1000, 9999); }
                    public function getPrenom() { 
                        $parts = explode(' ', $patientName);
                        return $parts[0] ?? $patientName; 
                    }
                    public function getNom() { 
                        $parts = explode(' ', $patientName);
                        return $parts[1] ?? $patientName; 
                    }
                    public function getEmail() { return $patientEmail ?: 'email@example.com'; }
                };
                
                $note->setPatient($patient);
                $note->setMedecin($medecin);
                
                // Ajouter la note à la base de données
                $this->entityManager->persist($note);
                $this->entityManager->flush();
                
                // Rediriger pour éviter la double soumission
                return $this->redirectToRoute('demo_notes');
            }
        }
        
        // Récupérer toutes les notes pour l'affichage
        $allNotes = $this->noteRepository->findAll();
        
        return $this->render('demo/notes.html.twig', [
            'allNotes' => $allNotes
        ]);
    }

    #[Route('/demo/notes/pdf', name: 'demo_notes_pdf', methods: ['GET'])]
    public function demoNotesPdf(): Response
    {
        // Récupérer toutes les notes de la base de données
        $allNotes = $this->noteRepository->findAll();
        
        if (empty($allNotes)) {
            // Si aucune note dans la base, utiliser des données fictives
            $medecin = new class {
                public function getPrenom() { return 'Jean'; }
                public function getNom() { return 'Dupont'; }
            };

            $notes = [];
            
            // Patient 1
            $patient1 = new class {
                public function getId() { return 1; }
                public function getPrenom() { return 'Marie'; }
                public function getNom() { return 'Martin'; }
                public function getEmail() { return 'marie.martin@email.com'; }
            };
            
            $notes[] = new class {
                public function getPatient() { global $patient1; return $patient1; }
                public function getContenu() { return 'Patient présente une amélioration significative de ses symptômes. Continue le traitement actuel et recommande suivi régulier.'; }
                public function getDateCreation() { return new \DateTime('2024-01-15 10:30'); }
            };
            
            $notes[] = new class {
                public function getPatient() { global $patient1; return $patient1; }
                public function getContenu() { return 'Note de suivi : Le patient rapporte une bonne tolérance au traitement. Aucun effet secondaire observé. Prochain rendez-vous dans 2 semaines.'; }
                public function getDateCreation() { return new \DateTime('2024-01-20 14:15'); }
            };

            // Patient 2
            $patient2 = new class {
                public function getId() { return 2; }
                public function getPrenom() { return 'Pierre'; }
                public function getNom() { return 'Durand'; }
                public function getEmail() { return 'pierre.durand@email.com'; }
            };
            
            $notes[] = new class {
                public function getPatient() { global $patient2; return $patient2; }
                public function getContenu() { return 'Première consultation. Patient anxieux mais coopératif. Évaluation complète des symptômes réalisée. Plan de traitement établi.'; }
                public function getDateCreation() { return new \DateTime('2024-01-18 09:00'); }
            };

            // Patient 3
            $patient3 = new class {
                public function getId() { return 3; }
                public function getPrenom() { return 'Sophie'; }
                public function getNom() { return 'Bernard'; }
                public function getEmail() { return 'sophie.bernard@email.com'; }
            };
            
            $notes[] = new class {
                public function getPatient() { global $patient3; return $patient3; }
                public function getContenu() { return 'Réévaluation après 3 mois de traitement. Progression notable dans la gestion des symptômes. Adapter posologie si nécessaire.'; }
                public function getDateCreation() { return new \DateTime('2024-01-22 16:45'); }
            };
        } else {
            // Utiliser les vraies notes de la base de données
            // Prendre le premier médecin disponible pour l'affichage
            $firstNote = $allNotes[0];
            $medecin = $firstNote->getMedecin();
            $notes = $allNotes;
        }

        // Configuration de DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);

        // Créer le PDF
        $dompdf = new Dompdf($options);

        // Générer le HTML pour le PDF
        $html = $this->renderView('doctor/notes/pdf.html.twig', [
            'notes' => $notes,
            'medecin' => $medecin,
            'date' => new \DateTime()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nom du fichier
        $filename = 'real_notes_' . date('Y-m-d_H-i') . '.pdf';

        // Retourner la réponse PDF
        return new Response(
            $dompdf->stream($filename, ['Attachment' => true]),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ]
        );
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

>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    private function getMedecin(): ?Medcin
    {
        $user = $this->getUser();
        return $user instanceof Medcin ? $user : null;
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
