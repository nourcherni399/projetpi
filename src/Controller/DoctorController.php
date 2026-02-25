<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Medcin;
use App\Entity\Notification;
use App\Entity\Note;
use App\Entity\Patient;
use App\Entity\RendezVous;
<<<<<<< HEAD
use App\Entity\User;
use App\Enum\StatusRendezVous;
use App\Form\DoctorDisponibiliteType;
use App\Form\DoctorRendezVousEditType;
use App\Form\NoteType;
use App\Form\ProfileType;
=======
use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use App\Form\DoctorDisponibiliteType;
use App\Form\DoctorRendezVousCreateType;
use App\Form\DoctorRendezVousEditType;
use App\Form\NoteType;
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
use App\Repository\DisponibiliteRepository;
use App\Repository\NotificationRepository;
use App\Repository\NoteRepository;
use App\Repository\RendezVousRepository;
use App\Service\GoogleCalendarService;
use App\Service\RappelSmsService;
use App\Service\RendezVousConfirmationMailer;
use App\Service\SmsStatusChecker;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DoctorController extends AbstractController
{
    public function __construct(
        private readonly DisponibiliteRepository $disponibiliteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository,
        private readonly NoteRepository $noteRepository,
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly RendezVousConfirmationMailer $rendezVousConfirmationMailer,
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly RappelSmsService $rappelSmsService,
        private readonly SmsStatusChecker $smsStatusChecker,
    ) {
    }

    #[Route('/medecin', name: 'doctor_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $medecin = $this->getMedecin();
<<<<<<< HEAD
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
=======

        $stats = null;
        if ($medecin !== null) {
            $stats = [
                'total_patients' => $this->rendezVousRepository->countDistinctPatientsByMedecin($medecin),
                'total_rdv' => $this->rendezVousRepository->countByMedecin($medecin),
                'rdv_today' => $this->rendezVousRepository->countTodayByMedecin($medecin),
                'total_notes' => $this->noteRepository->countByMedecin($medecin),
                'upcoming_rdv' => $this->rendezVousRepository->findUpcomingByMedecin($medecin, 6),
                'patient_notes_from_rdv' => $this->rendezVousRepository->findRecentWithPatientNotesByMedecin($medecin, 5),
            ];
        }

        return $this->render('doctor/dashboard.html.twig', [
            'search' => $search,
            'stats' => $stats,
        ]);
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
    }

    #[Route('/medecin/recherche', name: 'doctor_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        if ($q === '') {
            return $this->redirectToRoute('doctor_dashboard');
        }
        $lower = mb_strtolower($q);

        // Rediriger vers l'interface adaptée selon la recherche
        if (str_contains($lower, 'disponibil') || str_contains($lower, 'créneau') || str_contains($lower, 'horaire') || $this->looksLikeDate($q)) {
            return $this->redirectToRoute('doctor_availability', ['q' => $q]);
        }
<<<<<<< HEAD
        return $this->render('doctor/profile/edit.html.twig', array_merge($this->getDoctorTemplateVars($medecin), [
            'user' => $user,
            'form' => $form,
        ]));
