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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/admin/idees-evenements', name: 'admin_idees_evenement_')]
#[IsGranted('ROLE_ADMIN')]
final class IdeesEvenementController extends AbstractController
{
    private const THEMES = [
        'Sensoriel' => 'atelier sensoriel autisme famille',
        'Inclusion' => '├®v├®nement inclusion handicap famille',
        'Familles' => 'activit├® familles enfants handicap',
        'Ateliers' => 'atelier cr├®atif adapt├® autisme',
        'Sensibilisation' => 'sensibilisation autisme ├®cole inclusion',
    ];

    private const CACHE_TTL_SEARCH_SECONDS = 300;

    public function __construct(
        private readonly IdeeEvenementRepository $ideeRepository,
        private readonly GoogleCustomSearchService $googleSearch,
        private readonly FallbackSearchService $fallbackSearch,
        private readonly HuggingFaceService $huggingFace,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
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
    private const SESSION_LAST_ANALYSE = 'idees_last_analyse';

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
        usort($idees, static function (IdeeEvenement $a, IdeeEvenement $b): int {
            $sa = $a->getScore() ?? -1;
            $sb = $b->getScore() ?? -1;
            return $sb <=> $sa;
        });
        $upcoming = $session->get(self::SESSION_UPCOMING_KEY);
        $session->remove(self::SESSION_UPCOMING_KEY);
        $lastAnalyse = $session->get(self::SESSION_LAST_ANALYSE);

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
            'lastAnalyse' => $lastAnalyse,
        ];

