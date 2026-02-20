<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\IdeeEvenement;
use App\Repository\IdeeEvenementRepository;
use App\Service\FallbackSearchService;
use App\Service\GoogleCustomSearchService;
use App\Service\HuggingFaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/idees-evenements', name: 'admin_idees_evenement_')]
#[IsGranted('ROLE_ADMIN')]
final class IdeesEvenementController extends AbstractController
{
    private const THEMES = [
        'Sensoriel' => 'atelier sensoriel autisme famille',
        'Inclusion' => 'événement inclusion handicap famille',
        'Familles' => 'activité familles enfants handicap',
        'Ateliers' => 'atelier créatif adapté autisme',
        'Sensibilisation' => 'sensibilisation autisme école inclusion',
    ];

    public function __construct(
        private readonly IdeeEvenementRepository $ideeRepository,
        private readonly GoogleCustomSearchService $googleSearch,
        private readonly FallbackSearchService $fallbackSearch,
        private readonly HuggingFaceService $huggingFace,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    private const PERIODS = [
        'Ce mois' => 'this month',
        'Ce trimestre' => '2025',
        '2025' => '2025',
        '2026' => '2026',
    ];

    private const SESSION_UPCOMING_KEY = 'idees_evenement_upcoming_search';
    private const SESSION_UPCOMING_KEY_EVENTS = 'evenement_recherche_mondiale';
    private const SESSION_LAST_GENERATED_IDS = 'idees_last_generated_ids';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $session = $request->getSession();
        $lastIds = $session->get(self::SESSION_LAST_GENERATED_IDS);
        if ($request->query->get('toutes') === '1') {
            $session->remove(self::SESSION_LAST_GENERATED_IDS);
            $lastIds = null;
        }
        $onlyLastBatch = \is_array($lastIds) && $lastIds !== [];
        $idees = $onlyLastBatch
            ? $this->ideeRepository->findByIdsOrdered($lastIds)
            : $this->ideeRepository->findRecentOrderByCreatedAt(30);
        $upcoming = $session->get(self::SESSION_UPCOMING_KEY);
        $session->remove(self::SESSION_UPCOMING_KEY);

        $params = [
            'idees' => $idees,
            'show_only_last_batch' => $onlyLastBatch,
            'themes' => self::THEMES,
            'periods' => self::PERIODS,
            'googleSearchConfigured' => $this->googleSearch->isConfigured(),
            'hfConfigured' => $this->huggingFace->hasApiKey(),
            'upcomingEventsResults' => $upcoming['results'] ?? null,
            'upcomingEventsError' => $upcoming['error'] ?? null,
            'upcomingSearchQuerySent' => $upcoming['searchQuery'] ?? '',
            'upcomingSearchPerformed' => $upcoming !== null,
            'upcomingSearchQuery' => $upcoming['query'] ?? '',
            'upcomingSearchPeriod' => $upcoming['periodKey'] ?? '2025',
        ];

        return $this->render('admin/idees_evenement/index.html.twig', $params);
    }

