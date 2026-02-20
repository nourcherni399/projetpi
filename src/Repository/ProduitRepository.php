<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Produit;
use App\Enum\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * Métier avancé : suggère des produits à partir d'un besoin décrit en langage naturel.
     * Ne se limite pas aux catégories : recherche par sens (mots-clés généraux), texte dans nom/description,
     * et propose une sélection variée si la requête est générale ou sans résultat.
     *
     * @return Produit[]
     */
    public function suggestByNeed(string $need): array
    {
        $need = trim($need);
        if ($need === '') {
            return $this->findBy(['disponibilite' => true], ['nom' => 'ASC'], 12);
        }

        $text = mb_strtolower($need);
        $words = array_unique(array_filter(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY), fn ($w) => mb_strlen($w) >= 2));

        // 1) Requête générale : catégories déduites du sens + recherche texte dans nom/description
        $categoriesToSearch = $this->mapKeywordsToCategories($words, $need);
        $produits = $this->findByNeedWithCategoriesAndWords($categoriesToSearch, $words);

        // 2) Si aucun résultat, recherche plus large : uniquement par mots dans nom/description
        if (count($produits) === 0 && count($words) > 0) {
            $produits = $this->findByWordsOnly($words);
        }

        // 3) Si toujours rien (phrase très générale), proposition variée non liée à une catégorie
        if (count($produits) === 0) {
            $produits = $this->getSuggestionsGenerales();
        }

        return $produits;
    }

    /**
     * Recherche par catégories (sens) OU par mots dans nom/description.
     *
     * @param Categorie[] $categoriesToSearch
     * @param string[]    $words
     * @return Produit[]
     */
    private function findByNeedWithCategoriesAndWords(array $categoriesToSearch, array $words): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.disponibilite = :dispo')
            ->setParameter('dispo', true);

        $orParts = [];
        if (count($categoriesToSearch) > 0) {
            $orParts[] = 'p.categorie IN (:cats)';
        }
        foreach ($words as $i => $w) {
            $k = 'w' . $i;
            $orParts[] = '(LOWER(p.nom) LIKE :' . $k . ' OR LOWER(p.description) LIKE :' . $k . ')';
        }
        if (count($orParts) === 0) {
            return [];
        }
        $qb->andWhere($qb->expr()->orX(...$orParts));
        if (count($categoriesToSearch) > 0) {
            $qb->setParameter('cats', $categoriesToSearch);
        }
        foreach ($words as $i => $w) {
            $qb->setParameter('w' . $i, '%' . $w . '%');
        }
        $qb->orderBy('p.nom', 'ASC')->setMaxResults(12);
        return $qb->getQuery()->getResult();
    }

    /** Recherche uniquement par mots dans nom/description (sans filtre catégorie). @return Produit[] */
    private function findByWordsOnly(array $words): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.disponibilite = :dispo')
            ->setParameter('dispo', true);
        $orParts = [];
        foreach ($words as $i => $w) {
            $k = 'w' . $i;
            $orParts[] = '(LOWER(p.nom) LIKE :' . $k . ' OR LOWER(p.description) LIKE :' . $k . ')';
            $qb->setParameter($k, '%' . $w . '%');
        }
        $qb->andWhere($qb->expr()->orX(...$orParts));
        $qb->orderBy('p.nom', 'ASC')->setMaxResults(12);
        return $qb->getQuery()->getResult();
    }

    /** Sélection générale (phrase vague ou pas de match) : produits variés, pas par catégorie. @return Produit[] */
    private function getSuggestionsGenerales(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.disponibilite = :dispo')
            ->setParameter('dispo', true)
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults(12)
            ->getQuery()
            ->getResult();
    }

    /** Associe des mots et expressions générales au sens (catégories), pas seulement des termes techniques. @return Categorie[] */
    private function mapKeywordsToCategories(array $words, string $fullText): array
    {
        $text = ' ' . mb_strtolower($fullText) . ' ';
        $categories = [];
        $map = [
            // Sensoriel
            'sensoriel' => Categorie::SENSORIELS,
            'sensorielle' => Categorie::SENSORIELS,
            'stimulation' => Categorie::SENSORIELS,
            'tactile' => Categorie::SENSORIELS,
            'toucher' => Categorie::SENSORIELS,
            // Bruit / environnement
            'bruit' => Categorie::BRUIT_ET_ENVIRONNEMENT,
            'son' => Categorie::BRUIT_ET_ENVIRONNEMENT,
            'calme' => Categorie::BRUIT_ET_ENVIRONNEMENT,
            'environnement' => Categorie::BRUIT_ET_ENVIRONNEMENT,
            'silence' => Categorie::BRUIT_ET_ENVIRONNEMENT,
            'casque' => Categorie::BRUIT_ET_ENVIRONNEMENT,
            // Éducation / apprentissage
            'education' => Categorie::EDUCATION_APPRENTISSAGE,
            'apprentissage' => Categorie::EDUCATION_APPRENTISSAGE,
            'école' => Categorie::EDUCATION_APPRENTISSAGE,
            'scolaire' => Categorie::EDUCATION_APPRENTISSAGE,
            'concentration' => Categorie::EDUCATION_APPRENTISSAGE,
            'focus' => Categorie::EDUCATION_APPRENTISSAGE,
            // Communication / langage
            'communication' => Categorie::COMMUNICATION_LANGAGE,
            'langage' => Categorie::COMMUNICATION_LANGAGE,
            'parler' => Categorie::COMMUNICATION_LANGAGE,
            'expression' => Categorie::COMMUNICATION_LANGAGE,
            // Jeux / développement
            'jeu' => Categorie::JEUX_THERAPEUTIQUES_DEVELOPPEMENT,
            'jeux' => Categorie::JEUX_THERAPEUTIQUES_DEVELOPPEMENT,
            'développement' => Categorie::JEUX_THERAPEUTIQUES_DEVELOPPEMENT,
            'therapeutique' => Categorie::JEUX_THERAPEUTIQUES_DEVELOPPEMENT,
            'enfant' => Categorie::JEUX_THERAPEUTIQUES_DEVELOPPEMENT,
            'enfants' => Categorie::JEUX_THERAPEUTIQUES_DEVELOPPEMENT,
            'tsa' => Categorie::SENSORIELS,
            'autisme' => Categorie::SENSORIELS,
            'autiste' => Categorie::SENSORIELS,
            // Bien-être / relaxation
            'relaxation' => Categorie::BIEN_ETRE_RELAXATION,
            'relaxer' => Categorie::BIEN_ETRE_RELAXATION,
            'bien-être' => Categorie::BIEN_ETRE_RELAXATION,
            'bien etre' => Categorie::BIEN_ETRE_RELAXATION,
            'apaisement' => Categorie::BIEN_ETRE_RELAXATION,
            'stress' => Categorie::BIEN_ETRE_RELAXATION,
            'détendre' => Categorie::BIEN_ETRE_RELAXATION,
            'détente' => Categorie::BIEN_ETRE_RELAXATION,
            'sommeil' => Categorie::BIEN_ETRE_RELAXATION,
            'coucher' => Categorie::BIEN_ETRE_RELAXATION,
            'calmer' => Categorie::BIEN_ETRE_RELAXATION,
            // Vie quotidienne / aide générale
            'quotidien' => Categorie::VIE_QUOTIDIENNE,
            'vie quotidienne' => Categorie::VIE_QUOTIDIENNE,
            'autonomie' => Categorie::VIE_QUOTIDIENNE,
            'routine' => Categorie::VIE_QUOTIDIENNE,
            'aide' => Categorie::VIE_QUOTIDIENNE,
            'besoin' => Categorie::VIE_QUOTIDIENNE,
            'conseil' => Categorie::VIE_QUOTIDIENNE,
            'quelque' => Categorie::VIE_QUOTIDIENNE,
            'chose' => Categorie::VIE_QUOTIDIENNE,
        ];
        foreach ($map as $keyword => $cat) {
            if (mb_strpos($text, $keyword) !== false || in_array($keyword, $words, true)) {
                $categories[$cat->value] = $cat;
            }
        }
        return array_values($categories);
    }
}
