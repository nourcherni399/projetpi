<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Disponibilite;
use App\Entity\Evenement;
use App\Entity\InscritEvents;
use App\Entity\Medcin;
use App\Entity\Notification;
use App\Entity\Patient;
use App\Entity\MessageEvenement;
use App\Entity\RendezVous;
use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use App\Enum\UserRole;
use App\Form\MessageEvenementType;
use App\Repository\DisponibiliteRepository;
use App\Repository\EvenementRepository;
use App\Repository\InscritEventsRepository;
use App\Repository\MedcinRepository;
use App\Repository\MessageEvenementRepository;
use App\Repository\NotificationRepository;
use App\Repository\RendezVousRepository;
use App\Repository\ProduitRepository;
use App\Repository\ThematiqueRepository;
use App\Service\MeteoService;
use App\Service\WelcomeMessageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly ThematiqueRepository $thematiqueRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly MessageEvenementRepository $messageEvenementRepository,
        private readonly MedcinRepository $medecinRepository,
        private readonly DisponibiliteRepository $disponibiliteRepository,
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response|RedirectResponse
    {
        $user = $this->getUser();
        if ($user !== null && method_exists($user, 'getRole')) {
            $role = $user->getRole();
            if ($role === UserRole::ADMIN) {
                return $this->redirectToRoute('admin_dashboard');
            }
            if ($role === UserRole::MEDECIN) {
                return $this->redirectToRoute('doctor_dashboard');
            }
        }
        return $this->render('front/home/index.html.twig');
    }

    #[Route('/a-propos', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('front/about/index.html.twig');
    }

    #[Route('/notifications', name: 'user_notifications', methods: ['GET'])]
    public function notifications(): Response|RedirectResponse
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login', ['_target_path' => $this->generateUrl('user_notifications')]);
        }
        return $this->render('front/notifications/index.html.twig');
    }

    #[Route('/produits/{id}', name: 'user_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function productShow(int $id): Response
    {
        $produit = $this->produitRepository->find($id);
        if ($produit === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }
        return $this->render('front/products/show.html.twig', ['produit' => $produit]);
    }

    /** @return array<int, array{name: string, category: string, category_class: string, rating: string, reviews: int, description: string, price: int, description_long: string, characteristics: list<string>, benefits: list<string>}> */
    private function getProductsData(): array
    {
        return [
            1 => [
                'name' => 'Coussin Sensoriel Lesté Premium',
                'category' => 'Apaisement',
                'category_class' => 'bg-[#5eead4]/90',
                'rating' => '4.8',
                'reviews' => 24,
                'description' => 'Coussin lesté offrant une pression profonde apaisante, idéal pour réduire l\'anxiété et favoriser le calme.',
                'price' => 45,
                'description_long' => 'Notre coussin sensoriel lesté est spécialement conçu pour offrir une pression profonde et apaisante.',
                'characteristics' => [
                    'Poids ajustable de 1 à 3 kg',
                    'Housse en tissu doux et hypoallergénique',
                    'Lavable en machine',
                    'Dimensions: 40x40 cm',
                    'Remplissage en microbilles de verre',
                ],
                'benefits' => [
                    'Réduction du stress et de l\'anxiété',
                    'Améliore la concentration',
                    'Favorise le sommeil',
                    'Apaisement sensoriel durable',
                ],
            ],
            2 => [
                'name' => 'Fidget Cube Premium',
                'category' => 'Concentration',
                'category_class' => 'bg-[#A7C7E7]',
                'rating' => '4.9',
                'reviews' => 56,
                'description' => 'Cube multi-sensoriel avec 6 faces interactives pour améliorer la concentration et réduire le stress.',
                'price' => 18,
                'description_long' => 'Le Fidget Cube Premium offre six types de stimulations discrètes pour canaliser l\'énergie et améliorer la concentration.',
                'characteristics' => [
                    '6 faces interactives différentes',
                    'Matière silencieuse',
                    'Format poche',
                    'Résistant et durable',
                ],
                'benefits' => [
                    'Aide à la concentration',
                    'Réduction du stress',
                    'Discret au quotidien',
                    'Adapté à tous les âges',
                ],
            ],
            3 => [
                'name' => 'Casque Anti-Bruit Enfant',
                'category' => 'Protection',
                'category_class' => 'bg-[#D8B4FE]',
                'rating' => '4.7',
                'reviews' => 42,
                'description' => 'Casque confortable réduisant les stimuli sonores pour un environnement plus calme et sécurisant.',
                'price' => 35,
                'description_long' => 'Conçu pour les enfants, ce casque réduit les bruits environnants tout en préservant la qualité des sons utiles.',
                'characteristics' => [
                    'Réduction du bruit jusqu\'à 22 dB',
                    'Bandeau réglable',
                    'Pliable et transportable',
                    'Confort oreille doux',
                ],
                'benefits' => [
                    'Environnement apaisant',
                    'Protection auditive',
                    'Idéal en classe ou en déplacement',
                    'Réduction de la surcharge sensorielle',
                ],
            ],
        ];
    }

    #[Route('/evenements', name: 'user_events', methods: ['GET'])]
    public function events(Request $request): Response
    {
        $thematiques = $this->thematiqueRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);

        $dateFrom = null;
        $dateTo = null;
        $lieuRaw = $request->query->get('lieu');
        $lieu = null;
        if (is_string($lieuRaw)) {
            $lieuTrimmed = trim($lieuRaw);
            if ($lieuTrimmed !== '') {
                $lieu = $lieuTrimmed;
            }
        }
        $thematiqueId = $request->query->getInt('thematique');
        $dateFromStr = $request->query->get('date_from');
        $dateToStr = $request->query->get('date_to');
        if ($dateFromStr !== null && $dateFromStr !== '') {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFromStr);
            if ($d) {
                $dateFrom = $d;
            }
        }
        if ($dateToStr !== null && $dateToStr !== '') {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $dateToStr);
            if ($d) {
                $dateTo = $d;
            }
        }

        $allFiltered = $this->evenementRepository->findFilteredForFront($dateFrom, $dateTo, $lieu, $thematiqueId > 0 ? $thematiqueId : null);

        $hasActiveFilters = $lieu !== null || $dateFrom !== null || $dateTo !== null || $thematiqueId > 0;

        $grouped = [];
        foreach ($thematiques as $t) {
            if ($thematiqueId > 0 && (int) $t->getId() !== $thematiqueId) {
                continue;
            }
            $evenements = array_filter($allFiltered, static fn (Evenement $e) => $e->getThematique() !== null && $e->getThematique()->getId() === $t->getId());
            $evenements = array_values($evenements);
            if (!$hasActiveFilters || \count($evenements) > 0) {
                $grouped[] = ['thematique' => $t, 'evenements' => $evenements];
            }
        }
        $sansThematique = array_filter($allFiltered, static fn (Evenement $e) => $e->getThematique() === null);
        $sansThematique = array_values($sansThematique);

        return $this->render('front/events/index.html.twig', [
            'grouped' => $grouped,
            'sansThematique' => $sansThematique,
            'thematiques' => $thematiques,
            'filter_date_from' => $dateFromStr ?? $request->query->get('date_from'),
            'filter_date_to' => $dateToStr ?? $request->query->get('date_to'),
            'filter_lieu' => $lieu ?? '',
            'filter_thematique' => $thematiqueId,
            'has_active_filters' => $hasActiveFilters,
            'total_filtered' => \count($allFiltered),
        ]);
    }

    #[Route('/evenements/carte', name: 'user_events_map', methods: ['GET'])]
    public function eventsMap(): Response
    {
        $dateFrom = new \DateTimeImmutable('today');
        $all = $this->evenementRepository->findFilteredForFront($dateFrom, null, null, null);
        $eventsForMap = [];
        foreach ($all as $evenement) {
            $coords = $evenement->getCoordinates();
            if ($coords !== null) {
                $eventsForMap[] = [
                    'id' => $evenement->getId(),
                    'title' => $evenement->getTitle(),
                    'lat' => $coords[0],
                    'lng' => $coords[1],
                    'lieu' => $evenement->getLieu(),
                    'date' => $evenement->getDateEvent() ? $evenement->getDateEvent()->format('d/m/Y') : '',
                    'url' => $this->generateUrl('user_event_show', ['id' => $evenement->getId()]),
                ];
            }
        }

        return $this->render('front/events/map.html.twig', [
            'eventsForMap' => $eventsForMap,
        ]);
    }

    #[Route('/evenements/{id}', name: 'user_event_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function eventShow(int $id, Request $request, MeteoService $meteoService, WelcomeMessageService $welcomeMessageService): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }
        $user = $this->getUser();
        $inscription = $user !== null
            ? $this->inscritEventsRepository->findInscriptionForUserAndEvent($user, $evenement)
            : null;
        $userInscrit = $inscription !== null && $inscription->getStatut() === 'accepte';

        $messages = [];
        $messageForm = null;
        if ($user !== null) {
            $messages = $this->messageEvenementRepository->findByEvenementAndUserOrderByDate($evenement, $user);
            $this->messageEvenementRepository->markAdminMessagesAsReadByEvenementAndUser($evenement, $user);
            $newMessage = new MessageEvenement();
            $newMessage->setEvenement($evenement);
            $newMessage->setUser($user);
            $messageForm = $this->createForm(MessageEvenementType::class, $newMessage);
        }

        $meteo = $meteoService->getWeatherForEvent($evenement);
        $welcomeMessage = null;
        $clientIp = $request->getClientIp();
        if ($clientIp !== null) {
            $country = $welcomeMessageService->getCountryFromIp($clientIp);
            if ($country !== null) {
                $welcomeMessage = $welcomeMessageService->getWelcomeMessage($country);
            }
        }
        if ($welcomeMessage === null) {
            $welcomeMessage = $welcomeMessageService->getWelcomeMessage('');
        }

        return $this->render('front/events/show.html.twig', [
            'evenement' => $evenement,
            'userInscrit' => $userInscrit,
            'inscription' => $inscription,
            'messages' => $messages,
            'messageForm' => $messageForm,
            'meteo' => $meteo,
            'welcome_message' => $welcomeMessage,
        ]);
    }

    #[Route('/evenements/{id}/inscrire', name: 'user_event_register', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eventRegister(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            $this->addFlash('error', 'Connectez-vous pour vous inscrire à un événement.');
            return $this->redirectToRoute('app_login', ['_target_path' => $this->generateUrl('user_event_show', ['id' => $id])]);
        }

        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        if (!$this->isCsrfTokenValid('event_register_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('user_event_show', ['id' => $id]);
        }

        $existing = $this->inscritEventsRepository->findInscriptionForUserAndEvent($user, $evenement);
        if ($existing !== null) {
            if ($existing->getStatut() === 'accepte') {
                $this->addFlash('info', 'Vous êtes déjà inscrit à cet événement.');
                return $this->redirectToRoute('user_event_show', ['id' => $id]);
            }
            if ($existing->getStatut() === 'en_attente') {
                $this->addFlash('info', 'Votre demande d\'inscription est déjà en attente de validation.');
                return $this->redirectToRoute('user_event_show', ['id' => $id]);
            }
            $existing->setStatut('en_attente');
            $existing->setDateInscrit(new \DateTime());
            $existing->setEstInscrit(true);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre demande a été renvoyée. L\'administrateur la validera sous peu.');
            return $this->redirectToRoute('user_event_show', ['id' => $id]);
        }

        $inscrit = new InscritEvents();
        $inscrit->setUser($user);
        $inscrit->setEvenement($evenement);
        $inscrit->setDateInscrit(new \DateTime());
        $inscrit->setEstInscrit(true);
        $inscrit->setStatut('en_attente');
        $this->entityManager->persist($inscrit);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre demande d\'inscription a été enregistrée. L\'administrateur la validera sous peu.');
        return $this->redirectToRoute('user_event_show', ['id' => $id]);
    }

    #[Route('/evenements/{id}/desinscrire', name: 'user_event_unregister', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eventUnregister(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            $this->addFlash('error', 'Connectez-vous pour gérer vos inscriptions.');
            return $this->redirectToRoute('app_login');
        }

        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        if (!$this->isCsrfTokenValid('event_unregister_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('user_event_show', ['id' => $id]);
        }

        $inscrit = $this->inscritEventsRepository->findInscriptionForUserAndEvent($user, $evenement);
        if ($inscrit !== null && $inscrit->getStatut() === 'accepte') {
            $inscrit->setEstInscrit(false);
            $inscrit->setStatut('desinscrit');
            $this->entityManager->flush();
            $this->addFlash('success', 'Vous avez été désinscrit de l\'événement.');
        } else {
            $this->addFlash('info', 'Vous n\'étiez pas inscrit à cet événement.');
        }

        return $this->redirectToRoute('user_event_show', ['id' => $id]);
    }

    #[Route('/evenements/{id}/message', name: 'user_event_message', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eventMessage(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            $this->addFlash('error', 'Connectez-vous pour envoyer un message.');
            return $this->redirectToRoute('app_login', ['_target_path' => $this->generateUrl('user_event_show', ['id' => $id])]);
        }

        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        if (!$this->isCsrfTokenValid('event_message_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('user_event_show', ['id' => $id]);
        }

        $message = new MessageEvenement();
        $message->setEvenement($evenement);
        $message->setUser($user);
        $message->setEnvoyePar(MessageEvenement::ENVOYE_PAR_USER);
        $message->setDateEnvoi(new \DateTimeImmutable());
        $form = $this->createForm(MessageEvenementType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($message);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre message a été envoyé. L\'équipe vous répondra sous peu.');
        } else {
            $this->addFlash('error', 'Le message ne peut pas être vide.');
        }

        return $this->redirectToRoute('user_event_show', ['id' => $id]);
    }

    #[Route('/rendez-vous', name: 'user_appointments', methods: ['GET'])]
    public function appointments(): Response
    {
        $medecins = $this->medecinRepository->findAllOrderByNom();
        $specialites = array_values(array_unique(array_filter(array_map(
            static fn (Medcin $m) => $m->getSpecialite(),
            $medecins
        ))));
        sort($specialites);
        return $this->render('front/appointments/index.html.twig', [
            'medecins' => $medecins,
            'specialites' => $specialites,
        ]);
    }

    private const APPOINTMENT_TYPE_LABELS = [
        'premiere' => 'Première consultation',
        'bilan' => 'Bilan complet',
        'suivi' => 'Consultation de suivi',
        'urgent' => 'Consultation urgente',
    ];
    private const APPOINTMENT_MODE_LABELS = [
        'cabinet' => 'Au cabinet',
    ];

    #[Route('/rendez-vous/prendre/{id}', name: 'user_appointment_book', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function appointmentBook(int $id, Request $request): Response
    {
        $medecin = $this->medecinRepository->find($id);
        if ($medecin === null || !$medecin instanceof Medcin) {
            throw $this->createNotFoundException('Praticien introuvable.');
        }
        $doctor = $this->medecinToDoctorArray($medecin);
        $step = (int) $request->query->get('etape', 1);
        $step = max(1, min(4, $step));

        $disponibiliteId = $request->query->get('disponibilite_id');
        $dateRdvStr = (string) $request->query->get('date_rdv', '');
        $type = (string) $request->query->get('type', 'premiere');
        $mode = (string) $request->query->get('mode', 'cabinet');
        $motif = (string) $request->query->get('motif', '');
        $motifError = null;

        if (!isset(self::APPOINTMENT_TYPE_LABELS[$type])) {
            $type = 'premiere';
        }
        $mode = 'cabinet';

        $slotResult = $this->getAvailableSlotsForMedecin($medecin);
        $slots = $slotResult['slots'];
        $filterDays = $slotResult['filter_days'];

        // Obligation de passer par chaque étape : étape 2+ exige un créneau valide
        if ($step >= 2) {
            $dispoIdInt = (int) $disponibiliteId;
            $slotOk = false;
            if ($dispoIdInt > 0) {
                $disponibilite = $this->disponibiliteRepository->find($dispoIdInt);
                if ($disponibilite !== null && $disponibilite->getMedecin() === $medecin && $disponibilite->getDate() !== null) {
                    $expectedDate = $disponibilite->getDate()->format('Y-m-d');
                    if ($dateRdvStr === $expectedDate && !$this->rendezVousRepository->isSlotTaken($disponibilite)) {
                        $slotOk = true;
                    }
                }
            }
            if (!$slotOk) {
                $step = 1;
                $disponibiliteId = null;
                $dateRdvStr = '';
            }
        }

        // Contrôle de saisie : motif obligatoire avant de passer à l'étape 3
        if ($step === 3 && $motif === '') {
            $motifError = 'Le motif de la consultation est obligatoire.';
            $step = 2;
        }

        $selectedSlotAvailable = false;
        if ($disponibiliteId !== null && $disponibiliteId !== '' && $dateRdvStr !== '') {
            foreach ($slots as $s) {
                if ((int) $s['disponibilite_id'] === (int) $disponibiliteId && $s['date_rdv'] === $dateRdvStr) {
                    $selectedSlotAvailable = $s['available'];
                    break;
                }
            }
        }

        $choices = [
            'disponibilite_id' => $disponibiliteId,
            'date_rdv' => $dateRdvStr,
            'date_label' => $request->query->get('date_label', ''),
            'type' => $type,
            'type_label' => self::APPOINTMENT_TYPE_LABELS[$type],
            'mode' => $mode,
            'mode_label' => self::APPOINTMENT_MODE_LABELS[$mode],
            'motif' => $motif,
        ];

        $formRdv = [];
        $formErrors = [];
        if ($step === 3) {
            $session = $request->getSession();
            if ($session->has('rdv_form_data')) {
                $formRdv = $session->get('rdv_form_data', []);
                $session->remove('rdv_form_data');
            }
            if ($session->has('rdv_form_errors')) {
                $formErrors = $session->get('rdv_form_errors', []);
                $session->remove('rdv_form_errors');
            }
        }

        $requireLogin = !$this->getUser();
        $returnUri = $request->getPathInfo() . ($request->getQueryString() ? '?' . $request->getQueryString() : '');

        return $this->render('front/appointments/book.html.twig', [
            'doctor' => $doctor,
            'step' => $step,
            'choices' => $choices,
            'slots' => $slots,
            'filter_days' => $filterDays,
            'selected_slot_available' => $selectedSlotAvailable,
            'form_rdv' => $formRdv,
            'form_errors' => $formErrors,
            'require_login' => $requireLogin,
            'return_uri' => $returnUri,
            'motif_error' => $motifError,
        ]);
    }

    /** @return array{slots: list<array>, filter_days: list<string>} */
    private function getAvailableSlotsForMedecin(Medcin $medecin): array
    {
        $dispos = $this->disponibiliteRepository->findByMedecin($medecin);
        $today = new \DateTimeImmutable('today');
        $end = $today->modify('+4 weeks');
        $slots = [];

        $now = new \DateTimeImmutable('now');
        foreach ($dispos as $dispo) {
            if (!$dispo->isEstDispo()) {
                continue;
            }
            $date = $dispo->getDate();
            if ($date === null) {
                continue;
            }
            $dateImmutable = $date instanceof \DateTimeImmutable ? $date : new \DateTimeImmutable($date->format('Y-m-d'));
            if ($dateImmutable < $today || $dateImmutable > $end) {
                continue;
            }
            // Ne pas afficher les créneaux dont la date et l'heure de fin sont passées
            $heureFin = $dispo->getHeureFin();
            if ($heureFin !== null) {
                $slotEnd = $dateImmutable->setTime(
                    (int) $heureFin->format('H'),
                    (int) $heureFin->format('i'),
                    (int) $heureFin->format('s')
                );
                if ($slotEnd < $now) {
                    continue;
                }
            }
            $taken = $this->rendezVousRepository->isSlotTaken($dispo);
            $heureDebut = $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H:i') : '—';
            $heureFin = $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H:i') : '—';
            $dayOfWeek = (int) $dateImmutable->format('w');
            $dayName = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][$dayOfWeek];
            $slots[] = [
                'disponibilite_id' => $dispo->getId(),
                'date_rdv' => $dateImmutable->format('Y-m-d'),
                'label' => $dayName . ' ' . $dateImmutable->format('d/m/Y') . ', ' . $heureDebut . '-' . $heureFin,
                'day_name' => $dayName,
                'time_range' => $heureDebut . '-' . $heureFin,
                'available' => !$taken,
            ];
        }
        usort($slots, static fn (array $a, array $b): int => strcmp($a['date_rdv'], $b['date_rdv']));
        $uniqueDays = array_values(array_unique(array_column($slots, 'day_name')));
        return ['slots' => $slots, 'filter_days' => $uniqueDays];
    }

    #[Route('/rendez-vous/prendre/{id}/confirmer', name: 'user_appointment_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentSubmit(int $id, Request $request): Response
    {
        $medecin = $this->medecinRepository->find($id);
        if ($medecin === null || !$medecin instanceof Medcin) {
            throw $this->createNotFoundException('Praticien introuvable.');
        }

        $disponibiliteId = (int) $request->request->get('disponibilite_id', 0);
        $dateRdvStr = (string) $request->request->get('date_rdv', '');
        $disponibilite = $disponibiliteId > 0 ? $this->disponibiliteRepository->find($disponibiliteId) : null;

        if ($disponibilite === null || $disponibilite->getMedecin() !== $medecin) {
            $this->addFlash('error', 'Créneau invalide.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }

        $dateRdv = $disponibilite->getDate();
        if ($dateRdv === null) {
            $this->addFlash('error', 'Date du créneau invalide.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }
        $dateRdv = $dateRdv instanceof \DateTimeImmutable ? $dateRdv : new \DateTimeImmutable($dateRdv->format('Y-m-d'));

        if ($this->rendezVousRepository->isSlotTaken($disponibilite)) {
            $this->addFlash('error', 'Ce créneau n\'est plus disponible.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }

        $nom = trim((string) $request->request->get('nom', ''));
        $prenom = trim((string) $request->request->get('prenom', ''));
        $telephone = trim((string) $request->request->get('telephone', ''));
        $adresse = trim((string) $request->request->get('adresse', ''));
        $note = trim((string) $request->request->get('note', ''));
        $dateNaissanceStr = trim((string) $request->request->get('date_naissance', ''));

        $errors = [];
        $errorsByField = [];
        if ($nom === '') {
            $errors[] = 'Le nom est obligatoire.';
            $errorsByField['nom'] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom) < 2) {
            $errors[] = 'Le nom doit contenir au moins 2 caractères.';
            $errorsByField['nom'] = 'Le nom doit contenir au moins 2 caractères.';
        } elseif (mb_strlen($nom) > 255) {
            $errors[] = 'Le nom ne peut pas dépasser 255 caractères.';
            $errorsByField['nom'] = 'Le nom ne peut pas dépasser 255 caractères.';
        }
        if ($prenom === '') {
            $errors[] = 'Le prénom est obligatoire.';
            $errorsByField['prenom'] = 'Le prénom est obligatoire.';
        } elseif (mb_strlen($prenom) < 2) {
            $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
            $errorsByField['prenom'] = 'Le prénom doit contenir au moins 2 caractères.';
        } elseif (mb_strlen($prenom) > 255) {
            $errors[] = 'Le prénom ne peut pas dépasser 255 caractères.';
            $errorsByField['prenom'] = 'Le prénom ne peut pas dépasser 255 caractères.';
        }
        if ($telephone === '') {
            $errors[] = 'Le téléphone est obligatoire.';
            $errorsByField['telephone'] = 'Le téléphone est obligatoire.';
        } elseif (mb_strlen($telephone) > 30) {
            $errors[] = 'Le téléphone ne peut pas dépasser 30 caractères.';
            $errorsByField['telephone'] = 'Le téléphone ne peut pas dépasser 30 caractères.';
        } elseif (!preg_match('/^[\d\s\-\+\.\(\)]+$/', $telephone)) {
            $errors[] = 'Le téléphone contient des caractères non autorisés.';
            $errorsByField['telephone'] = 'Le téléphone contient des caractères non autorisés.';
        } elseif (preg_match_all('/\d/', $telephone) < 8) {
            $errors[] = 'Le numéro de téléphone doit contenir au moins 8 chiffres.';
            $errorsByField['telephone'] = 'Le numéro de téléphone doit contenir au moins 8 chiffres.';
        }
        if ($adresse === '') {
            $errors[] = 'L\'adresse est obligatoire.';
            $errorsByField['adresse'] = 'L\'adresse est obligatoire.';
        } elseif (mb_strlen($adresse) > 500) {
            $errors[] = 'L\'adresse ne peut pas dépasser 500 caractères.';
            $errorsByField['adresse'] = 'L\'adresse ne peut pas dépasser 500 caractères.';
        }
        if ($dateNaissanceStr === '') {
            $errors[] = 'La date de naissance est obligatoire.';
            $errorsByField['date_naissance'] = 'La date de naissance est obligatoire.';
        } else {
            try {
                $dateNaissanceTest = new \DateTimeImmutable($dateNaissanceStr);
                if ($dateNaissanceTest > new \DateTimeImmutable('today')) {
                    $errors[] = 'La date de naissance ne peut pas être dans le futur.';
                    $errorsByField['date_naissance'] = 'La date de naissance ne peut pas être dans le futur.';
                }
            } catch (\Throwable) {
                $errors[] = 'La date de naissance est invalide.';
                $errorsByField['date_naissance'] = 'La date de naissance est invalide.';
            }
        }
        if (mb_strlen($note) > 5000) {
            $errors[] = 'La note ne peut pas dépasser 5000 caractères.';
            $errorsByField['note'] = 'La note ne peut pas dépasser 5000 caractères.';
        }

        if ($errors !== []) {
            $session = $request->getSession();
            $session->set('rdv_form_data', [
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'adresse' => $adresse,
                'note' => $note,
                'date_naissance' => $dateNaissanceStr,
            ]);
            $session->set('rdv_form_errors', $errorsByField);
            // Toujours inclure le créneau pour rester sur l'étape 3 et afficher les erreurs
            $redirectParams = [
                'id' => $id,
                'etape' => 3,
                'disponibilite_id' => $disponibiliteId,
                'date_rdv' => $dateRdvStr,
                'date_label' => (string) $request->request->get('date_label', ''),
                'type' => (string) $request->request->get('type', 'premiere'),
                'mode' => (string) $request->request->get('mode', 'cabinet'),
                'motif' => (string) $request->request->get('motif', ''),
            ];
            return $this->redirectToRoute('user_appointment_book', $redirectParams);
        }

        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('rdv_submit', $token)) {
            $this->addFlash('error', 'Session expirée. Veuillez recommencer.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }

        $motifKey = (string) $request->request->get('motif_key', 'normal');
        $motif = match ($motifKey) {
            'urgence' => Motif::URGENCE,
            'suivie' => Motif::SUIVIE,
            default => Motif::NORMAL,
        };

        $rdv = new RendezVous();
        $rdv->setMedecin($medecin);
        $rdv->setDisponibilite($disponibilite);
        $rdv->setDateRdv($dateRdv);
        $rdv->setNom($nom);
        $rdv->setPrenom($prenom);
        $rdv->setStatus(StatusRendezVous::EN_ATTENTE);
        $rdv->setMotif($motif);
        $rdv->setTelephone($telephone !== '' ? $telephone : null);
        $rdv->setAdresse($adresse !== '' ? $adresse : null);
        $rdv->setNotePatient($note !== '' ? $note : 'vide');
        if ($dateNaissanceStr !== '') {
            try {
                $rdv->setDateNaissance(new \DateTimeImmutable($dateNaissanceStr));
            } catch (\Throwable) {
            }
        }
        $user = $this->getUser();
        if ($user instanceof Patient) {
            $rdv->setPatient($user);
        }

        $this->entityManager->persist($rdv);
        $this->entityManager->flush();

        $notif = new Notification();
        $notif->setDestinataire($medecin);
        $notif->setType(Notification::TYPE_DEMANDE_RDV);
        $notif->setRendezVous($rdv);
        $this->entityManager->persist($notif);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre demande de rendez-vous a été envoyée. Le médecin vous répondra sous peu.');

        return $this->redirectToRoute('user_appointment_book', [
            'id' => $id,
            'etape' => 4,
            'date_rdv' => $dateRdvStr,
            'date_label' => $request->request->get('date_label', ''),
            'type' => $request->request->get('type', 'premiere'),
            'mode' => $request->request->get('mode', 'cabinet'),
            'motif' => $request->request->get('motif', ''),
        ]);
    }

    /**
     * @return array{id: int, name: string, initials: string, specialty: string, specialty_class: string, rating: string, reviews: int, description: string, address: string, phone: string, email: string, price: int|float, has_cabinet: bool, has_teleconsult: bool}
     */
    private function medecinToDoctorArray(Medcin $medecin): array
    {
        $nom = $medecin->getNom() ?? '';
        $prenom = $medecin->getPrenom() ?? '';
        $initials = (mb_substr($nom, 0, 1) . mb_substr($prenom, 0, 1)) ?: 'DR';
        $specialite = $medecin->getSpecialite() ?? 'Spécialiste';
        $specialtyClass = match (mb_strtolower($specialite)) {
            'psychiatre' => 'bg-emerald-100 text-emerald-800',
            'psychologue' => 'bg-emerald-100 text-emerald-800',
            'orthophoniste' => 'bg-sky-100 text-sky-800',
            default => 'bg-[#A7C7E7]/20 text-[#4B5563]',
        };

        return [
            'id' => $medecin->getId(),
            'name' => trim('Dr. ' . $nom . ' ' . $prenom) ?: 'Praticien',
            'initials' => mb_strtoupper($initials),
            'specialty' => $specialite,
            'specialty_class' => $specialtyClass,
            'rating' => '—',
            'reviews' => 0,
            'description' => 'Praticien accompagnant les personnes avec TSA. Cabinet : ' . ($medecin->getNomCabinet() ?? 'non renseigné') . '.',
            'address' => $medecin->getAdresseCabinet() ?? '—',
            'phone' => $medecin->getTelephoneCabinet() ?? $medecin->getTelephone() ?? '—',
            'email' => $medecin->getEmail() ?? '—',
            'price' => (int) round($medecin->getTarifConsultation() ?? 0),
            'has_cabinet' => $medecin->getAdresseCabinet() !== null && $medecin->getAdresseCabinet() !== '',
            'has_teleconsult' => false,
        ];
    }
}