        return $this->render('admin/idees_evenement/index.html.twig', $params);
    }

    #[Route('/trouver', name: 'find', methods: ['POST'])]
    public function find(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_idees_evenement_find', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de s├®curit├® invalide. R├®essayez.');
            return $this->redirectToRoute('admin_idees_evenement_index');
        }
        $themeKey = $request->request->getString('theme', '');
        $motsLibres = $request->request->getString('mots_libres', '');
        $query = trim($motsLibres) !== ''
            ? $motsLibres
            : (self::THEMES[$themeKey] ?? '├®v├®nement autisme famille inclusion');

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
            $this->addFlash('success', $count . ' id├®e(s) ont ├®t├® ajout├®es. Vous pouvez en cr├®er un brouillon d\'├®v├®nement.');
        } else {
            $err = $this->huggingFace->getLastApiError();
            $this->addFlash('warning', $err !== null
                ? 'Aucune id├®e g├®n├®r├®e. ' . ($err['message'] ?? 'Erreur IA.')
                : 'Aucune id├®e g├®n├®r├®e. V├®rifiez la cl├® Hugging Face (HUGGINGFACE_API_KEY) et r├®essayez.');
        }

        return $this->redirectToRoute('admin_idees_evenement_index');
    }

    /**
     * Recherche mondiale d'├®v├®nements ├á venir (r├®els, prochaine p├®riode).
     * N├®cessite GOOGLE_CSE_API_KEY et GOOGLE_CSE_CX.
     */
    #[Route('/evenements-venir', name: 'search_upcoming', methods: ['POST'])]
    public function searchUpcoming(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_idees_evenement_search_upcoming', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de s├®curit├® invalide. R├®essayez.');
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
            $this->addFlash('warning', 'Saisissez des mots-cl├®s (th├¿me, lieu, type d\'├®v├®nement) pour lancer la recherche.');
            $session->set($sessionKey, [
                'results' => [],
                'error' => 'Saisissez des mots-cl├®s dans le champ ci-dessus (ex. Autisme, conf├®rence Paris) puis cliquez sur ┬½ Rechercher dans le monde ┬╗.',
                'searchQuery' => '',
                'query' => '',
                'periodKey' => $periodKey,
            ]);
            return $this->redirectToRoute($redirectRoute, ['_fragment' => 'resultats-recherche-mondiale']);
        }

        $cacheKey = 'idees_search_' . md5($query . '|' . $periodKey);
        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($query, $period): array {
            $item->expiresAfter(self::CACHE_TTL_SEARCH_SECONDS);
            $searchQuery = 'upcoming events ' . $query . ' ' . $period;
            $results = [];
            $error = null;
            if ($this->googleSearch->isConfigured()) {
                $searchResult = $this->googleSearch->searchUpcomingEvents($query, $period);
                $results = $searchResult['items'];
                $error = $searchResult['error'];
                $searchQuery = $searchResult['searchQuery'];
            } else {
                $error = 'Google non configur├®';
            }
            if ($error !== null && empty($results)) {
                $fallback = $this->fallbackSearch->search($searchQuery, 15);
                if (!empty($fallback['items'])) {
                    $results = $fallback['items'];
                    $error = null;
                }
            }
            return ['results' => $results, 'error' => $error, 'searchQuery' => $searchQuery];
        });

        if (empty($data['results'])) {
            $this->cache->delete($cacheKey);
        }

        $session->set($sessionKey, [
            'results' => $data['results'],
            'error' => $data['error'],
            'searchQuery' => $data['searchQuery'],
            'query' => $query,
            'periodKey' => $periodKey,
        ]);
        return $this->redirectToRoute($redirectRoute, ['_fragment' => 'resultats-recherche-mondiale']);
    }

    /**
     * ├Ç partir d'une recherche mondiale (mots-cl├®s + p├®riode), r├®cup├¿re des extraits web puis l'IA g├®n├¿re des id├®es d'├®v├®nements.
     */
    #[Route('/generer-idees-recherche', name: 'ideas_from_upcoming', methods: ['POST'])]
    public function ideasFromUpcoming(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_idees_evenement_ideas_from_upcoming', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de s├®curit├® invalide. R├®essayez.');
            return $this->redirectToRoute('admin_evenement_index', ['_fragment' => 'recherche-avancee']);
        }
        $returnToEvents = $request->request->getString('return_to') === 'events';
        $redirectRoute = $returnToEvents ? 'admin_evenement_index' : 'admin_idees_evenement_index';
        $fragment = $returnToEvents ? 'recherche-avancee' : 'resultats-recherche-mondiale';

        if (!$this->huggingFace->hasApiKey()) {
            $this->addFlash('warning', 'LÔÇÖIA nÔÇÖest pas configur├®e. Ajoutez HUGGINGFACE_API_KEY dans .env pour g├®n├®rer des id├®es.');
            return $this->redirectToRoute($redirectRoute, ['_fragment' => $fragment]);
        }

        $query = trim($request->request->getString('upcoming_query', ''));
        $periodKey = $request->request->getString('upcoming_period', '2025');
        $period = self::PERIODS[$periodKey] ?? '2025';
        $searchQuery = $query !== '' ? 'upcoming events ' . $query . ' ' . $period : 'upcoming events autism inclusion 2025';

        $result = $this->googleSearch->searchAndGetSnippets($searchQuery);
        $text = $result['text'] ?? '';
        if ($text === '') {
            $this->addFlash('warning', 'Aucun extrait trouv├® pour cette recherche. Saisissez des mots-cl├®s puis relancez la recherche avant de g├®n├®rer des id├®es.');
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
                $this->addFlash('success', $count . ' id├®e(s) g├®n├®r├®e(s) par lÔÇÖIA. Vous pouvez les consulter dans Id├®es ou en cr├®er un brouillon dÔÇÖ├®v├®nement.');
            }
        } else {
            $err = $this->huggingFace->getLastApiError();
            $this->addFlash('warning', $err !== null
                ? 'Aucune id├®e g├®n├®r├®e. ' . ($err['message'] ?? 'Erreur IA.')
                : 'Aucune id├®e g├®n├®r├®e. R├®essayez avec dÔÇÖautres mots-cl├®s.');
        }

        return $this->redirectToRoute($redirectRoute, ['_fragment' => $fragment]);
    }

    /**
     * Analyse les r├®sultats de recherche affich├®s (envoy├®s en POST) et propose des id├®es avec pourquoi + score.
     * POST: _token, return_to, upcoming_query, upcoming_period, results_json (JSON array of {name, snippet}).
     */
    #[Route('/analyser-recherche-et-proposer', name: 'analyze_and_propose', methods: ['POST'])]
    public function analyzeAndPropose(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_idees_evenement_analyze_and_propose', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de s├®curit├® invalide. R├®essayez.');
            return $this->redirectToRoute('admin_idees_evenement_index', ['_fragment' => 'resultats-recherche-mondiale']);
        }

        // Toujours rediriger vers la page Id├®es apr├¿s ┬½ G├®n├®rer ┬╗, pour afficher le r├®sultat ou lÔÇÖerreur au bon endroit
        $redirectToIdees = fn (string $fragment = 'resultats-recherche-mondiale') => $this->redirectToRoute('admin_idees_evenement_index', ['_fragment' => $fragment]);

        if (!$this->huggingFace->hasApiKey()) {
            $this->addFlash('warning', 'LÔÇÖIA nÔÇÖest pas configur├®e. Ajoutez HUGGINGFACE_API_KEY dans .env.');
            return $redirectToIdees();
        }

        $query = trim($request->request->getString('upcoming_query', ''));
        $periodKey = $request->request->getString('upcoming_period', '2025');
        $period = self::PERIODS[$periodKey] ?? '2025';
        $resultsJson = $request->request->getString('results_json', '');
        $results = [];
        if ($resultsJson !== '') {
            try {
                $decoded = json_decode($resultsJson, true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($decoded)) {
                    $results = $decoded;
                }
            } catch (\JsonException) {
                // ignore
            }
        }

        if ($results !== []) {
            $searchResultsText = '';
            foreach ($results as $item) {
                $name = isset($item['name']) && \is_string($item['name']) ? trim($item['name']) : '';
                $snippet = isset($item['snippet']) && \is_string($item['snippet']) ? trim($item['snippet']) : '';
                if ($name !== '' || $snippet !== '') {
                    $searchResultsText .= "Titre : " . $name . "\nR├®sum├® : " . $snippet . "\n---\n";
                }
            }
            if ($searchResultsText === '') {
                $this->addFlash('warning', 'Les r├®sultats ne contiennent pas de texte ├á analyser.');
                return $redirectToIdees();
            }
            $out = $this->huggingFace->analyzeSearchResultsAndSuggestEvents($searchResultsText, $query !== '' ? $query : 'recherche', $periodKey);
        } else {
            if ($query === '') {
                $this->addFlash('warning', 'Saisissez des mots-cl├®s pour que lÔÇÖIA propose des id├®es (ex. violence, ├®ducation Tunisie).');
                return $redirectToIdees();
            }
            $out = $this->huggingFace->suggestEventIdeasFromQueryWithAnalysis($query, $periodKey);
        }
        if ($out === [] || ($out['propositions'] ?? []) === []) {
            $err = $this->huggingFace->getLastApiError();
            $this->addFlash('warning', $err !== null
                ? 'LÔÇÖIA nÔÇÖa pas pu analyser. ' . ($err['message'] ?? '')
                : 'Aucune proposition g├®n├®r├®e. R├®essayez avec dÔÇÖautres r├®sultats.');
            return $redirectToIdees();
        }

        $created = [];
        foreach ($out['propositions'] as $prop) {
            $idee = new IdeeEvenement();
            $idee->setTitre($prop['titre']);
            $idee->setDescription($prop['description'] ?? '');
            $idee->setTheme($prop['theme'] ?? '');
            $idee->setPourquoi($prop['pourquoi'] ?? '');
            $idee->setScore(\array_key_exists('score', $prop) ? (int) $prop['score'] : null);
            $idee->setMotsCle($query !== '' ? $query : null);
            $this->entityManager->persist($idee);
            $created[] = $idee;
        }
        $this->entityManager->flush();

        $session = $request->getSession();
        $session->set(self::SESSION_LAST_GENERATED_IDS, array_map(static fn (IdeeEvenement $e) => $e->getId(), $created));
        $session->set(self::SESSION_LAST_ANALYSE, $out['analyse'] ?? '');

        $this->addFlash('success', 'Cr├®├® avec succ├¿s.');

        return $this->redirectToRoute('admin_idees_evenement_index', ['_fragment' => 'resultats-analyse']);
    }

    #[Route('/creer-brouillon/{id}', name: 'creer_brouillon', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function creerBrouillon(int $id, Request $request): Response
    {
        $idee = $this->ideeRepository->find($id);
        if ($idee === null) {
            $this->addFlash('error', 'Id├®e introuvable.');
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
