<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Medcin;
use App\Entity\Produit;
use App\Entity\User;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Repository\InscritEventsRepository;
use App\Repository\MedcinRepository;
use App\Repository\ProduitRepository;
use App\Repository\RendezVousRepository;
use App\Repository\UserHistoryRepository;
use App\Repository\UserPreferenceRepository;

final class RecommendationService
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly MedcinRepository $medcinRepository,
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly CommandeRepository $commandeRepository,
        private readonly UserHistoryRepository $userHistoryRepository,
        private readonly UserPreferenceRepository $userPreferenceRepository,
        private readonly ExternalAiRecommendationService $externalAiRecommendationService,
    ) {
    }

    /**
     * @return array{
     *   productSuggestions:list<Produit>,
     *   eventSuggestions:list<Evenement>,
     *   doctorSuggestions:list<Medcin>,
     *   reasons:list<string>,
     *   source:string
     * }
     */
    public function getSuggestions(User $user): array
    {
        $productCandidates = $this->findProductCandidates(30);
        $eventCandidates = $this->findEventCandidates(30);
        $doctorCandidates = $this->findDoctorCandidates(30);
        $productCandidates = $this->excludePurchasedProducts($user, $productCandidates);
        $eventCandidates = $this->excludeRegisteredEvents($user, $eventCandidates);
        $history = $this->formatHistory($user);
        $topPreferenceSignals = $this->formatTopPreferenceSignals($user);
        $interestSummary = $this->buildInterestSummary($history);
        $purchaseSummary = $this->buildPurchaseSummary($user);
        $eventRegistrationSummary = $this->buildEventRegistrationSummary($user);
        $focusSignals = $this->buildFocusSignals($topPreferenceSignals, $purchaseSummary, $eventRegistrationSummary);
        $productCandidates = $this->focusProductCandidates($productCandidates, $focusSignals['product_categories']);
        $eventCandidates = $this->focusEventCandidates($eventCandidates, $focusSignals['event_theme_ids']);
        $doctorCandidates = $this->focusDoctorCandidates($doctorCandidates, $focusSignals['doctor_specialities']);

        $aiResult = $this->externalAiRecommendationService->rank(
            [
                'user_id' => $user->getId(),
                'user_role' => $user->getRole()?->value,
                'history' => $history,
                'top_preference_signals' => $topPreferenceSignals,
                'interest_summary' => $interestSummary,
                'purchase_summary' => $purchaseSummary,
                'event_registration_summary' => $eventRegistrationSummary,
                'focus_signals' => $focusSignals,
            ],
            $productCandidates,
            $eventCandidates,
            $doctorCandidates
        );

        if (!is_array($aiResult)) {
            return [
                'productSuggestions' => [],
                'eventSuggestions' => [],
                'doctorSuggestions' => [],
                'reasons' => ['IA externe indisponible pour le moment.'],
                'source' => 'ai_unavailable',
            ];
        }

        return [
            'productSuggestions' => $this->pickByIds($productCandidates, $aiResult['product_ids'], 3),
            'eventSuggestions' => $this->pickByIds($eventCandidates, $aiResult['event_ids'], 3),
            'doctorSuggestions' => $this->pickByIds($doctorCandidates, $aiResult['doctor_ids'], 3),
            'reasons' => $aiResult['reasons'] !== [] ? array_slice($aiResult['reasons'], 0, 4) : ['Suggestions générées par IA externe.'],
            'source' => 'external_ai',
        ];
    }

    /**
     * @return list<Produit>
     */
    private function findProductCandidates(int $limit): array
    {
        return $this->produitRepository->createQueryBuilder('p')
            ->andWhere('p.disponibilite = :dispo')
            ->setParameter('dispo', true)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Evenement>
     */
    private function findEventCandidates(int $limit): array
    {
        $today = new \DateTimeImmutable('today');
        return $this->evenementRepository->createQueryBuilder('e')
            ->andWhere('e.dateEvent >= :today')
            ->setParameter('today', $today)
            ->orderBy('e.dateEvent', 'ASC')
            ->addOrderBy('e.heureDebut', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Medcin>
     */
    private function findDoctorCandidates(int $limit): array
    {
        return array_slice($this->medcinRepository->findAllOrderByNom(), 0, $limit);
    }

    /**
     * @return list<array{action:string,item_type:string,item_id:int|null,metadata:array<string,mixed>|null,created_at:string|null}>
     */
    private function formatHistory(User $user): array
    {
        $rows = $this->userHistoryRepository->findRecentForUser($user, 80);
        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'action' => (string) $row->getAction(),
                'item_type' => (string) $row->getItemType(),
                'item_id' => $row->getItemId(),
                'metadata' => $row->getMetadata(),
                'created_at' => $row->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $history;
    }

    /**
     * @return list<array{signal:string,weight:int}>
     */
    private function formatTopPreferenceSignals(User $user): array
    {
        $rows = $this->userPreferenceRepository->findTopForUser($user, 12);
        $signals = [];
        foreach ($rows as $row) {
            $signals[] = [
                'signal' => (string) $row->getCategory(),
                'weight' => (int) $row->getWeight(),
            ];
        }

        return $signals;
    }

    /**
     * @param list<array{action:string,item_type:string,item_id:int|null,metadata:array<string,mixed>|null,created_at:string|null}> $history
     * @return array{
     *   action_counts:array<string,int>,
     *   item_type_counts:array<string,int>,
     *   top_item_ids:array<string,list<int>>
     * }
     */
    private function buildInterestSummary(array $history): array
    {
        $actionCounts = [];
        $itemTypeCounts = [];
        $itemCounters = [];

        foreach ($history as $row) {
            $action = $row['action'] !== '' ? $row['action'] : 'unknown';
            $itemType = $row['item_type'] !== '' ? $row['item_type'] : 'unknown';
            $itemId = $row['item_id'];

            $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
            $itemTypeCounts[$itemType] = ($itemTypeCounts[$itemType] ?? 0) + 1;

            if (is_int($itemId)) {
                if (!isset($itemCounters[$itemType])) {
                    $itemCounters[$itemType] = [];
                }
                $itemCounters[$itemType][$itemId] = ($itemCounters[$itemType][$itemId] ?? 0) + 1;
            }
        }

        $topItemIds = [];
        foreach ($itemCounters as $itemType => $counters) {
            arsort($counters);
            $topItemIds[$itemType] = array_map('intval', array_slice(array_keys($counters), 0, 5));
        }

        return [
            'action_counts' => $actionCounts,
            'item_type_counts' => $itemTypeCounts,
            'top_item_ids' => $topItemIds,
        ];
    }

    /**
     * @return array{
     *   purchased_product_ids:list<int>,
     *   purchased_category_counts:array<string,int>
     * }
     */
    private function buildPurchaseSummary(User $user): array
    {
        $purchasedProductIds = [];
        $purchasedCategoryCounts = [];

        foreach ($this->commandeRepository->findByUserOrderedByDate($user) as $commande) {
            if ($commande->getStatut() === 'annulee') {
                continue;
            }
            foreach ($commande->getLignes() as $ligne) {
                $produit = $ligne->getProduit();
                $productId = $produit?->getId();
                if (is_int($productId)) {
                    $purchasedProductIds[$productId] = true;
                }
                $categorie = $produit?->getCategorie()?->value;
                if (is_string($categorie) && $categorie !== '') {
                    $purchasedCategoryCounts[$categorie] = ($purchasedCategoryCounts[$categorie] ?? 0) + max(1, (int) $ligne->getQuantite());
                }
            }
        }

        arsort($purchasedCategoryCounts);

        return [
            'purchased_product_ids' => array_map('intval', array_keys($purchasedProductIds)),
            'purchased_category_counts' => $purchasedCategoryCounts,
        ];
    }

    /**
     * @return array{
     *   registered_event_ids:list<int>,
     *   registered_theme_counts:array<int,int>
     * }
     */
    private function buildEventRegistrationSummary(User $user): array
    {
        $registeredEventIds = [];
        $registeredThemeCounts = [];

        foreach ($this->inscritEventsRepository->findAccepteRefuseOuEnAttenteForUser($user) as $inscription) {
            if (!in_array($inscription->getStatut(), ['accepte', 'en_attente'], true)) {
                continue;
            }
            $event = $inscription->getEvenement();
            $eventId = $event?->getId();
            if (is_int($eventId)) {
                $registeredEventIds[$eventId] = true;
            }
            $themeId = $event?->getThematique()?->getId();
            if (is_int($themeId)) {
                $registeredThemeCounts[$themeId] = ($registeredThemeCounts[$themeId] ?? 0) + 1;
            }
        }

        arsort($registeredThemeCounts);

        return [
            'registered_event_ids' => array_map('intval', array_keys($registeredEventIds)),
            'registered_theme_counts' => $registeredThemeCounts,
        ];
    }

    /**
     * @param list<array{signal:string,weight:int}> $topPreferenceSignals
     * @param array{purchased_product_ids:list<int>,purchased_category_counts:array<string,int>} $purchaseSummary
     * @param array{registered_event_ids:list<int>,registered_theme_counts:array<int,int>} $eventRegistrationSummary
     * @return array{
     *   product_categories:list<string>,
     *   event_theme_ids:list<int>,
     *   doctor_specialities:list<string>
     * }
     */
    private function buildFocusSignals(
        array $topPreferenceSignals,
        array $purchaseSummary,
        array $eventRegistrationSummary
    ): array {
        $productCategories = [];
        $eventThemeIds = [];
        $doctorSpecialities = [];

        foreach (array_keys($purchaseSummary['purchased_category_counts']) as $category) {
            if (is_string($category) && $category !== '') {
                $productCategories[$category] = true;
            }
        }
        foreach (array_keys($eventRegistrationSummary['registered_theme_counts']) as $themeId) {
            $id = (int) $themeId;
            if ($id > 0) {
                $eventThemeIds[$id] = true;
            }
        }

        foreach ($topPreferenceSignals as $signal) {
            $value = $signal['signal'];
            if (str_starts_with($value, 'product_category:')) {
                $productCategories[substr($value, strlen('product_category:'))] = true;
            } elseif (str_starts_with($value, 'event_theme:')) {
                $id = (int) substr($value, strlen('event_theme:'));
                if ($id > 0) {
                    $eventThemeIds[$id] = true;
                }
            } elseif (str_starts_with($value, 'doctor_speciality:')) {
                $spec = trim(substr($value, strlen('doctor_speciality:')));
                if ($spec !== '') {
                    $doctorSpecialities[mb_strtolower($spec)] = true;
                }
            }
        }

        return [
            'product_categories' => array_values(array_keys($productCategories)),
            'event_theme_ids' => array_map('intval', array_values(array_keys($eventThemeIds))),
            'doctor_specialities' => array_values(array_keys($doctorSpecialities)),
        ];
    }

    /**
     * @param list<Produit> $candidates
     * @param list<string> $categories
     * @return list<Produit>
     */
    private function focusProductCandidates(array $candidates, array $categories): array
    {
        if ($categories === []) {
            return $candidates;
        }

        $categorySet = array_fill_keys($categories, true);
        $focused = array_values(array_filter(
            $candidates,
            static fn (Produit $produit): bool => isset($categorySet[$produit->getCategorie()?->value ?? ''])
        ));

        return $focused !== [] ? $focused : $candidates;
    }

    /**
     * @param list<Evenement> $candidates
     * @param list<int> $themeIds
     * @return list<Evenement>
     */
    private function focusEventCandidates(array $candidates, array $themeIds): array
    {
        if ($themeIds === []) {
            return $candidates;
        }

        $themeSet = array_fill_keys($themeIds, true);
        $focused = array_values(array_filter(
            $candidates,
            static fn (Evenement $event): bool => isset($themeSet[$event->getThematique()?->getId() ?? -1])
        ));

        return $focused !== [] ? $focused : $candidates;
    }

    /**
     * @param list<Medcin> $candidates
     * @param list<string> $specialities
     * @return list<Medcin>
     */
    private function focusDoctorCandidates(array $candidates, array $specialities): array
    {
        if ($specialities === []) {
            return $candidates;
        }

        $focused = array_values(array_filter($candidates, static function (Medcin $doctor) use ($specialities): bool {
            $specialite = mb_strtolower(trim((string) $doctor->getSpecialite()));
            if ($specialite === '') {
                return false;
            }
            foreach ($specialities as $wanted) {
                if ($wanted !== '' && str_contains($specialite, $wanted)) {
                    return true;
                }
            }
            return false;
        }));

        return $focused !== [] ? $focused : $candidates;
    }

    /**
     * @param list<Produit> $candidates
     * @return list<Produit>
     */
    private function excludePurchasedProducts(User $user, array $candidates): array
    {
        $purchasedProductIds = [];
        foreach ($this->commandeRepository->findByUserOrderedByDate($user) as $commande) {
            if ($commande->getStatut() === 'annulee') {
                continue;
            }
            foreach ($commande->getLignes() as $ligne) {
                $productId = $ligne->getProduit()?->getId();
                if (is_int($productId)) {
                    $purchasedProductIds[$productId] = true;
                }
            }
        }

        if ($purchasedProductIds === []) {
            return $candidates;
        }

        return array_values(array_filter(
            $candidates,
            static fn (Produit $produit): bool => !isset($purchasedProductIds[$produit->getId() ?? -1])
        ));
    }

    /**
     * @param list<Evenement> $candidates
     * @return list<Evenement>
     */
    private function excludeRegisteredEvents(User $user, array $candidates): array
    {
        $registeredEventIds = [];
        foreach ($this->inscritEventsRepository->findAccepteRefuseOuEnAttenteForUser($user) as $inscription) {
            if (!in_array($inscription->getStatut(), ['accepte', 'en_attente'], true)) {
                continue;
            }
            $eventId = $inscription->getEvenement()?->getId();
            if (is_int($eventId)) {
                $registeredEventIds[$eventId] = true;
            }
        }

        if ($registeredEventIds === []) {
            return $candidates;
        }

        return array_values(array_filter(
            $candidates,
            static fn (Evenement $event): bool => !isset($registeredEventIds[$event->getId() ?? -1])
        ));
    }

    /**
     * @template T of object
     * @param list<T> $entities
     * @param list<int> $ids
     * @return list<T>
     */
    private function pickByIds(array $entities, array $ids, int $limit): array
    {
        $byId = [];
        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getId')) {
                continue;
            }
            $id = $entity->getId();
            if (is_int($id)) {
                $byId[$id] = $entity;
            }
        }

        $selected = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $selected[] = $byId[$id];
            }
            if (count($selected) >= $limit) {
                return $selected;
            }
        }
        return $selected;
    }
}