=======
        if (str_contains($lower, 'note') || str_contains($lower, 'notes')) {
            return $this->redirectToRoute('doctor_notes', ['q' => $q]);
        }
        if (str_contains($lower, 'rendez') || str_contains($lower, 'rdv') || str_contains($lower, 'agenda') || str_contains($lower, 'consultation')) {
            return $this->redirectToRoute('doctor_rendezvous', ['q' => $q]);
        }
        if (str_contains($lower, 'notification') || str_contains($lower, 'alerte') || str_contains($lower, 'demande')) {
            return $this->redirectToRoute('doctor_notifications');
        }
        if (str_contains($lower, 'tableau') || str_contains($lower, 'accueil') || str_contains($lower, 'dashboard')) {
            return $this->redirectToRoute('doctor_dashboard', ['q' => $q]);
        }

        // Par défaut : tableau de bord avec la recherche (ou disponibilités si ça ressemble à une date)
        return $this->redirectToRoute('doctor_dashboard', ['q' => $q]);
    }

    private function looksLikeDate(string $q): bool
    {
        return (bool) preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', trim($q))
            || (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($q));
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
    }

    #[Route('/medecin/disponibilites', name: 'doctor_availability', methods: ['GET', 'POST'])]
    public function availability(Request $request): Response
    {
        $medecin = $this->getMedecin();
        $search = (string) $request->query->get('q', '');
        $search = \trim($search);
        $order = (string) $request->query->get('order', 'asc');
        $order = \in_array(strtolower($order), ['asc', 'desc'], true) ? strtolower($order) : 'asc';

        // Ne pas filtrer si la recherche est un simple mot-clé de section (ex. "disponibilite" pour ouvrir la page)
        $searchForFilter = $this->isSectionKeyword($search) ? '' : $search;

        $disponibilites = $this->disponibiliteRepository->findForListing($medecin);
        if ($searchForFilter !== '') {
            $lower = mb_strtolower($searchForFilter);
            $disponibilites = \array_values(\array_filter($disponibilites, static function (Disponibilite $d) use ($lower): bool {
                $jourLabel = $d->getJourLabel() ?? '';
                $dateStr = $d->getDate()?->format('d/m/Y') ?? '';
                $heureDebut = $d->getHeureDebut()?->format('H:i') ?? '';
                $heureFin = $d->getHeureFin()?->format('H:i') ?? '';
                $duree = (string) $d->getDuree();

                return \str_contains(mb_strtolower($jourLabel), $lower)
                    || \str_contains(mb_strtolower($dateStr), $lower)
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
<<<<<<< HEAD
            $this->entityManager->persist($disponibilite);
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité enregistrée.');
            return $this->redirectToRoute('doctor_availability', ['q' => $search, 'order' => $order], Response::HTTP_SEE_OTHER);
=======
            $date = $disponibilite->getDate();
            $heureDebut = $disponibilite->getHeureDebut();
            $heureFin = $disponibilite->getHeureFin();
            if ($date !== null && $heureDebut !== null && $heureFin !== null && $this->disponibiliteRepository->existsSameSlot($medecin, $date, $heureDebut, $heureFin, null)) {
                $this->addFlash('error', 'Cette disponibilité existe déjà à cette date et à ces horaires.');
            } else {
                $this->entityManager->persist($disponibilite);
                $this->entityManager->flush();
                $this->addFlash('success', 'Disponibilité enregistrée.');
                $params = ['q' => $search];
                if ($date !== null) {
                    $params['month'] = $date->format('Y-m');
                }
                return $this->redirectToRoute('doctor_availability', $params);
            }
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        }

        // Calendrier mensuel : mois demandé ou courant
        $monthParam = $request->query->get('month', '');
        $now = new \DateTimeImmutable('today');
        if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $calendarFirst = \DateTimeImmutable::createFromFormat('Y-m-d', $monthParam . '-01');
            $calendarFirst = $calendarFirst ?: $now;
        } else {
            $calendarFirst = $now->modify('first day of this month');
        }
        $calendarLast = $calendarFirst->modify('last day of this month');
        $calendarWeeks = $this->buildCalendarWeeks($calendarFirst, $calendarLast, $disponibilites);
        $prevMonth = $calendarFirst->modify('-1 month')->format('Y-m');
        $nextMonth = $calendarFirst->modify('+1 month')->format('Y-m');
        $calendarLabel = $this->formatMonthYear($calendarFirst);

        return $this->render('doctor/availability/index.html.twig', [
            'disponibilites' => $disponibilites,
            'medecin' => $medecin,
            'form' => $form,
            'search' => $search,
<<<<<<< HEAD
            'order' => $order,
        ]));
=======
            'calendar_first' => $calendarFirst,
            'calendar_label' => $calendarLabel,
            'calendar_weeks' => $calendarWeeks,
            'prev_month' => $prevMonth,
            'next_month' => $nextMonth,
        ]);
    }

    /** Construit les semaines du calendrier (lundi = premier jour) avec les dispos par jour. */
    private function buildCalendarWeeks(\DateTimeImmutable $firstDayOfMonth, \DateTimeImmutable $lastDayOfMonth, array $disponibilites): array
    {
        $n = (int) $firstDayOfMonth->format('N');
        $start = $firstDayOfMonth->modify('-' . ($n - 1) . ' days');
        $nLast = (int) $lastDayOfMonth->format('N');
        $daysToAdd = $nLast === 7 ? 0 : 7 - $nLast;
        $end = $lastDayOfMonth->modify('+' . $daysToAdd . ' days');

        $disposByDate = [];
        foreach ($disponibilites as $d) {
            $date = $d->getDate();
            if ($date === null) {
                continue;
            }
            $key = $date->format('Y-m-d');
            if (!isset($disposByDate[$key])) {
                $disposByDate[$key] = [];
            }
            $disposByDate[$key][] = $d;
        }

        $weeks = [];
        $current = $start;
        while ($current <= $end) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $current->format('Y-m-d');
                $isCurrentMonth = $current >= $firstDayOfMonth && $current <= $lastDayOfMonth;
                $week[] = [
                    'date' => $current,
                    'is_current_month' => $isCurrentMonth,
                    'dispos' => $disposByDate[$key] ?? [],
                ];
                $current = $current->modify('+1 day');
            }
            $weeks[] = $week;
        }
        return $weeks;
    }

    private function formatMonthYear(\DateTimeImmutable $d): string
    {
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        return $months[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }

    /** Indique si la requête est un mot-clé de section (pour ouvrir une page) et non un vrai critère de filtre. */
    private function isSectionKeyword(string $q): bool
    {
        $lower = mb_strtolower(trim($q));
        if ($lower === '') {
            return true;
        }
        $keywords = ['disponibil', 'créneau', 'creneau', 'horaire', 'note', 'notes', 'rendez', 'rdv', 'agenda', 'consultation', 'notification', 'tableau', 'accueil', 'dashboard'];
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
    }

    #[Route('/medecin/disponibilites/nouvelle', name: 'doctor_availability_new', methods: ['GET', 'POST'])]
    public function newAvailability(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
<<<<<<< HEAD
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
=======
            return $this->redirectToRoute('login');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        }

        $disponibilite = new Disponibilite();
        $disponibilite->setMedecin($medecin);
        $form = $this->createForm(DoctorDisponibiliteType::class, $disponibilite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
<<<<<<< HEAD
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
=======
            $disponibilite->setMedecin($medecin);
            $date = $disponibilite->getDate();
            $heureDebut = $disponibilite->getHeureDebut();
            $heureFin = $disponibilite->getHeureFin();
            if ($date !== null && $heureDebut !== null && $heureFin !== null && $this->disponibiliteRepository->existsSameSlot($medecin, $date, $heureDebut, $heureFin, null)) {
                $this->addFlash('error', 'Cette disponibilité existe déjà à cette date et à ces horaires.');
                return $this->render('doctor/availability/new.html.twig', ['form' => $form]);
            }
            $this->entityManager->persist($disponibilite);
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité enregistrée.');
            return $this->redirectToRoute('doctor_availability');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        }

        return $this->render('doctor/availability/new.html.twig', ['form' => $form]);
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
            $date = $disponibilite->getDate();
            $heureDebut = $disponibilite->getHeureDebut();
            $heureFin = $disponibilite->getHeureFin();
            if ($date !== null && $heureDebut !== null && $heureFin !== null && $this->disponibiliteRepository->existsSameSlot($disponibilite->getMedecin(), $date, $heureDebut, $heureFin, $disponibilite->getId())) {
                $this->addFlash('error', 'Cette disponibilité existe déjà à cette date et à ces horaires.');
                return $this->render('doctor/availability/edit.html.twig', ['form' => $form, 'disponibilite' => $disponibilite]);
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Disponibilité mise à jour.');
            return $this->redirectToRoute('doctor_availability');
        }

        return $this->render('doctor/availability/edit.html.twig', [
            'form' => $form,
            'disponibilite' => $disponibilite,
        ]);
    }

    #[Route('/medecin/disponibilites/{id}/supprimer', name: 'doctor_availability_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAvailability(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
