<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Disponibilite;
use App\Entity\Evenement;
use App\Entity\Medcin;
use App\Entity\Notification;
use App\Entity\Patient;
use App\Entity\RendezVous;
use App\Enum\Motif;
use App\Enum\StatusRendezVous;
use App\Enum\UserRole;
use App\Form\BlogType;
use App\Entity\InscritEvents;
use App\Repository\DisponibiliteRepository;
use App\Repository\EvenementRepository;
use App\Repository\InscritEventsRepository;
use App\Repository\MedcinRepository;
use App\Repository\ModuleRepository;
use App\Repository\NotificationRepository;
use App\Repository\RendezVousRepository;
use App\Repository\ThematiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
        private readonly ThematiqueRepository $thematiqueRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly MedcinRepository $medcinRepository,
        private readonly DisponibiliteRepository $disponibiliteRepository,
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly InscritEventsRepository $inscritEventsRepository,
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

    #[Route('/produits', name: 'user_products', methods: ['GET'])]
    public function products(): Response
    {
        return $this->render('front/products/index.html.twig');
    }

    #[Route('/produits/{id}', name: 'user_product_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function productShow(int $id): Response
    {
        $products = $this->getProductsData();
        $product = $products[$id] ?? null;
        if ($product === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }
        $product['id'] = $id;
        return $this->render('front/products/show.html.twig', ['product' => $product]);
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
        $dateFrom = null;
        $dateTo = null;
        $dateFromStr = trim((string) $request->query->get('date_from', ''));
        $dateToStr = trim((string) $request->query->get('date_to', ''));
        if ($dateFromStr !== '') {
            try {
                $dateFrom = new \DateTimeImmutable($dateFromStr);
            } catch (\Throwable) {
            }
        }
        if ($dateToStr !== '') {
            try {
                $dateTo = new \DateTimeImmutable($dateToStr);
            } catch (\Throwable) {
            }
        }
        $themeId = $request->query->get('theme');
        $themeId = is_numeric($themeId) ? (int) $themeId : null;
        $lieu = trim((string) $request->query->get('lieu', ''));

        $evenementsFiltres = $this->evenementRepository->findFiltered($dateFrom, $dateTo, $themeId, $lieu === '' ? null : $lieu);

        $thematiques = $this->thematiqueRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);
        $lieux = $this->evenementRepository->findDistinctLieux();

        $grouped = [];
        foreach ($thematiques as $t) {
            if ($themeId !== null && $t->getId() !== $themeId) {
                continue;
            }
            $evenements = array_values(array_filter($evenementsFiltres, static fn (Evenement $e) => $e->getThematique() && $e->getThematique()->getId() === $t->getId()));
            $grouped[] = ['thematique' => $t, 'evenements' => $evenements];
        }
        $sansThematique = array_values(array_filter($evenementsFiltres, static fn (Evenement $e) => $e->getThematique() === null));

        $eventCards = [];
        foreach ($evenementsFiltres as $ev) {
            $eventCards[] = ['event' => $ev, 'thematique' => $ev->getThematique()];
        }

        return $this->render('front/events/index.html.twig', [
            'grouped' => $grouped,
            'sansThematique' => $sansThematique,
            'eventCards' => $eventCards,
            'thematiques' => $thematiques,
            'lieux' => $lieux,
            'filters' => [
                'date_from' => $dateFromStr,
                'date_to' => $dateToStr,
                'theme' => $themeId,
                'lieu' => $lieu,
            ],
        ]);
    }

    #[Route('/evenements/{id}', name: 'user_event_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function eventShow(int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }
        $user = $this->getUser();
        $userInscrit = $user !== null
            ? $this->inscritEventsRepository->findInscriptionForUserAndEvent($user, $evenement) !== null
            : false;

        return $this->render('front/events/show.html.twig', [
            'evenement' => $evenement,
            'userInscrit' => $userInscrit,
        ]);
    }

    #[Route('/evenements/{id}/inscrire', name: 'user_event_register', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eventRegister(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            $this->addFlash('error', 'Connectez-vous pour vous inscrire à un événement.');
            return $this->redirectToRoute('app_login');
        }

        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        if (!$this->isCsrfTokenValid('event_register_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('user_event_show', ['id' => $id]);
        }

        if ($this->inscritEventsRepository->findInscriptionForUserAndEvent($user, $evenement) !== null) {
            $this->addFlash('info', 'Vous êtes déjà inscrit à cet événement.');
            return $this->redirectToRoute('user_event_show', ['id' => $id]);
        }

        $inscrit = new InscritEvents();
        $inscrit->setUser($user);
        $inscrit->setEvenement($evenement);
        $inscrit->setDateInscrit(new \DateTime());
        $inscrit->setEstInscrit(true);
        $this->entityManager->persist($inscrit);
        $this->entityManager->flush();

        $this->addFlash('success', 'Vous êtes inscrit à l\'événement.');
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
        if ($inscrit !== null) {
            $inscrit->setEstInscrit(false);
            $this->entityManager->flush();
            $this->addFlash('success', 'Vous avez été désinscrit de l\'événement.');
        } else {
            $this->addFlash('info', 'Vous n\'étiez pas inscrit à cet événement.');
        }

        return $this->redirectToRoute('user_event_show', ['id' => $id]);
    }

    #[Route('/rendez-vous', name: 'user_appointments', methods: ['GET'])]
    public function appointments(): Response
    {
        $medecins = $this->medcinRepository->findAllOrderByNom();
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

    /** Labels pour type de consultation et mode (affichage récap/confirmation). */
    private const APPOINTMENT_TYPE_LABELS = [
        'premiere' => 'Première consultation',
        'bilan' => 'Bilan complet',
        'suivi' => 'Consultation de suivi',
        'urgent' => 'Consultation urgente',
    ];
    private const APPOINTMENT_MODE_LABELS = [
        'cabinet' => 'Au cabinet',
        'teleconsult' => 'Téléconsultation',
    ];

    /** Numéro de jour PHP (1=lundi) pour chaque Jour enum. */
    private const JOUR_TO_NUMBER = [
        'lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4,
        'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7,
    ];

    #[Route('/rendez-vous/prendre/{id}', name: 'user_appointment_book', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function appointmentBook(int $id, Request $request): Response
    {
        $medecin = $this->medcinRepository->find($id);
        if ($medecin === null || !$medecin instanceof Medcin) {
            throw $this->createNotFoundException('Praticien introuvable.');
        }
        $doctor = $this->medecinToDoctorArray($medecin);
        $step = (int) $request->query->get('etape', 1);
        $step = max(1, min(4, $step));

        $disponibiliteId = $request->query->get('disponibilite_id');
        $dateRdv = (string) $request->query->get('date_rdv', '');
        $type = (string) $request->query->get('type', 'premiere');
        $mode = (string) $request->query->get('mode', 'cabinet');
        $motif = (string) $request->query->get('motif', '');

        if (!isset(self::APPOINTMENT_TYPE_LABELS[$type])) {
            $type = 'premiere';
        }
        if (!isset(self::APPOINTMENT_MODE_LABELS[$mode])) {
            $mode = 'cabinet';
        }

        $slots = $this->getAvailableSlotsForMedecin($medecin);

        $choices = [
            'disponibilite_id' => $disponibiliteId,
            'date_rdv' => $dateRdv,
            'date_label' => $request->query->get('date_label', ''),
            'type' => $type,
            'type_label' => self::APPOINTMENT_TYPE_LABELS[$type],
            'mode' => $mode,
            'mode_label' => self::APPOINTMENT_MODE_LABELS[$mode],
            'motif' => $motif,
        ];

        return $this->render('front/appointments/book.html.twig', [
            'doctor' => $doctor,
            'step' => $step,
            'choices' => $choices,
            'slots' => $slots,
        ]);
    }

    /**
     * @return list<array{disponibilite_id: int, date_rdv: string, label: string}>
     */
    private function getAvailableSlotsForMedecin(Medcin $medecin): array
    {
        $dispos = $this->disponibiliteRepository->findByMedecin($medecin);
        $slots = [];
        $today = new \DateTimeImmutable('today');
        $end = $today->modify('+4 weeks');
        $jourNumber = self::JOUR_TO_NUMBER;

        foreach ($dispos as $dispo) {
            if (!$dispo->isEstDispo() || $dispo->getJour() === null) {
                continue;
            }
            $jourValue = $dispo->getJour()->value;
            $targetDayNum = $jourNumber[$jourValue] ?? null;
            if ($targetDayNum === null) {
                continue;
            }
            $iter = $today;
            while ($iter <= $end) {
                if ((int) $iter->format('N') === $targetDayNum) {
                    if (!$this->rendezVousRepository->isSlotTaken($dispo, $iter)) {
                        $heureDebut = $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H:i') : '—';
                        $heureFin = $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H:i') : '—';
                        $slots[] = [
                            'disponibilite_id' => $dispo->getId(),
                            'date_rdv' => $iter->format('Y-m-d'),
                            'label' => ucfirst($jourValue) . ' ' . $iter->format('d/m/Y') . ', ' . $heureDebut . '-' . $heureFin,
                        ];
                    }
                }
                $iter = $iter->modify('+1 day');
            }
        }
        usort($slots, static fn (array $a, array $b): int => strcmp($a['date_rdv'] . $a['disponibilite_id'], $b['date_rdv'] . $b['disponibilite_id']));
        return $slots;
    }

    #[Route('/rendez-vous/prendre/{id}/confirmer', name: 'user_appointment_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentSubmit(int $id, Request $request): Response
    {
        $medecin = $this->medcinRepository->find($id);
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

        $dateRdv = null;
        if ($dateRdvStr !== '') {
            try {
                $dateRdv = new \DateTimeImmutable($dateRdvStr);
            } catch (\Throwable) {
            }
        }
        if ($dateRdv === null) {
            $this->addFlash('error', 'Date invalide.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }

        if ($this->rendezVousRepository->isSlotTaken($disponibilite, $dateRdv)) {
            $this->addFlash('error', 'Ce créneau n\'est plus disponible.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id]);
        }

        $nom = trim((string) $request->request->get('nom', ''));
        $prenom = trim((string) $request->request->get('prenom', ''));
        if ($nom === '' || $prenom === '') {
            $this->addFlash('error', 'Nom et prénom obligatoires.');
            return $this->redirectToRoute('user_appointment_book', ['id' => $id, 'etape' => 3] + array_filter([
                'disponibilite_id' => $disponibiliteId ?: null,
                'date_rdv' => $dateRdvStr ?: null,
                'type' => $request->request->get('type'),
                'mode' => $request->request->get('mode'),
                'motif' => $request->request->get('motif'),
            ]));
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
        $rdv->setTelephone((string) $request->request->get('telephone', ''));
        $rdv->setAdresse((string) $request->request->get('adresse', ''));
        $rdv->setNotePatient((string) $request->request->get('note', 'vide'));
        $dateNaissance = $request->request->get('date_naissance');
        if ($dateNaissance !== null && $dateNaissance !== '') {
            try {
                $rdv->setDateNaissance(new \DateTime($dateNaissance));
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
            'has_teleconsult' => true,
        ];
    }

    #[Route('/blog', name: 'user_blog', methods: ['GET'])]
    public function blog(): Response
    {
        $modules = $this->moduleRepository->findPublishedOrderByDate();
        $data = $this->getBlogData($modules);
        return $this->render('front/blog/index.html.twig', [
            'featured' => $data['featured'],
            'articles' => $data['articles'],
            'categories' => $data['categories'],
            'popular_articles' => $data['popular_articles'],
            'popular_tags' => $data['popular_tags'],
        ]);
    }

    #[Route('/blog/module/{id}', name: 'user_blog_module', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function blogModule(int $id): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }
        return $this->render('front/blog/module.html.twig', [
            'module' => $module,
        ]);
    }

    #[Route('/blog/module/{id}/ecrire', name: 'user_blog_ecrire', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function blogEcrire(Request $request, int $id): Response
    {
        $module = $this->moduleRepository->find($id);
        if ($module === null || !$module->isPublished()) {
            throw $this->createNotFoundException('Module introuvable.');
        }

        $blog = new Blog();
        $blog->setModule($module);
        $now = new \DateTime();
        $blog->setDateCreation($now);
        $blog->setDateModif($now);
        $user = $this->getUser();
        if ($user !== null) {
            $blog->setUser($user);
        }

        $form = $this->createForm(BlogType::class, $blog);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $blog->setImage($blog->getImage() ?? '');
            $this->entityManager->persist($blog);
            $this->entityManager->flush();
            $this->addFlash('success', 'Votre article a été enregistré.');
            return $this->redirectToRoute('user_blog_module', ['id' => $id]);
        }

        return $this->render('front/blog/ecrire.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    /**
     * @param list<Module> $modules
     * @return array{
     *   featured: array{id: int, title: string, excerpt: string, author: string, author_initials: string, date: string, likes: int, comments: int, tags: list<string>, has_infographic: bool, infographic_title: string, infographic_description: string, infographic_stats: list<string>},
     *   articles: list<array{id: int, title: string, excerpt: string, author: string, author_initials: string, date: string, likes: int, comments: int, tags: list<string>, highlight: string|null, image_label: string, image_url: string|null}>,
     *   categories: list<array{name: string, count: int}>,
     *   popular_articles: list<array{id: int, title: string}>,
     *   popular_tags: list<string>
     * }
     */
    private function getBlogData(array $modules): array
    {
        $defaultFeatured = [
            'id' => 0,
            'title' => 'Blog & Témoignages',
            'excerpt' => 'Découvrez les modules et articles de la communauté.',
            'author' => 'AutiCare',
            'author_initials' => 'AG',
            'date' => (new \DateTime())->format('d F Y'),
            'likes' => 0,
            'comments' => 0,
            'tags' => ['Blog'],
            'has_infographic' => true,
            'infographic_title' => 'Troubles du spectre de l\'autisme (TSA)',
            'infographic_description' => 'Ce trouble neuro-développemental peut altérer le comportement social, la communication et le langage.',
            'infographic_stats' => ['Pas de cause unique identifiée', 'TOUCHE 1 personne sur 100 • 3 garçons pour 1 fille'],
        ];

        $featured = $defaultFeatured;
        $articles = [];
        $popular_articles = [];
        $niveauLabels = ['difficile' => 'Difficile', 'moyen' => 'Moyen', 'facile' => 'Facile'];

        foreach ($modules as $index => $module) {
            $popular_articles[] = ['id' => $module->getId(), 'title' => $module->getTitre() ?? ''];

            if ($index === 0) {
                $featured = [
                    'id' => $module->getId(),
                    'title' => $module->getTitre() ?? '',
                    'excerpt' => $module->getDescription() ?? '',
                    'author' => 'AutiCare',
                    'author_initials' => 'AG',
                    'date' => $module->getDateCreation() ? $module->getDateCreation()->format('d F Y') : '',
                    'likes' => 0,
                    'comments' => 0,
                    'tags' => ['Module', $niveauLabels[$module->getNiveau()] ?? $module->getNiveau()],
                    'has_infographic' => true,
                    'infographic_title' => $module->getTitre() ?? 'Troubles du spectre de l\'autisme (TSA)',
                    'infographic_description' => $module->getDescription() ?? 'Ce trouble neuro-développemental peut altérer le comportement social, la communication et le langage.',
                    'infographic_stats' => ['Niveau : ' . ($niveauLabels[$module->getNiveau()] ?? $module->getNiveau())],
                ];
            } else {
                $articles[] = [
                    'id' => $module->getId(),
                    'title' => $module->getTitre() ?? '',
                    'excerpt' => $module->getDescription() ?? '',
                    'author' => 'AutiCare',
                    'author_initials' => 'AG',
                    'date' => $module->getDateCreation() ? $module->getDateCreation()->format('d F Y') : '',
                    'likes' => 0,
                    'comments' => 0,
                    'tags' => [$niveauLabels[$module->getNiveau()] ?? $module->getNiveau()],
                    'highlight' => null,
                    'image_label' => $module->getTitre() ?? 'Module',
                    'image_url' => $module->getImage() ?: null,
                ];
            }
        }

        return [
            'featured' => $featured,
            'articles' => $articles,
            'categories' => [
                ['name' => 'Facile', 'count' => \count(array_filter($modules, fn (Module $m) => $m->getNiveau() === 'facile'))],
                ['name' => 'Moyen', 'count' => \count(array_filter($modules, fn (Module $m) => $m->getNiveau() === 'moyen'))],
                ['name' => 'Difficile', 'count' => \count(array_filter($modules, fn (Module $m) => $m->getNiveau() === 'difficile'))],
            ],
            'popular_articles' => \array_slice($popular_articles, 0, 5),
            'popular_tags' => array_values(array_unique(array_merge(
                ['Autisme', 'Développement', 'Famille'],
                array_map(fn (Module $m) => $niveauLabels[$m->getNiveau()] ?? $m->getNiveau(), $modules)
            ))),
        ];
    }

    #[Route('/connexion', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('front/auth/login.html.twig');
    }
}
