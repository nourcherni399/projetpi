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
use App\Entity\Module;
use App\Enum\Categorie;
use App\Form\BlogType;
use App\Repository\DisponibiliteRepository;
use App\Repository\EvenementRepository;
use App\Repository\MedcinRepository;
use App\Repository\ModuleRepository;
use App\Repository\NotificationRepository;
use App\Repository\RendezVousRepository;
use App\Repository\ThematiqueRepository;
use App\Repository\ProduitRepository;
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
        private readonly ProduitRepository $produitRepository,
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
    public function products(Request $request): Response
    {
        $categorie = $request->query->get('categorie');
        $minPrice = $request->query->get('minPrice');
        $maxPrice = $request->query->get('maxPrice');
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sortBy', 'nom'); // 'nom' ou 'prix'
        $sortOrder = $request->query->get('sortOrder', 'asc'); // 'asc' ou 'desc'
        
        $criteria = [];
        
        if ($categorie) {
            try {
                $categorieEnum = Categorie::from($categorie);
                $criteria['categorie'] = $categorieEnum;
            } catch (\ValueError $e) {
                // Invalid category, ignore
            }
        }
        
        // Définir l'ordre de tri
        $orderBy = [];
        if ($sortBy === 'prix') {
            $orderBy['prix'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        } else {
            $orderBy['nom'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        }
        
        $produits = $this->produitRepository->findBy($criteria, $orderBy);
        
        // Filter by price range and search term in PHP
        if ($minPrice !== null || $maxPrice !== null || $search !== null) {
            $minPrice = $minPrice !== null ? (int)$minPrice : 0;
            $maxPrice = $maxPrice !== null ? (int)$maxPrice : PHP_INT_MAX;
            $searchTerm = $search !== null ? strtolower(trim($search)) : '';
            
            $produits = array_filter($produits, function($produit) use ($minPrice, $maxPrice, $searchTerm) {
                $priceMatch = $produit->getPrix() >= $minPrice && $produit->getPrix() <= $maxPrice;
                
                if ($searchTerm === '') {
                    return $priceMatch;
                }
                
                $nomMatch = strpos(strtolower($produit->getNom()), $searchTerm) !== false;
                $descriptionMatch = $produit->getDescription() && strpos(strtolower($produit->getDescription()), $searchTerm) !== false;
                $categorieMatch = $produit->getCategorie() && strpos(strtolower($produit->getCategorie()->label()), $searchTerm) !== false;
                
                return $priceMatch && ($nomMatch || $descriptionMatch || $categorieMatch);
            });
        }
        
        return $this->render('front/products/index.html.twig', [
            'produits' => $produits,
            'categories' => Categorie::cases(),
            'selectedCategorie' => $categorie,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
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
    public function events(): Response
    {
        $thematiques = $this->thematiqueRepository->findBy(['actif' => true], ['ordre' => 'ASC', 'nomThematique' => 'ASC']);
        $grouped = [];
        foreach ($thematiques as $t) {
            $evenements = $t->getEvenements()->toArray();
            usort($evenements, static function (Evenement $a, Evenement $b): int {
                $d = ($a->getDateEvent() <=> $b->getDateEvent());
                return $d !== 0 ? $d : ($a->getHeureDebut() <=> $b->getHeureDebut());
            });
            $grouped[] = ['thematique' => $t, 'evenements' => $evenements];
        }
        $sansThematique = $this->evenementRepository->findBy(['thematique' => null], ['dateEvent' => 'ASC', 'heureDebut' => 'ASC']);
        return $this->render('front/events/index.html.twig', [
            'grouped' => $grouped,
            'sansThematique' => $sansThematique,
        ]);
    }

    #[Route('/evenements/{id}', name: 'user_event_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function eventShow(int $id): Response
    {
        $evenement = $this->evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }
        return $this->render('front/events/show.html.twig', ['evenement' => $evenement]);
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


    #[Route('/inscription', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('front/auth/register.html.twig');
    }

    #[Route('/connexion', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('front/auth/login.html.twig');
    }
}