<<<<<<< HEAD
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
=======
        $disponibilite = $this->disponibiliteRepository->find($id);
        $canDelete = $disponibilite !== null && (
            ($medecin === null && $disponibilite->getMedecin() === null)
            || ($medecin !== null && $disponibilite->getMedecin() === $medecin)
        );
        if (!$canDelete) {
            $this->addFlash('error', 'Créneau introuvable.');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
            return $this->redirectToRoute('doctor_availability');
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_availability_delete_' . $id, $token)) {
<<<<<<< HEAD
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
=======
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_availability');
        }

        $this->entityManager->remove($disponibilite);
        $this->entityManager->flush();
        $this->addFlash('success', 'Créneau supprimé.');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858

        return $this->redirectToRoute('doctor_availability');
    }

    #[Route('/medecin/notifications', name: 'doctor_notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof Medcin) {
            return $this->redirectToRoute('app_login');
        }
        $notifications = $this->notificationRepository->findByDestinataireOrderByCreatedDesc($user);
        // Marquer toutes les notifications comme lues à l'accès à la page → le badge rouge disparaît
        foreach ($notifications as $n) {
            if (!$n->isLu()) {
                $n->setLu(true);
            }
        }
        $this->entityManager->flush();

        $demandesRdv = $this->rendezVousRepository->findEnAttenteByMedecin($user);
        $notifAnnuleReportePatient = $this->notificationRepository->findAnnuleReportePatientByDestinataireOrderByCreatedDesc($user);
        $unread = 0;
        $today = (int) $this->notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.createdAt >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
        $weekStart = new \DateTimeImmutable('monday this week');
        $thisWeek = (int) $this->notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.destinataire = :user')
            ->andWhere('n.createdAt >= :week')
            ->setParameter('user', $user)
            ->setParameter('week', $weekStart)
            ->getQuery()
            ->getSingleScalarResult();
        return $this->render('doctor/notifications/index.html.twig', [
            'notifications' => $notifications,
            'notif_annule_reporte_patient' => $notifAnnuleReportePatient,
            'demandes_rdv' => $demandesRdv,
            'stats' => [
                'total_notifications' => \count($notifications),
                'unread_notifications' => $unread,
                'total_demandes' => \count($demandesRdv),
                'today_notifications' => $today,
                'this_week_notifications' => $thisWeek,
            ],
        ]);
    }

    #[Route('/medecin/notes', name: 'doctor_notes', methods: ['GET', 'POST'])]
    public function notes(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
<<<<<<< HEAD
            $this->addFlash('error', 'Accès non autorisé.');
=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
            return $this->redirectToRoute('app_login');
        }
        $search = trim((string) $request->query->get('q', ''));
        $searchForFilter = $this->isSectionKeyword($search) ? '' : $search;
        $notes = $searchForFilter !== ''
            ? $this->noteRepository->searchByMedecin($medecin, $searchForFilter)
            : $this->noteRepository->findByMedecinOrderByDate($medecin);
        $patients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