    #[Route('/trouver', name: 'find', methods: ['POST'])]
    public function find(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_idees_evenement_find', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Réessayez.');
            return $this->redirectToRoute('admin_idees_evenement_index');
        }
        $themeKey = $request->request->getString('theme', '');
        $motsLibres = $request->request->getString('mots_libres', '');
        $query = trim($motsLibres) !== ''
            ? $motsLibres
            : (self::THEMES[$themeKey] ?? 'événement autisme famille inclusion');

        $result = $this->googleSearch->searchAndGetSnippets($query);
        $ideas = $this->huggingFace->suggestEventIdeasFromText($result['text']);

        $count = 0;
        foreach ($ideas as $idea) {
            $idee = new IdeeEvenement();
            $idee->setTitre($idea['titre']);
            $idee->setDescription($idea['description']);
            $idee->setTheme($idea['theme']);
            $idee->setPourquoi($idea['pourquoi']);
            $idee->setMotsCle($query);
            $this->entityManager->persist($idee);
            $count++;
        }
        if ($count > 0) {
            $this->entityManager->flush();
        }

        if ($count > 0) {
            $this->addFlash('success', $count . ' idée(s) ont été ajoutées. Vous pouvez en créer un brouillon d\'événement.');
        } else {
            $err = $this->huggingFace->getLastApiError();
            $this->addFlash('warning', $err !== null
                ? 'Aucune idée générée. ' . ($err['message'] ?? 'Erreur IA.')
                : 'Aucune idée générée. Vérifiez la clé Hugging Face (HUGGINGFACE_API_KEY) et réessayez.');
        }

        return $this->redirectToRoute('admin_idees_evenement_index');
    }

    /**
     * Recherche mondiale d'événements à venir (réels, prochaine période).
     * Nécessite GOOGLE_CSE_API_KEY et GOOGLE_CSE_CX.
     */
    #[Route('/evenements-venir', name: 'search_upcoming', methods: ['POST'])]
    public function searchUpcoming(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_idees_evenement_search_upcoming', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Réessayez.');
            $toEvents = $request->request->getString('return_to') === 'events';
            return $this->redirectToRoute($toEvents ? 'admin_evenement_index' : 'admin_idees_evenement_index', ['_fragment' => 'resultats-recherche-mondiale']);
        }
        $returnToEvents = $request->request->getString('return_to') === 'events';
        $sessionKey = $returnToEvents ? self::SESSION_UPCOMING_KEY_EVENTS : self::SESSION_UPCOMING_KEY;
        $query = trim($request->request->getString('upcoming_query', ''));
        $periodKey = $request->request->getString('upcoming_period', '2025');
        $period = self::PERIODS[$periodKey] ?? '2025';
        $session = $request->getSession();
        $redirectRoute = $returnToEvents ? 'admin_evenement_index' : 'admin_idees_evenement_index';

        if ($query === '') {
            $this->addFlash('warning', 'Saisissez des mots-clés (thème, lieu, type d\'événement) pour lancer la recherche.');
            $session->set($sessionKey, [
                'results' => [],
                'error' => 'Saisissez des mots-clés dans le champ ci-dessus (ex. Autisme, conférence Paris) puis cliquez sur « Rechercher dans le monde ».',
                'searchQuery' => '',
                'query' => '',
                'periodKey' => $periodKey,
            ]);
            return $this->redirectToRoute($redirectRoute, ['_fragment' => 'resultats-recherche-mondiale']);
        }
        $searchQuery = 'upcoming events ' . $query . ' ' . $period;
        $results = [];
        $error = null;

        if ($this->googleSearch->isConfigured()) {
            $searchResult = $this->googleSearch->searchUpcomingEvents($query, $period);
            $results = $searchResult['items'];
            $error = $searchResult['error'];
            $searchQuery = $searchResult['searchQuery'];
        } else {
            $error = 'Google non configuré';
        }

        if ($error !== null && empty($results)) {
            $fallback = $this->fallbackSearch->search($searchQuery, 15);
            if (!empty($fallback['items'])) {
                $results = $fallback['items'];
                $error = null;
            }
        }

        $session->set($sessionKey, [
            'results' => $results,
            'error' => $error,
            'searchQuery' => $searchQuery,
            'query' => $query,
            'periodKey' => $periodKey,
        ]);
        return $this->redirectToRoute($redirectRoute, ['_fragment' => 'resultats-recherche-mondiale']);
    }

    /**
     * À partir d'une recherche mondiale (mots-clés + période), récupère des extraits web puis l'IA génère des idées d'événements.
     */
    #[Route('/generer-idees-recherche', name: 'ideas_from_upcoming', methods: ['POST'])]
    public function ideasFromUpcoming(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_idees_evenement_ideas_from_upcoming', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide. Réessayez.');
            return $this->redirectToRoute('admin_evenement_index', ['_fragment' => 'recherche-avancee']);
        }
        $returnToEvents = $request->request->getString('return_to') === 'events';
        $redirectRoute = $returnToEvents ? 'admin_evenement_index' : 'admin_idees_evenement_index';
        $fragment = $returnToEvents ? 'recherche-avancee' : 'resultats-recherche-mondiale';

        if (!$this->huggingFace->hasApiKey()) {
            $this->addFlash('warning', 'L’IA n’est pas configurée. Ajoutez HUGGINGFACE_API_KEY dans .env pour générer des idées.');
            return $this->redirectToRoute($redirectRoute, ['_fragment' => $fragment]);
        }

        $query = trim($request->request->getString('upcoming_query', ''));
        $periodKey = $request->request->getString('upcoming_period', '2025');
        $period = self::PERIODS[$periodKey] ?? '2025';
        $searchQuery = $query !== '' ? 'upcoming events ' . $query . ' ' . $period : 'upcoming events autism inclusion 2025';

        $result = $this->googleSearch->searchAndGetSnippets($searchQuery);
        $text = $result['text'] ?? '';
        if ($text === '') {
            $this->addFlash('warning', 'Aucun extrait trouvé pour cette recherche. Saisissez des mots-clés puis relancez la recherche avant de générer des idées.');
            return $this->redirectToRoute($redirectRoute, ['_fragment' => $fragment]);
        }

        $ideas = $this->huggingFace->suggestEventIdeasFromText($text, $query !== '' ? $query : null);
        if ($query !== '' && $ideas !== []) {
            $queryWords = array_filter(preg_split('/\s+/u', mb_strtolower($query), -1, \PREG_SPLIT_NO_EMPTY));
            $queryWords = array_values(array_filter($queryWords, static fn (string $w): bool => mb_strlen($w) >= 2));
            if ($queryWords !== []) {
                $filtered = array_values(array_filter($ideas, static function (array $idea) use ($queryWords): bool {
                    $titre = mb_strtolower($idea['titre'] ?? '');
                    $desc = mb_strtolower($idea['description'] ?? '');
                    $texte = $titre . ' ' . $desc;
                    foreach ($queryWords as $word) {
                        if (mb_strpos($texte, $word) !== false) {
                            return true;
                        }
                    }
                    return false;
                }));
                if ($filtered !== []) {
                    $ideas = $filtered;
                }
            }
        }
        $created = [];
        foreach ($ideas as $idea) {
            $idee = new IdeeEvenement();
            $idee->setTitre($idea['titre'] ?? 'Sans titre');
            $idee->setDescription($idea['description'] ?? '');
            $idee->setTheme($idea['theme'] ?? 'Autre');
            $idee->setPourquoi($idea['pourquoi'] ?? '');
            $idee->setMotsCle($query !== '' ? $query : $searchQuery);
            $this->entityManager->persist($idee);
            $created[] = $idee;
        }
        if ($created !== []) {
            $this->entityManager->flush();
            $session = $request->getSession();
            $session->set(self::SESSION_LAST_GENERATED_IDS, array_map(static fn (IdeeEvenement $e) => $e->getId(), $created));
            $count = \count($created);
            if ($returnToEvents) {
                $this->addFlash('success_idees', $count);
            } else {
                $this->addFlash('success', $count . ' idée(s) générée(s) par l’IA. Vous pouvez les consulter dans Idées ou en créer un brouillon d’événement.');
            }
        } else {
            $err = $this->huggingFace->getLastApiError();
            $this->addFlash('warning', $err !== null
                ? 'Aucune idée générée. ' . ($err['message'] ?? 'Erreur IA.')
                : 'Aucune idée générée. Réessayez avec d’autres mots-clés.');
        }

        return $this->redirectToRoute($redirectRoute, ['_fragment' => $fragment]);
    }

    #[Route('/creer-brouillon/{id}', name: 'creer_brouillon', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function creerBrouillon(int $id, Request $request): Response
    {
        $idee = $this->ideeRepository->find($id);
        if ($idee === null) {
            $this->addFlash('error', 'Idée introuvable.');
            return $this->redirectToRoute('admin_idees_evenement_index');
        }

        $request->getSession()->set('idee_evenement_prefill', [
            'titre' => $idee->getTitre(),
            'description' => $idee->getDescription(),
            'theme' => $idee->getTheme(),
        ]);

        return $this->redirectToRoute('admin_evenement_new');
    }
}
