<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use App\Entity\Notification;
use App\Entity\Note;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Enum\StatusRendezVous;
use App\Form\DoctorDisponibiliteType;
use App\Form\DoctorRendezVousEditType;
use App\Form\NoteType;
use App\Form\ProfileType;
use App\Repository\DisponibiliteRepository;
use App\Repository\NotificationRepository;
use App\Repository\NoteRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
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
            return $this->redirectToRoute('app_login');
        }

        $stats = [
            'total_rdv' => $this->rendezVousRepository->countByMedecin($medecin),
            'rdv_today' => $this->rendezVousRepository->countTodayByMedecin($medecin),
            'total_notes' => $this->noteRepository->countByMedecin($medecin),
            'total_patients' => $this->rendezVousRepository->countDistinctPatientsByMedecin($medecin),
            'upcoming_rdv' => $this->rendezVousRepository->findUpcomingByMedecin($medecin, 5),
            'recent_notes' => $this->noteRepository->findRecentByMedecin($medecin, 3),
            'patient_notes_from_rdv' => $this->rendezVousRepository->findRecentWithPatientNotesByMedecin($medecin, 5),
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
        $search = (string) $request->query->get('q', '');
        $search = \trim($search);
        $order = (string) $request->query->get('order', 'asc');
        $order = \in_array(strtolower($order), ['asc', 'desc'], true) ? strtolower($order) : 'asc';

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

        if ($disponibilites !== []) {
            \usort($disponibilites, static function (Disponibilite $a, Disponibilite $b) use ($order): int {
                $jourA = $a->getJour()?->value ?? '';
                $jourB = $b->getJour()?->value ?? '';
                if ($jourA === $jourB) {
                    $timeA = $a->getHeureDebut()?->format('H:i') ?? '';
                    $timeB = $b->getHeureDebut()?->format('H:i') ?? '';
                    $cmp = \strcmp($timeA, $timeB);
                } else {
                    $cmp = \strcmp($jourA, $jourB);
                }

                return $order === 'asc' ? $cmp : -$cmp;
            });
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
            return $this->redirectToRoute('doctor_availability', ['q' => $search, 'order' => $order], Response::HTTP_SEE_OTHER);
        }

        return $this->render('doctor/availability/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'disponibilites' => $disponibilites,
            'medecin' => $medecin,
            'form' => $form,
            'search' => $search,
            'order' => $order,
        ]));
    }

    #[Route('/medecin/disponibilites/nouvelle', name: 'doctor_availability_new', methods: ['GET', 'POST'])]
    public function newAvailability(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        $disponibilite = new Disponibilite();
        $disponibilite->setMedecin($medecin);
        $form = $this->createForm(DoctorDisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $jour = $disponibilite->getJour();
            $heureDebut = $disponibilite->getHeureDebut();
            $heureFin = $disponibilite->getHeureFin();

            if ($jour === null) {
                $this->addFlash('error', 'Le jour est obligatoire.');
                return $this->render('doctor/availability/new.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'form' => $form,
                ]));
            }

            if ($heureDebut === null || $heureFin === null) {
                $this->addFlash('error', 'Les heures de début et de fin sont obligatoires.');
                return $this->render('doctor/availability/new.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'form' => $form,
                ]));
            }

            if ($heureFin <= $heureDebut) {
                $this->addFlash('error', 'L\'heure de fin doit être après l\'heure de début.');
                return $this->render('doctor/availability/new.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'form' => $form,
                ]));
            }

            $interval = $heureDebut->diff($heureFin);
            if ($interval->h > 8 || ($interval->h == 8 && $interval->i > 0)) {
                $this->addFlash('error', 'La disponibilité ne peut pas dépasser 8 heures.');
                return $this->render('doctor/availability/new.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'form' => $form,
                ]));
            }

            $existingDispos = $this->disponibiliteRepository->findByMedecinAndJour($medecin, $jour);
            foreach ($existingDispos as $existing) {
                if (($heureDebut >= $existing->getHeureDebut() && $heureDebut < $existing->getHeureFin())
                    || ($heureFin > $existing->getHeureDebut() && $heureFin <= $existing->getHeureFin())
                    || ($heureDebut <= $existing->getHeureDebut() && $heureFin >= $existing->getHeureFin())) {
                    $this->addFlash('error', 'Cette disponibilité chevauche une disponibilité existante.');
                    return $this->render('doctor/availability/new.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                        'form' => $form,
                    ]));
                }
            }

            try {
                $disponibilite->setMedecin($medecin);
                $this->entityManager->persist($disponibilite);
                $this->entityManager->flush();
                $this->addFlash('success', 'Disponibilité enregistrée avec succès.');
                return $this->redirectToRoute('doctor_availability');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.');
                return $this->render('doctor/availability/new.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'form' => $form,
                ]));
            }
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
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        if ($id <= 0) {
            $this->addFlash('error', 'Identifiant de disponibilité invalide.');
            return $this->redirectToRoute('doctor_availability');
        }

        $disponibilite = $this->disponibiliteRepository->find($id);

        if ($disponibilite === null) {
            $this->addFlash('error', 'Disponibilité introuvable.');
            return $this->redirectToRoute('doctor_availability');
        }

        if ($disponibilite->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Cette disponibilité ne vous appartient pas.');
            return $this->redirectToRoute('doctor_availability');
        }

        $rendezVousConfirmes = $this->rendezVousRepository->findByDisponibiliteAndStatus($disponibilite, StatusRendezVous::CONFIRMER);
        if (!empty($rendezVousConfirmes)) {
            $this->addFlash('error', 'Impossible de supprimer cette disponibilité car des rendez-vous confirmés y sont associés.');
            return $this->redirectToRoute('doctor_availability');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_availability_delete_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('doctor_availability');
        }

        try {
            $this->entityManager->remove($disponibilite);
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité supprimée avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la suppression. Veuillez réessayer.');
        }

        return $this->redirectToRoute('doctor_availability');
    }

    #[Route('/medecin/notes', name: 'doctor_notes', methods: ['GET', 'POST'])]
    public function notes(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        $patients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
        $notes = $this->noteRepository->findByMedecinOrderByDate($medecin);
        $stats = $this->calculateNotesStats($notes, $medecin);

        $note = new Note();
        $note->setMedecin($medecin);
        $form = $this->createForm(NoteType::class, $note, ['patients' => $patients]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $patient = $note->getPatient();
            $contenu = $note->getContenu();

            if ($patient === null) {
                $this->addFlash('error', 'Veuillez sélectionner un patient.');
                return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'notes' => $notes,
                    'form' => $form,
                    'patients' => $patients,
                    'stats' => $stats,
                ]));
            }

            if (empty($contenu) || trim($contenu) === '') {
                $this->addFlash('error', 'Le contenu de la note ne peut pas être vide.');
                return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'notes' => $notes,
                    'form' => $form,
                    'patients' => $patients,
                    'stats' => $stats,
                ]));
            }

            $contenuLength = strlen(trim($contenu));
            if ($contenuLength < 3) {
                $this->addFlash('error', 'La note doit contenir au moins 3 caractères.');
                return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'notes' => $notes,
                    'form' => $form,
                    'patients' => $patients,
                    'stats' => $stats,
                ]));
            }

            if ($contenuLength > 5000) {
                $this->addFlash('error', 'La note ne peut pas dépasser 5000 caractères.');
                return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'notes' => $notes,
                    'form' => $form,
                    'patients' => $patients,
                    'stats' => $stats,
                ]));
            }

            $hasRendezVous = $this->rendezVousRepository->findByMedecinAndPatient($medecin, $patient);
            if (empty($hasRendezVous)) {
                $this->addFlash('error', 'Vous ne pouvez ajouter une note qu\'à un patient avec qui vous avez eu un rendez-vous.');
                return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'notes' => $notes,
                    'form' => $form,
                    'patients' => $patients,
                    'stats' => $stats,
                ]));
            }

            $recentNotes = array_slice($this->noteRepository->findByMedecinAndPatient($medecin, $patient), 0, 5);
            foreach ($recentNotes as $recentNote) {
                if (trim($recentNote->getContenu()) === trim($contenu)) {
                    $this->addFlash('error', 'Une note identique existe déjà. Veuillez vérifier avant d\'ajouter.');
                    return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                        'notes' => $notes,
                        'form' => $form,
                        'patients' => $patients,
                        'stats' => $stats,
                    ]));
                }
            }

            try {
                $note->setMedecin($medecin);
                $note->setDateCreation(new \DateTimeImmutable());
                $this->entityManager->persist($note);
                $this->entityManager->flush();
                $this->addFlash('success', 'Note enregistrée avec succès.');
                return $this->redirectToRoute('doctor_notes');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.');
                return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'notes' => $notes,
                    'form' => $form,
                    'patients' => $patients,
                    'stats' => $stats,
                ]));
            }
        }

        return $this->render('doctor/notes/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'notes' => $notes,
            'form' => $form,
            'patients' => $patients,
            'stats' => $stats,
        ]));
    }

    #[Route('/medecin/notes/{id}', name: 'doctor_note_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function noteShow(int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }
        $note = $this->noteRepository->find($id);
        if ($note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        return $this->render('doctor/notes/show.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'note' => $note,
        ]));
    }

    #[Route('/medecin/notes/{id}/modifier', name: 'doctor_note_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function noteEdit(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }
        $note = $this->noteRepository->find($id);
        if ($note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        $patients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
        $form = $this->createForm(NoteType::class, $note, ['patients' => $patients]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contenu = $note->getContenu();
            if (strlen(trim($contenu)) < 3) {
                $this->addFlash('error', 'La note doit contenir au moins 3 caractères.');
                return $this->render('doctor/notes/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'note' => $note,
                    'form' => $form,
                ]));
            }
            if (strlen(trim($contenu)) > 5000) {
                $this->addFlash('error', 'La note ne peut pas dépasser 5000 caractères.');
                return $this->render('doctor/notes/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
                    'note' => $note,
                    'form' => $form,
                ]));
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Note mise à jour avec succès.');
            return $this->redirectToRoute('doctor_notes');
        }

        return $this->render('doctor/notes/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'note' => $note,
            'form' => $form,
        ]));
    }

    #[Route('/medecin/notes/{id}/supprimer', name: 'doctor_note_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function noteDelete(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }
        $note = $this->noteRepository->find($id);
        if ($note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_note_delete_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_notes');
        }
        $this->entityManager->remove($note);
        $this->entityManager->flush();
        $this->addFlash('success', 'Note supprimée.');
        return $this->redirectToRoute('doctor_notes');
    }

    #[Route('/medecin/notes/export/pdf', name: 'doctor_notes_export_pdf', methods: ['GET'])]
    public function notesExportPdf(): Response
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->addFlash('error', 'Export PDF indisponible : installez la dépendance avec "composer require dompdf/dompdf" puis réessayez.');
            return $this->redirectToRoute('doctor_notes');
        }
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }
        $notes = $this->noteRepository->findByMedecinOrderByDate($medecin);
        $html = $this->renderView('doctor/notes/pdf_list.html.twig', [
            'medecin' => $medecin,
            'notes' => $notes,
        ]);
        $dompdf = new Dompdf();
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        $dompdf->getOptions()->set('defaultFont', 'DejaVu Sans');
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $filename = 'notes-patients-' . (new \DateTimeImmutable())->format('Y-m-d') . '.pdf';
        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/medecin/notes/{id}/export/pdf', name: 'doctor_note_export_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function noteExportPdf(int $id): Response
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->addFlash('error', 'Export PDF indisponible : installez la dépendance avec "composer require dompdf/dompdf" puis réessayez.');
            return $this->redirectToRoute('doctor_notes');
        }
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }
        $note = $this->noteRepository->find($id);
        if ($note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        $html = $this->renderView('doctor/notes/pdf_single.html.twig', ['note' => $note]);
        $dompdf = new Dompdf();
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        $dompdf->getOptions()->set('defaultFont', 'DejaVu Sans');
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();
        $patient = $note->getPatient();
        $base = $patient ? (trim(($patient->getPrenom() ?? '') . '-' . ($patient->getNom() ?? '')) ?: 'note-' . $id) : 'note-' . $id;
        $datePart = $note->getDateCreation()?->format('Y-m-d') ?? (new \DateTimeImmutable())->format('Y-m-d');
        $filename = preg_replace('/[^a-zA-Z0-9\-]/', '-', $base) . '-' . $datePart . '.pdf';
        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/medecin/rendez-vous', name: 'doctor_rendezvous', methods: ['GET'])]
    public function rendezvous(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        $order = (string) $request->query->get('order', 'asc');
        $order = \in_array(strtolower($order), ['asc', 'desc'], true) ? strtolower($order) : 'asc';

        $rendezVous = $this->rendezVousRepository->findByMedecinOrderByDate($medecin, $order);
        $stats = $this->calculateRendezVousStats($rendezVous, $medecin);

        $upcomingRdv = array_filter($rendezVous, function ($rdv) {
            return $rdv->getDateRdv() && $rdv->getDateRdv() >= new \DateTime('today');
        });
        $todayRdv = array_filter($rendezVous, function ($rdv) {
            return $rdv->getDateRdv() && $rdv->getDateRdv()->format('Y-m-d') === (new \DateTime('today'))->format('Y-m-d');
        });

        return $this->render('doctor/rendezvous/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'rendez_vous' => $rendezVous,
            'stats' => $stats,
            'upcoming_count' => count($upcomingRdv),
            'today_count' => count($todayRdv),
            'recent_rdv' => array_slice($rendezVous, 0, 5),
            'order' => $order,
        ]));
    }

    #[Route('/medecin/rendez-vous/{id}/modifier', name: 'doctor_rendezvous_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function rendezvousEdit(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        $rdv = $this->rendezVousRepository->find($id);
        if ($rdv === null) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_rendezvous');
        }

        if ($rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Ce rendez-vous ne vous appartient pas.');
            return $this->redirectToRoute('doctor_rendezvous');
        }

        $form = $this->createForm(DoctorRendezVousEditType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Rendez-vous mis à jour avec succès.');
            return $this->redirectToRoute('doctor_rendezvous');
        }

        return $this->render('doctor/rendezvous/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'rdv' => $rdv,
            'form' => $form,
        ]));
    }

    #[Route('/medecin/rendez-vous/{id}/supprimer', name: 'doctor_rendezvous_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousDelete(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        $rdv = $this->rendezVousRepository->find($id);
        if ($rdv === null) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_rendezvous');
        }

        if ($rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Ce rendez-vous ne vous appartient pas.');
            return $this->redirectToRoute('doctor_rendezvous');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_rendezvous_delete_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('doctor_rendezvous');
        }

        $this->entityManager->remove($rdv);
        $this->entityManager->flush();
        $this->addFlash('success', 'Rendez-vous supprimé avec succès.');

        return $this->redirectToRoute('doctor_rendezvous');
    }

    #[Route('/medecin/rendez-vous/{id}/accepter', name: 'doctor_rendezvous_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousAccept(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        if ($id <= 0) {
            $this->addFlash('error', 'Identifiant de rendez-vous invalide.');
            return $this->redirectToRoute('doctor_notifications');
        }

        $rdv = $this->rendezVousRepository->find($id);
        if ($rdv === null) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_notifications');
        }

        if ($rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Ce rendez-vous ne vous appartient pas.');
            return $this->redirectToRoute('doctor_notifications');
        }

        if ($rdv->getStatus() !== StatusRendezVous::EN_ATTENTE) {
            $this->addFlash('error', 'Ce rendez-vous a déjà été traité.');
            return $this->redirectToRoute('doctor_notifications');
        }

        if ($rdv->getDateRdv() && $rdv->getDateRdv() < new \DateTime('today')) {
            $this->addFlash('error', 'Ce rendez-vous est dans le passé et ne peut plus être accepté.');
            return $this->redirectToRoute('doctor_notifications');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_rdv_accept_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('doctor_notifications');
        }

        try {
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
            $this->addFlash('success', 'Rendez-vous accepté avec succès. Le patient a été notifié.');
            return $this->redirectToRoute('doctor_notifications');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'acceptation. Veuillez réessayer.');
            return $this->redirectToRoute('doctor_notifications');
        }
    }

    #[Route('/medecin/notifications', name: 'doctor_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('app_login');
        }

        $notifications = $this->notificationRepository->findByDestinataireOrderByCreatedDesc($medecin);
        $demandesRdv = $this->rendezVousRepository->findEnAttenteByMedecin($medecin);
        $stats = $this->calculateNotificationsStats($notifications, $demandesRdv, $medecin);

        return $this->render('doctor/notifications/index.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'notifications' => $notifications,
            'demandes_rdv' => $demandesRdv,
            'stats' => $stats,
        ]));
    }

    #[Route('/medecin/rendez-vous/{id}/refuser', name: 'doctor_rendezvous_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousRefuse(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        if ($id <= 0) {
            $this->addFlash('error', 'Identifiant de rendez-vous invalide.');
            return $this->redirectToRoute('doctor_notifications');
        }

        $rdv = $this->rendezVousRepository->find($id);
        if ($rdv === null) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_notifications');
        }

        if ($rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Ce rendez-vous ne vous appartient pas.');
            return $this->redirectToRoute('doctor_notifications');
        }

        if ($rdv->getStatus() !== StatusRendezVous::EN_ATTENTE) {
            $this->addFlash('error', 'Ce rendez-vous a déjà été traité.');
            return $this->redirectToRoute('doctor_notifications');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_rdv_refuse_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('doctor_notifications');
        }

        try {
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
            $this->addFlash('success', 'Demande refusée avec succès. Le patient a été notifié.');
            return $this->redirectToRoute('doctor_notifications');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Une erreur est survenue lors du refus. Veuillez réessayer.');
            return $this->redirectToRoute('doctor_notifications');
        }
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

        if (!$user instanceof Medcin) {
            return null;
        }

        if (!$user->isActive()) {
            return null;
        }

        if (empty($user->getNom()) || empty($user->getPrenom()) || empty($user->getEmail())) {
            return null;
        }

        return $user;
    }

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

            $dateRdv = $rdv->getDateRdv();
            if ($dateRdv) {
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

    private function calculateNotesStats(array $notes, Medcin $medecin): array
    {
        $total = count($notes);
        $thisWeek = 0;
        $thisMonth = 0;
        $patientsWithNotes = [];
        $totalLength = 0;

        $weekStart = new \DateTime('monday this week');
        $weekEnd = new \DateTime('sunday this week');
        $monthStart = new \DateTime('first day of this month');
        $monthEnd = new \DateTime('last day of this month');

        foreach ($notes as $note) {
            if ($note->getPatient()) {
                $patientsWithNotes[$note->getPatient()->getId()] = true;
            }

            $content = $note->getContenu();
            if ($content) {
                $totalLength += strlen($content);
            }

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

        $limitUrgent = (new \DateTime('today'))->modify('+2 days');
        foreach ($demandesRdv as $demande) {
            $dateRdv = $demande->getDateRdv();
            if ($dateRdv !== null && $dateRdv <= $limitUrgent) {
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