<<<<<<< HEAD
        $notes = $this->noteRepository->findByMedecinOrderByDate($medecin);
        $stats = $this->calculateNotesStats($notes, $medecin);

=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        $note = new Note();
        $note->setMedecin($medecin);
        $form = $this->createForm(NoteType::class, $note, ['patients' => $patients]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
<<<<<<< HEAD
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
=======
            $note->setMedecin($medecin);
            $this->entityManager->persist($note);
            $this->entityManager->flush();
            $this->addFlash('success', 'Note enregistrée.');
            return $this->redirectToRoute('doctor_notes');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        }
        return $this->render('doctor/notes/index.html.twig', [
            'notes' => $notes,
            'form' => $form,
            'patients' => $patients,
<<<<<<< HEAD
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
=======
            'search' => $search,
        ]);
    }

    #[Route('/medecin/notes/upload', name: 'doctor_notes_upload', methods: ['POST'])]
    public function notesUpload(Request $request): JsonResponse
    {
        if ($this->getMedecin() === null) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('doctor_notes_upload', $token)) {
            return new JsonResponse(['error' => 'Jeton de sécurité invalide'], 403);
        }
        $file = $request->files->get('file');
        if (!$file || !$file->isValid()) {
            return new JsonResponse(['error' => 'Fichier invalide'], 400);
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!\in_array($file->getMimeType(), $allowed, true)) {
            return new JsonResponse(['error' => 'Type de fichier non autorisé'], 400);
        }
        $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/notes';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safeName = uniqid('', true) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
        try {
            $file->move($dir, $safeName);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Erreur enregistrement'], 500);
        }
        $url = $request->getBasePath() . '/uploads/notes/' . $safeName;
        return new JsonResponse(['url' => $url, 'name' => $file->getClientOriginalName()]);
    }

    #[Route('/medecin/rendez-vous', name: 'doctor_rendezvous', methods: ['GET'])]
    public function rendezvous(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('app_login');
        }
        $order = (string) $request->query->get('order', 'asc');
        $search = trim((string) $request->query->get('q', ''));
        $searchForFilter = $this->isSectionKeyword($search) ? '' : $search;

        if ($searchForFilter !== '') {
            $all = $this->rendezVousRepository->searchByMedecin($medecin, $searchForFilter);
            $rendezVousConfirmes = array_values(array_filter($all, fn (RendezVous $r) => $r->getStatus() === StatusRendezVous::CONFIRMER));
            $rendezVousEnAttente = array_values(array_filter($all, fn (RendezVous $r) => $r->getStatus() === StatusRendezVous::EN_ATTENTE));
            $rendezVousAnnules = array_values(array_filter($all, fn (RendezVous $r) => $r->getStatus() === StatusRendezVous::ANNULER));
        } else {
            $rendezVousConfirmes = $this->rendezVousRepository->findByMedecinAndStatusOrderByDate($medecin, StatusRendezVous::CONFIRMER, $order);
            $rendezVousEnAttente = $this->rendezVousRepository->findByMedecinAndStatusOrderByDate($medecin, StatusRendezVous::EN_ATTENTE, $order);
            $rendezVousAnnules = $this->rendezVousRepository->findByMedecinAndStatusOrderByDate($medecin, StatusRendezVous::ANNULER, $order);
        }

        $total = $this->rendezVousRepository->countByMedecin($medecin);
        $todayCount = $this->rendezVousRepository->countTodayByMedecin($medecin);

        return $this->render('doctor/rendezvous/index.html.twig', [
            'rendez_vous_confirmes' => $rendezVousConfirmes,
            'rendez_vous_en_attente' => $rendezVousEnAttente,
            'rendez_vous_annules' => $rendezVousAnnules,
            'order' => $order,
            'today_count' => $todayCount,
            'search' => $search,
            'sms_enabled' => $this->smsStatusChecker->isSmsEnabled(),
            'stats' => [
                'total' => $total,
                'confirmes' => \count($rendezVousConfirmes),
                'en_attente' => \count($rendezVousEnAttente),
                'annules' => \count($rendezVousAnnules),
            ],
        ]);
    }

    #[Route('/medecin/rendez-vous/calendrier', name: 'doctor_rendezvous_calendrier', methods: ['GET'])]
    public function rendezvousCalendrier(): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('doctor/rendezvous/calendrier.html.twig');
    }

    #[Route('/medecin/rendez-vous/calendar/events', name: 'doctor_rendezvous_calendar_events', methods: ['GET'])]
    public function calendarEvents(Request $request): JsonResponse
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return new JsonResponse([], 401);
        }
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        if (!\is_string($start) || !\is_string($end)) {
            return new JsonResponse([]);
        }
        try {
            $startDt = new \DateTimeImmutable($start);
            $endDt = new \DateTimeImmutable($end);
        } catch (\Throwable) {
            return new JsonResponse([]);
        }
        $startDate = new \DateTimeImmutable($startDt->format('Y-m-d') . ' 00:00:00');
        $endDate = new \DateTimeImmutable($endDt->format('Y-m-d') . ' 23:59:59');
        $rdvs = $this->rendezVousRepository->findByMedecinBetweenDates($medecin, $startDate, $endDate);
        $events = [];
        foreach ($rdvs as $rdv) {
            $dateRdv = $rdv->getDateRdv();
            $dispo = $rdv->getDisponibilite();
            if ($dateRdv === null) {
                continue;
            }
            $heureDebut = $dispo?->getHeureDebut();
            $heureFin = $dispo?->getHeureFin();
            $startIso = $dateRdv->format('Y-m-d') . 'T' . ($heureDebut ? $heureDebut->format('H:i:s') : '09:00:00');
            $endIso = $dateRdv->format('Y-m-d') . 'T' . ($heureFin ? $heureFin->format('H:i:s') : '10:00:00');
            $title = trim(($rdv->getPrenom() ?? '') . ' ' . ($rdv->getNom() ?? '')) ?: 'Patient';
            $status = $rdv->getStatus();
            $color = $status === StatusRendezVous::CONFIRMER ? '#10b981' : ($status === StatusRendezVous::EN_ATTENTE ? '#f59e0b' : '#6b7280');
            $events[] = [
                'id' => (string) $rdv->getId(),
                'title' => $title,
                'start' => $startIso,
                'end' => $endIso,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'status' => $status?->value,
                    'editUrl' => $this->generateUrl('doctor_rendezvous_edit', ['id' => $rdv->getId()]),
                ],
            ];
        }
        return new JsonResponse($events);
    }

    #[Route('/medecin/rendez-vous/{id}/detail', name: 'doctor_rendezvous_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function rendezvousDetail(int $id): JsonResponse
    {
        $medecin = $this->getMedecin();
        $rdv = $this->rendezVousRepository->find($id);
        if ($medecin === null || $rdv === null || $rdv->getMedecin() !== $medecin) {
            return new JsonResponse(['error' => 'Rendez-vous introuvable.'], 404);
        }
        $dispo = $rdv->getDisponibilite();
        $dateRdv = $rdv->getDateRdv();
        $heureDebut = $dispo?->getHeureDebut();
        $heureFin = $dispo?->getHeureFin();
        $status = $rdv->getStatus();
        $motif = $rdv->getMotif();
        $statusLabel = match ($status) {
            StatusRendezVous::CONFIRMER => 'Confirmé',
            StatusRendezVous::EN_ATTENTE => 'En attente',
            StatusRendezVous::ANNULER => 'Annulé',
            default => $status?->value ?? '',
        };
        $motifLabel = match ($motif) {
            Motif::URGENCE => 'Urgence',
            Motif::SUIVIE => 'Suivi',
            Motif::NORMAL => 'Normal',
            default => $motif?->value ?? '',
        };
        $data = [
            'id' => $rdv->getId(),
            'nom' => $rdv->getNom(),
            'prenom' => $rdv->getPrenom(),
            'email' => $rdv->getEmail(),
            'telephone' => $rdv->getTelephone(),
            'adresse' => $rdv->getAdresse(),
            'dateNaissance' => $rdv->getDateNaissance()?->format('d/m/Y'),
            'dateRdv' => $dateRdv?->format('d/m/Y'),
            'heureDebut' => $heureDebut?->format('H:i'),
            'heureFin' => $heureFin?->format('H:i'),
            'motif' => $motifLabel,
            'status' => $statusLabel,
            'notePatient' => $rdv->getNotePatient(),
            'editUrl' => $this->generateUrl('doctor_rendezvous_edit', ['id' => $rdv->getId()]),
        ];
        return new JsonResponse($data);
    }

    #[Route('/medecin/rendez-vous/{id}/move', name: 'doctor_rendezvous_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousMove(Request $request, int $id): JsonResponse
    {
        $medecin = $this->getMedecin();
        $rdv = $this->rendezVousRepository->find($id);
        if ($medecin === null || $rdv === null || $rdv->getMedecin() !== $medecin) {
            return new JsonResponse(['error' => 'Rendez-vous introuvable.'], 404);
        }
        $data = json_decode($request->getContent(), true);
        $start = isset($data['start']) && \is_string($data['start']) ? $data['start'] : null;
        if ($start === null) {
            return new JsonResponse(['error' => 'Paramètre start requis.'], 400);
        }
        try {
            $startDt = new \DateTimeImmutable($start);
        } catch (\Throwable) {
            return new JsonResponse(['error' => 'Date invalide.'], 400);
        }
        $dateYmd = $startDt->format('Y-m-d');
        $timeHi = $startDt->format('H:i');
        $dispo = $this->disponibiliteRepository->findSlotAt($medecin, $dateYmd, $timeHi);
        if ($dispo === null) {
            return new JsonResponse(['error' => 'Aucun créneau disponible à cette date et heure.'], 400);
        }
        if ($this->rendezVousRepository->isSlotTaken($dispo)) {
            return new JsonResponse(['error' => 'Ce créneau est déjà pris.'], 409);
        }
        $dateRdv = new \DateTimeImmutable($dateYmd);
        $rdv->setDateRdv($dateRdv);
        $rdv->setDisponibilite($dispo);
        $this->entityManager->flush();
        $heureFin = $dispo->getHeureFin();
        $endIso = $dateYmd . 'T' . ($heureFin ? $heureFin->format('H:i:s') : '10:00:00');
        return new JsonResponse(['id' => $rdv->getId(), 'start' => $startDt->format('c'), 'end' => $endIso]);
    }

    #[Route('/medecin/rendez-vous/nouveau', name: 'doctor_rendezvous_new', methods: ['GET', 'POST'])]
    public function rendezvousNew(Request $request): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('app_login');
        }
        $date = $request->query->get('date');
        $time = $request->query->get('time');
        if (!\is_string($date) || !\is_string($time)) {
            $this->addFlash('error', 'Indiquez la date et l\'heure du créneau (depuis le calendrier).');
            return $this->redirectToRoute('doctor_rendezvous_calendrier');
        }
        $dispo = $this->disponibiliteRepository->findSlotAt($medecin, $date, $time);
        if ($dispo === null) {
            $this->addFlash('error', 'Aucun créneau disponible à cette date et heure. Créez d\'abord une disponibilité.');
            return $this->redirectToRoute('doctor_rendezvous_calendrier');
        }
        if ($this->rendezVousRepository->isSlotTaken($dispo)) {
            $this->addFlash('error', 'Ce créneau est déjà pris.');
            return $this->redirectToRoute('doctor_rendezvous_calendrier');
        }
        $rdv = new RendezVous();
        $rdv->setMedecin($medecin);
        $rdv->setDisponibilite($dispo);
        $rdv->setDateRdv(new \DateTimeImmutable($dispo->getDate()?->format('Y-m-d') ?? $date));
        $rdv->setStatus(StatusRendezVous::EN_ATTENTE);
        $rdv->setMotif(Motif::NORMAL);
        $rdv->setNotePatient('vide');
        $patients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
        $form = $this->createForm(DoctorRendezVousCreateType::class, $rdv, ['patients' => $patients]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $patient = $rdv->getPatient();
            if ($patient !== null) {
                $rdv->setNom($patient->getNom() ?? '');
                $rdv->setPrenom($patient->getPrenom() ?? '');
                $rdv->setEmail($patient->getEmail());
                $rdv->setTelephone($patient->getTelephone());
            }
            if ($rdv->getNotePatient() === null || $rdv->getNotePatient() === '') {
                $rdv->setNotePatient('vide');
            }
            $this->entityManager->persist($rdv);
            $this->entityManager->flush();
            $this->addFlash('success', 'Rendez-vous créé.');
            return $this->redirectToRoute('doctor_rendezvous_calendrier');
        }
        $dateLabel = $dispo->getDate()?->format('d/m/Y') . ' ' . ($dispo->getHeureDebut()?->format('H:i') ?? '') . '-' . ($dispo->getHeureFin()?->format('H:i') ?? '');
        return $this->render('doctor/rendezvous/new.html.twig', [
            'form' => $form,
            'date_label' => $dateLabel,
        ]);
    }

    #[Route('/medecin/rendez-vous/{id}/envoyer-rappel-sms', name: 'doctor_rendezvous_envoyer_rappel_sms', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousEnvoyerRappelSms(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        $rdv = $this->rendezVousRepository->find($id);
        if ($medecin === null || $rdv === null || $rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_rendezvous');
        }
        if (!$this->isCsrfTokenValid('doctor_rdv_rappel_sms_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_rendezvous');
        }
        if ($rdv->getStatus() !== StatusRendezVous::CONFIRMER) {
            $this->addFlash('error', 'Seuls les rendez-vous confirmés peuvent recevoir un SMS de rappel.');
            return $this->redirectToRoute('doctor_rendezvous');
        }
        $dateRdv = $rdv->getDateRdv();
        $dispo = $rdv->getDisponibilite();
        $heureFin = $dispo?->getHeureFin();
        if ($dateRdv !== null && $heureFin !== null) {
            $finRdv = (new \DateTimeImmutable($dateRdv->format('Y-m-d')))->setTime(
                (int) $heureFin->format('H'),
                (int) $heureFin->format('i'),
                (int) $heureFin->format('s')
            );
            if ($finRdv < new \DateTimeImmutable('now')) {
                $this->addFlash('error', 'Impossible d\'envoyer un rappel pour un rendez-vous déjà passé.');
                return $this->redirectToRoute('doctor_rendezvous');
            }
        }
        if ($this->rappelSmsService->envoyerRappel($rdv)) {
            $this->addFlash('success', 'SMS de rappel (24h) envoyé au patient.');
        } else {
            $this->addFlash('error', $this->rappelSmsService->getLastError() ?? 'Impossible d\'envoyer le SMS de rappel.');
        }
        return $this->redirectToRoute('doctor_rendezvous');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
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
<<<<<<< HEAD
        if ($medecin === null) {
            $this->addFlash('error', 'Accès non autorisé.');
            return $this->redirectToRoute('app_login');
        }

        if ($id <= 0) {
            $this->addFlash('error', 'Identifiant de rendez-vous invalide.');
            return $this->redirectToRoute('doctor_notifications');
        }

