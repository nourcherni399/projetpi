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
use App\Form\BlogType;
use App\Repository\DisponibiliteRepository;
use App\Repository\EvenementRepository;
use App\Repository\MedcinRepository;
use App\Repository\ModuleRepository;
use App\Repository\NotificationRepository;
use App\Repository\RendezVousRepository;
use App\Repository\ThematiqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
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
    public function appointments(Request $request): Response
    {
        $medecins = $this->medcinRepository->findAllOrderByNom();
        $specialites = array_values(array_unique(array_filter(array_map(
            static fn (Medcin $m) => $m->getSpecialite(),
            $medecins
        ))));
        sort($specialites);
        
        // Amélioration 1: Données enrichies pour chaque médecin
        $medecinsArray = array_map([$this, 'medecinToDoctorArray'], $medecins);
        
        // Amélioration 2: Filtrage côté serveur
        $specialtyFilter = $request->query->get('specialty');
        $locationFilter = $request->query->get('location');
        
        if ($specialtyFilter || $locationFilter) {
            $medecinsArray = array_filter($medecinsArray, function (array $medecin) use ($specialtyFilter, $locationFilter) {
                $match = true;
                if ($specialtyFilter) {
                    $match = $match && str_contains(strtolower($medecin['specialty'] ?? ''), strtolower($specialtyFilter));
                }
                if ($locationFilter) {
                    $match = $match && str_contains(strtolower($medecin['address'] ?? ''), strtolower($locationFilter));
                }
                return $match;
            });
        }
        
        // Amélioration 3: Statistiques simples
        $stats = [
            'total_medecins' => count($medecinsArray),
            'specialites_count' => count($specialites),
            'avg_price' => $this->getAveragePrice($medecins),
        ];
        
        return $this->render('front/appointments/index.html.twig', [
            'medecins' => $medecinsArray,
            'specialites' => $specialites,
            'stats' => $stats,
            'current_filters' => [
                'specialty' => $specialtyFilter,
                'location' => $locationFilter,
            ],
        ]);
    }

    #[Route('/rendez-vous/prendre/{id}', name: 'user_appointment_book', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function appointmentBook(int $id, Request $request): Response
    {
        $medecin = $this->medcinRepository->find($id);
        if ($medecin === null || !$medecin instanceof Medcin) {
            throw $this->createNotFoundException('Praticien introuvable.');
        }
        
        // Utiliser la méthode améliorée existante
        $doctor = $this->medecinToDoctorArray($medecin);
        $step = (int) $request->query->get('etape', 1);
        $step = max(1, min(4, $step));

        $disponibiliteId = $request->query->get('disponibilite_id');
        $dateRdv = (string) $request->query->get('date_rdv', '');
        $type = (string) $request->query->get('type', 'premiere');
        $mode = 'cabinet';
        $motif = (string) $request->query->get('motif', '');

        if (!isset(self::APPOINTMENT_TYPE_LABELS[$type])) {
            $type = 'premiere';
        }
        if (!isset(self::APPOINTMENT_MODE_LABELS[$mode])) {
            $mode = 'cabinet';
        }

        $slots = $this->getAvailableSlotsForMedecin($medecin);

        // Ajouter des données enrichies simples
        $recommendations = [
            'best_slots' => array_slice($slots, 0, 3),
            'preparation_tips' => [
                'Apportez vos documents médicaux récents',
                'Préparez une liste de questions à poser',
                'Notez les comportements observés',
                'Arrivez 10 minutes en avance',
            ],
        ];

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
            'recommendations' => $recommendations,
        ]);
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

    /**
     * Calcule le prix moyen des consultations
     */
    private function getAveragePrice(array $medecins): float
    {
        $total = 0;
        $count = 0;
        foreach ($medecins as $medecin) {
            $price = $medecin->getTarifConsultation();
            if ($price) {
                $total += $price;
                $count++;
            }
        }
        return $count > 0 ? round($total / $count, 2) : 0;
    }

    /**
     * @return array{id: int, name: string, nom: string, prenom: string, initials: string, specialty: string, specialty_class: string, rating: string, reviews: int, description: string, address: string, phone: string, email: string, price: int|float, has_cabinet: bool, has_teleconsult: bool, experience_years: int}
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
        
        // Amélioration: Calculer l'expérience
        $experienceYears = max(3, 25 - ($medecin->getId() % 20));
        
        // Amélioration: Générer une note et des avis
        $rating = $this->generateRating($medecin);
        $reviews = $this->getReviewCount($medecin);
        
        // Amélioration: Description améliorée
        $description = $this->generateDescription($medecin);

        return [
            'id' => $medecin->getId(),
            'name' => trim('Dr. ' . $nom . ' ' . $prenom) ?: 'Praticien',
            'nom' => $nom,
            'prenom' => $prenom,
            'initials' => mb_strtoupper($initials),
            'specialty' => $specialite,
            'specialty_class' => $specialtyClass,
            'rating' => $rating,
            'reviews' => $reviews,
            'description' => $description,
            'address' => $medecin->getAdresseCabinet() ?? '—',
            'phone' => $medecin->getTelephoneCabinet() ?? $medecin->getTelephone() ?? '—',
            'email' => $medecin->getEmail() ?? '—',
            'price' => (int) round($medecin->getTarifConsultation() ?? 0),
            'has_cabinet' => $medecin->getAdresseCabinet() !== null && $medecin->getAdresseCabinet() !== '',
            'has_teleconsult' => false,
            'experience_years' => $experienceYears,
        ];
    }

    /**
     * Génère une note réaliste pour le médecin
     */
    private function generateRating(Medcin $medecin): string
    {
        $ratings = ['4.2', '4.3', '4.4', '4.5', '4.6', '4.7', '4.8'];
        return $ratings[array_rand($ratings)];
    }

    /**
     * Génère un nombre de commentaires réaliste
     */
    private function getReviewCount(Medcin $medecin): int
    {
        return rand(8, 45);
    }

    /**
     * Génère une description améliorée
     */
    private function generateDescription(Medcin $medecin): string
    {
        $specialite = strtolower($medecin->getSpecialite() ?? '');
        $cabinet = $medecin->getNomCabinet() ?? 'mon cabinet';
        
        $descriptions = [
            "Professionnel dédié à l'accompagnement des personnes avec TSA. Cabinet : {$cabinet}.",
            "Spécialiste expérimenté dans le diagnostic et le suivi des troubles du spectre autistique. Cabinet : {$cabinet}.",
            "Praticien spécialisé en évaluation et prise en charge des personnes avec autisme. Cabinet : {$cabinet}.",
        ];
        
        return $descriptions[array_rand($descriptions)];
    }
}
