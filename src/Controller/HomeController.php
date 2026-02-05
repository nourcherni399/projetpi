<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Module;
use App\Form\BlogType;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
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
        return $this->render('front/events/index.html.twig', [
            'events' => $this->getEventsData(),
        ]);
    }

    #[Route('/evenements/{id}', name: 'user_event_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function eventShow(int $id): Response
    {
        $events = $this->getEventsData();
        $event = $events[$id] ?? null;
        if ($event === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }
        $event['id'] = $id;
        return $this->render('front/events/show.html.twig', ['event' => $event]);
    }

    /**
     * @return array<int, array{
     *   title: string,
     *   date_label: string,
     *   day: string,
     *   month_year: string,
     *   time_range: string,
     *   location: string,
     *   capacity: string,
     *   capacity_max: int,
     *   capacity_current: int,
     *   animator: string,
     *   price_display: string,
     *   type_tag: string,
     *   public_tags: list<string>,
     *   urgency_tag: string|null,
     *   description_short: string,
     *   description_long: string,
     *   programme: list<array{label: string, duration: string}>,
     *   address_full: string,
     *   places_remaining: int,
     *   contact_label: string
     * }>
     */
    private function getEventsData(): array
    {
        return [
            1 => [
                'title' => 'Atelier Sensoriel pour Enfants',
                'date_label' => '15 Février 2026',
                'day' => '15',
                'month_year' => 'Février 2026',
                'time_range' => '14:00 - 16:00',
                'location' => 'Centre AutiCare, Paris',
                'capacity' => '4/12 places',
                'capacity_max' => 12,
                'capacity_current' => 8,
                'animator' => 'Marie Dupont',
                'price_display' => '25 €',
                'type_tag' => 'Atelier',
                'public_tags' => ['Enfants', 'Enfants (6-12 ans)'],
                'urgency_tag' => 'Plus que 4 places !',
                'description_short' => 'Un atelier ludique pour explorer différentes textures et sensations dans un environnement calme et adapté.',
                'description_long' => 'Cet atelier est conçu pour les enfants autistes de 6 à 12 ans. Dans un environnement calme et adapté, les participants explorent différentes textures, sons et sensations, encadrés par des professionnels formés. Un moment de découverte et d\'apaisement pour favoriser le bien-être sensoriel.',
                'programme' => [
                    ['label' => 'Accueil et présentation', 'duration' => '15 min'],
                    ['label' => 'Exploration tactile : différentes textures', 'duration' => '30 min'],
                    ['label' => 'Pause sensorielle apaisante', 'duration' => '15 min'],
                    ['label' => 'Jeux de sons et de lumières', 'duration' => '30 min'],
                    ['label' => 'Activité créative libre', 'duration' => '20 min'],
                    ['label' => 'Temps calme et conclusion', 'duration' => '10 min'],
                ],
                'address_full' => '12 rue de la Paix, 75002 Paris',
                'places_remaining' => 4,
                'contact_label' => 'Contacter l\'organisateur',
            ],
            2 => [
                'title' => 'Rencontre Parents & Familles',
                'date_label' => '22 Février 2026',
                'day' => '22',
                'month_year' => 'Février 2026',
                'time_range' => '10:00 - 12:00',
                'location' => 'En ligne (Zoom)',
                'capacity' => '18/30 places',
                'capacity_max' => 30,
                'capacity_current' => 12,
                'animator' => 'Équipe AutiCare',
                'price_display' => 'Gratuit',
                'type_tag' => 'Rencontre',
                'public_tags' => ['Familles'],
                'urgency_tag' => null,
                'description_short' => 'Échangez avec d\'autres familles concernées par l\'autisme dans un cadre bienveillant et sans jugement.',
                'description_long' => 'Un moment d\'échange et de partage d\'expériences entre parents et proches dans un cadre bienveillant. Rencontres en visioconférence pour permettre à tous de participer.',
                'programme' => [],
                'address_full' => 'En ligne',
                'places_remaining' => 18,
                'contact_label' => 'Contacter l\'organisateur',
            ],
            3 => [
                'title' => 'Séance de Sensibilisation',
                'date_label' => '1 Mars 2026',
                'day' => '1',
                'month_year' => 'Mars 2026',
                'time_range' => '18:00 - 20:00',
                'location' => 'Médiathèque Centrale, Lyon',
                'capacity' => '32/50 places',
                'capacity_max' => 50,
                'capacity_current' => 18,
                'animator' => 'Dr. Pierre Martin',
                'price_display' => 'Gratuit',
                'type_tag' => 'Sensibilisation',
                'public_tags' => ['Tout public'],
                'urgency_tag' => null,
                'description_short' => 'Une session pour mieux comprendre l\'autisme, déconstruire les idées reçues et apprendre à accompagner.',
                'description_long' => 'Session d\'information et d\'échanges pour mieux comprendre l\'autisme, déconstruire les idées reçues et découvrir comment accompagner au quotidien.',
                'programme' => [],
                'address_full' => 'Médiathèque Centrale, Lyon',
                'places_remaining' => 32,
                'contact_label' => 'Contacter l\'organisateur',
            ],
            4 => [
                'title' => 'Formation Communication Visuelle',
                'date_label' => '8 Mars 2026',
                'day' => '8',
                'month_year' => 'Mars 2026',
                'time_range' => '09:00 - 17:00',
                'location' => 'Centre AutiCare, Paris',
                'capacity' => '7/15 places',
                'capacity_max' => 15,
                'capacity_current' => 8,
                'animator' => 'Sophie Laurent',
                'price_display' => '120 €',
                'type_tag' => 'Formation',
                'public_tags' => ['Professionnels'],
                'urgency_tag' => null,
                'description_short' => 'Formation complète sur les outils de communication visuelle et leur utilisation avec les personnes autistes.',
                'description_long' => 'Formation d\'une journée pour les professionnels sur les outils de communication visuelle (PECS, pictogrammes, emplois du temps visuels) et leur mise en œuvre.',
                'programme' => [],
                'address_full' => 'Centre AutiCare, Paris',
                'places_remaining' => 7,
                'contact_label' => 'Contacter l\'organisateur',
            ],
        ];
    }

    #[Route('/rendez-vous', name: 'user_appointments', methods: ['GET'])]
    public function appointments(): Response
    {
        return $this->render('front/appointments/index.html.twig');
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

    #[Route('/rendez-vous/prendre/{id}', name: 'user_appointment_book', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function appointmentBook(int $id, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $doctors = $this->getDoctorsData();
        $doctor = $doctors[$id] ?? null;
        if ($doctor === null) {
            throw $this->createNotFoundException('Praticien introuvable.');
        }
        $doctor['id'] = $id;
        $step = (int) $request->query->get('etape', 1);
        $step = max(1, min(4, $step));

        $date = (string) $request->query->get('date', '');
        $time = (string) $request->query->get('time', '');
        $type = (string) $request->query->get('type', 'premiere');
        $mode = (string) $request->query->get('mode', 'cabinet');
        $motif = (string) $request->query->get('motif', '');

        if (!isset(self::APPOINTMENT_TYPE_LABELS[$type])) {
            $type = 'premiere';
        }
        if (!isset(self::APPOINTMENT_MODE_LABELS[$mode])) {
            $mode = 'cabinet';
        }

        $choices = [
            'date' => $date,
            'time' => $time,
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
        ]);
    }

    /** @return array<int, array{name: string, initials: string, specialty: string, specialty_class: string, rating: string, reviews: int, description: string, address: string, phone: string, email: string, price: int, has_cabinet: bool, has_teleconsult: bool}> */
    private function getDoctorsData(): array
    {
        return [
            1 => [
                'name' => 'Dr. Marie Dupont',
                'initials' => 'DMD',
                'specialty' => 'Psychiatre',
                'specialty_class' => 'bg-emerald-100 text-emerald-800',
                'rating' => '4.9',
                'reviews' => 127,
                'description' => 'Spécialisée dans le diagnostic et l\'accompagnement des troubles du spectre autistique chez l\'enfant et l\'adulte. Plus de 15 ans d\'expérience.',
                'address' => '45 rue de la Roquette, 75011 Paris',
                'phone' => '01 23 45 67 89',
                'email' => 'contact@dr-dupont.fr',
                'price' => 80,
                'has_cabinet' => true,
                'has_teleconsult' => true,
            ],
            2 => [
                'name' => 'Dr. Thomas Bernard',
                'initials' => 'DTB',
                'specialty' => 'Psychologue',
                'specialty_class' => 'bg-emerald-100 text-emerald-800',
                'rating' => '4.8',
                'reviews' => 89,
                'description' => 'Psychologue clinicien spécialisé dans les thérapies comportementales et cognitives adaptées à l\'autisme.',
                'address' => 'Lyon 6ème',
                'phone' => '01 23 45 67 89',
                'email' => 'contact@dr-bernard.fr',
                'price' => 70,
                'has_cabinet' => true,
                'has_teleconsult' => true,
            ],
            3 => [
                'name' => 'Claire Lefebvre',
                'initials' => 'CL',
                'specialty' => 'Orthophoniste',
                'specialty_class' => 'bg-sky-100 text-sky-800',
                'rating' => '4.7',
                'reviews' => 56,
                'description' => 'Accompagnement de la communication et du langage chez les personnes avec TSA.',
                'address' => 'Paris 15ème',
                'phone' => '01 23 45 67 89',
                'email' => 'contact@claire-lefebvre.fr',
                'price' => 65,
                'has_cabinet' => true,
                'has_teleconsult' => false,
            ],
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