=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        $rdv = $this->rendezVousRepository->find($id);
        if ($medecin === null || $rdv === null || $rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_notifications');
        }
<<<<<<< HEAD

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
=======
        if (!$this->isCsrfTokenValid('doctor_rdv_accept_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_notifications');
        }
        $rdv->setStatus(StatusRendezVous::CONFIRMER);
        $this->entityManager->flush();
        $patient = $rdv->getPatient();
        if ($patient !== null) {
            $notif = new Notification();
            $notif->setDestinataire($patient);
            $notif->setType(Notification::TYPE_RDV_ACCEPTE);
            $notif->setRendezVous($rdv);
            $this->entityManager->persist($notif);
            $this->entityManager->flush();
        }
        $emailSent = $this->rendezVousConfirmationMailer->sendConfirme($rdv);
        $this->googleCalendarService->createEventFromRendezVous($rdv);
        if ($emailSent) {
            $this->addFlash('success', 'Rendez-vous accepté. Un email de confirmation a été envoyé au patient.');
        } else {
            $this->addFlash('warning', 'Rendez-vous accepté. L\'email de confirmation n\'a pas pu être envoyé au patient (adresse email absente ou erreur d\'envoi).');
        }
        return $this->redirectToRoute('doctor_notifications');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
    }

    #[Route('/medecin/rendez-vous/{id}/refuser', name: 'doctor_rendezvous_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousRefuse(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
