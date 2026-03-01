<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Produit;
use App\Entity\User;
use App\Enum\Categorie;
use App\Repository\CommandeRepository;
use App\Repository\EvenementRepository;
use App\Repository\InscritEventsRepository;
use App\Repository\ProduitRepository;
use App\Repository\ThematiqueRepository;

/**
 * Fournit les suggestions pour la bande du layout utilisateur :
 * - Produits des catégories achetées par l'utilisateur
 * - Événements des thématiques pour lesquelles l'utilisateur s'est inscrit
 */
final class LayoutSuggestionsService
{
    private const MAX_PRODUITS = 10;
    private const MAX_EVENEMENTS = 10;

    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly CommandeRepository $commandeRepository,
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly ThematiqueRepository $thematiqueRepository,
    ) {
    }

    /**
     * @return array{
     *   productSuggestions: list<Produit>,
     *   eventSuggestions: list<Evenement>,
     *   doctorSuggestions: list<object>
     * }
     */
    public function getSuggestionsForUser(User $user): array
    {
        $productCategories = $this->getPurchasedCategories($user);
        $thematiqueIds = $this->getRegisteredThematiqueIds($user);

        $products = $productCategories !== []
            ? $this->findProductsByCategories($productCategories, $user)
            : [];
        $events = $thematiqueIds !== []
            ? $this->findEventsByThematiques($thematiqueIds, $user)
            : [];

        return [
            'productSuggestions' => $products,
            'eventSuggestions' => $events,
            'doctorSuggestions' => [], // Pas de médecins dans les préférences utilisateur demandées
        ];
    }

    /**
     * @return list<string> Catégories (enum value) des produits achetés
     */
    private function getPurchasedCategories(User $user): array
    {
        $categories = [];
        foreach ($this->commandeRepository->findByUserOrderedByDate($user) as $commande) {
            if ($commande->getStatut() === 'annulee') {
                continue;
            }
            foreach ($commande->getLignes() as $ligne) {
                $categorie = $ligne->getProduit()?->getCategorie()?->value;
                if (is_string($categorie) && $categorie !== '' && !isset($categories[$categorie])) {
                    $categories[$categorie] = true;
                }
            }
        }
        return array_keys($categories);
    }

    /**
     * @return list<int> IDs des thématiques des événements auxquels l'utilisateur s'est inscrit
     */
    private function getRegisteredThematiqueIds(User $user): array
    {
        $ids = [];
        foreach ($this->inscritEventsRepository->findAccepteRefuseOuEnAttenteForUser($user) as $inscription) {
            if (!in_array($inscription->getStatut(), ['accepte', 'en_attente'], true)) {
                continue;
            }
            $themeId = $inscription->getEvenement()?->getThematique()?->getId();
            if (is_int($themeId) && $themeId > 0 && !isset($ids[$themeId])) {
                $ids[$themeId] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * @param list<string> $categories
     * @return list<Produit>
     */
    private function findProductsByCategories(array $categories, User $user): array
    {
        $cases = array_values(array_filter(
            array_map(static fn (string $v): ?Categorie => Categorie::tryFrom($v), $categories),
            static fn (?Categorie $c): bool => $c !== null
        ));
        if ($cases === []) {
            return [];
        }
        $purchasedIds = $this->getPurchasedProductIds($user);
        $qb = $this->produitRepository->createQueryBuilder('p')
            ->andWhere('p.disponibilite = :dispo')
            ->setParameter('dispo', true)
            ->andWhere('p.categorie IN (:categories)')
            ->setParameter('categories', $cases)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(self::MAX_PRODUITS * 2); // Buffer pour exclure les déjà achetés

        $results = $qb->getQuery()->getResult();
        $filtered = array_values(array_filter(
            $results,
            static fn (Produit $p): bool => !isset($purchasedIds[$p->getId() ?? -1])
        ));

        return array_slice($filtered, 0, self::MAX_PRODUITS);
    }

    /**
     * @param list<int> $thematiqueIds
     * @return list<Evenement>
     */
    private function findEventsByThematiques(array $thematiqueIds, User $user): array
    {
        $registeredIds = $this->getRegisteredEventIds($user);
        $thematiques = $this->thematiqueRepository->findBy(['id' => $thematiqueIds]);
        if ($thematiques === []) {
            return [];
        }
        $today = new \DateTimeImmutable('today');
        $qb = $this->evenementRepository->createQueryBuilder('e')
            ->andWhere('e.dateEvent >= :today')
            ->setParameter('today', $today)
            ->andWhere('e.thematique IN (:thematiques)')
            ->setParameter('thematiques', $thematiques)
            ->orderBy('e.dateEvent', 'ASC')
            ->addOrderBy('e.heureDebut', 'ASC')
            ->setMaxResults(self::MAX_EVENEMENTS * 2);

        $results = $qb->getQuery()->getResult();
        $filtered = array_values(array_filter(
            $results,
            static fn (Evenement $e): bool => !isset($registeredIds[$e->getId() ?? -1])
        ));

        return array_slice($filtered, 0, self::MAX_EVENEMENTS);
    }

    /** @return array<int, true> */
    private function getPurchasedProductIds(User $user): array
    {
        $ids = [];
        foreach ($this->commandeRepository->findByUserOrderedByDate($user) as $commande) {
            if ($commande->getStatut() === 'annulee') {
                continue;
            }
            foreach ($commande->getLignes() as $ligne) {
                $id = $ligne->getProduit()?->getId();
                if (is_int($id)) {
                    $ids[$id] = true;
                }
            }
        }
        return $ids;
    }

    /** @return array<int, true> */
    private function getRegisteredEventIds(User $user): array
    {
        $ids = [];
        foreach ($this->inscritEventsRepository->findAccepteRefuseOuEnAttenteForUser($user) as $inscription) {
            if (!in_array($inscription->getStatut(), ['accepte', 'en_attente'], true)) {
                continue;
            }
            $id = $inscription->getEvenement()?->getId();
            if (is_int($id)) {
                $ids[$id] = true;
            }
        }
        return $ids;
    }
}