<<<<<<< HEAD
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
=======
        $rdv = $this->rendezVousRepository->find($id);
        if ($medecin === null || $rdv === null || $rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_notifications');
        }
        if (!$this->isCsrfTokenValid('doctor_rdv_refuse_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_notifications');
        }
        $rdv->setStatus(StatusRendezVous::ANNULER);
        $this->entityManager->flush();
        $patient = $rdv->getPatient();
        if ($patient !== null) {
            $notif = new Notification();
            $notif->setDestinataire($patient);
            $notif->setType(Notification::TYPE_RDV_REFUSE);
            $notif->setRendezVous($rdv);
            $this->entityManager->persist($notif);
            $this->entityManager->flush();
        }
        $this->addFlash('success', 'Demande refusée.');
        return $this->redirectToRoute('doctor_notifications');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
    }

    #[Route('/medecin/rendez-vous/{id}/modifier', name: 'doctor_rendezvous_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function rendezvousEdit(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        $rdv = $this->rendezVousRepository->find($id);
        if ($medecin === null || $rdv === null || $rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_rendezvous');
        }
        $form = $this->createForm(DoctorRendezVousEditType::class, $rdv);
        $oldStatus = $rdv->getStatus();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $newStatus = $rdv->getStatus();
            $this->entityManager->flush();
            $patient = $rdv->getPatient();
            if ($patient !== null && $oldStatus !== $newStatus) {
                if ($newStatus === StatusRendezVous::CONFIRMER) {
                    $notif = new Notification();
                    $notif->setDestinataire($patient);
                    $notif->setType(Notification::TYPE_RDV_ACCEPTE);
                    $notif->setRendezVous($rdv);
                    $this->entityManager->persist($notif);
                    $this->entityManager->flush();
                } elseif ($newStatus === StatusRendezVous::ANNULER) {
                    $notif = new Notification();
                    $notif->setDestinataire($patient);
                    $notif->setType(Notification::TYPE_RDV_REFUSE);
                    $notif->setRendezVous($rdv);
                    $this->entityManager->persist($notif);
                    $this->entityManager->flush();
                }
            }
            $this->addFlash('success', 'Rendez-vous mis à jour.');
            return $this->redirectToRoute('doctor_rendezvous');
        }
        return $this->render('doctor/rendezvous/edit.html.twig', [
            'form' => $form,
            'rdv' => $rdv,
        ]);
    }

    #[Route('/medecin/rendez-vous/{id}/supprimer', name: 'doctor_rendezvous_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rendezvousDelete(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        $rdv = $this->rendezVousRepository->find($id);
        if ($medecin === null || $rdv === null || $rdv->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Rendez-vous introuvable.');
            return $this->redirectToRoute('doctor_rendezvous');
        }
        if (!$this->isCsrfTokenValid('doctor_rendezvous_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_rendezvous');
        }
        $this->entityManager->remove($rdv);
        $this->entityManager->flush();
        $this->addFlash('success', 'Rendez-vous supprimé.');
        return $this->redirectToRoute('doctor_rendezvous');
    }

    #[Route('/medecin/notes/{id}', name: 'doctor_note_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function noteShow(int $id): Response
    {
        $medecin = $this->getMedecin();
        $note = $this->noteRepository->find($id);
        if ($medecin === null || $note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        return $this->render('doctor/notes/show.html.twig', ['note' => $note]);
    }

    #[Route('/medecin/notes/{id}/modifier', name: 'doctor_note_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function noteEdit(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        $note = $this->noteRepository->find($id);
        if ($medecin === null || $note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        $patients = $this->rendezVousRepository->findDistinctPatientsByMedecin($medecin);
        $form = $this->createForm(NoteType::class, $note, ['patients' => $patients]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Note mise à jour.');
            return $this->redirectToRoute('doctor_notes');
        }
        return $this->render('doctor/notes/edit.html.twig', [
            'form' => $form,
            'note' => $note,
        ]);
    }

    #[Route('/medecin/notes/{id}/supprimer', name: 'doctor_note_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function noteDelete(Request $request, int $id): Response
    {
        $medecin = $this->getMedecin();
        $note = $this->noteRepository->find($id);
        if ($medecin === null || $note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        if (!$this->isCsrfTokenValid('doctor_note_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('doctor_notes');
        }
        $this->entityManager->remove($note);
        $this->entityManager->flush();
        $this->addFlash('success', 'Note supprimée.');
        return $this->redirectToRoute('doctor_notes');
    }

    #[Route('/medecin/notes/{id}/export-pdf', name: 'doctor_note_export_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function noteExportPdf(int $id): Response
    {
        $medecin = $this->getMedecin();
        $note = $this->noteRepository->find($id);
        if ($medecin === null || $note === null || $note->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Note introuvable.');
            return $this->redirectToRoute('doctor_notes');
        }
        return $this->render('doctor/notes/pdf_single.html.twig', ['note' => $note]);
    }

    #[Route('/medecin/notes/export-pdf', name: 'doctor_notes_export_pdf', methods: ['GET'])]
    public function notesExportPdf(): Response
    {
        $medecin = $this->getMedecin();
        if ($medecin === null) {
            return $this->redirectToRoute('app_login');
        }
        $notes = $this->noteRepository->findByMedecinOrderByDate($medecin);
        return $this->render('doctor/notes/pdf_list.html.twig', [
            'notes' => $notes,
            'medecin' => $medecin,
        ]);
    }

    private function getMedecin(): ?Medcin
    {
        $user = $this->getUser();
<<<<<<< HEAD

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
=======
        return $user instanceof Medcin ? $user : null;
    }
}
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
