<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProduitRepository;
use App\Repository\CartRepository;
use App\Repository\CartItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/stats', name: 'admin_stats_')]
final class StatsController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly CartRepository $cartRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        try {
            // Augmenter le temps d'exécution pour cette page
            set_time_limit(300); // 5 minutes au lieu de 2
            
            // Statistiques des produits
            $totalProduits = $this->produitRepository->count([]);
            $produitsDisponibles = $this->produitRepository->count(['disponibilite' => true]);
            $produitsIndisponibles = $totalProduits - $produitsDisponibles;
            
            // Produits par catégorie
            $produitsParCategorie = $this->getProduitsParCategorie();
            
            // Prix moyens par catégorie
            $prixMoyensParCategorie = $this->getPrixMoyensParCategorie();
            
            // Top 5 des produits les plus chers
            $produitsPlusChers = $this->getProduitsPlusChers();
            
            // Top 5 des produits les moins chers
            $produitsMoinsChers = $this->getProduitsMoinsChers();
            
            // Statistiques des paniers
            $totalPaniers = $this->cartRepository->count([]);
            $paniersActifs = $this->getPaniersActifs();
            $totalArticlesDansPaniers = $this->getTotalArticlesDansPaniers();
            
            // Produits les plus ajoutés aux paniers
            $produitsPlusAjoutes = $this->getProduitsPlusAjoutesPaniers();
            
            // Valeur totale des paniers
            $valeurTotalePaniers = $this->getValeurTotalePaniers();

            return $this->render('admin/stats/index.html.twig', [
                'totalProduits' => $totalProduits,
                'produitsDisponibles' => $produitsDisponibles,
                'produitsIndisponibles' => $produitsIndisponibles,
                'produitsParCategorie' => $produitsParCategorie,
                'prixMoyensParCategorie' => $prixMoyensParCategorie,
                'produitsPlusChers' => $produitsPlusChers,
                'produitsMoinsChers' => $produitsMoinsChers,
                'totalPaniers' => $totalPaniers,
                'paniersActifs' => $paniersActifs,
                'totalArticlesDansPaniers' => $totalArticlesDansPaniers,
                'produitsPlusAjoutes' => $produitsPlusAjoutes,
                'valeurTotalePaniers' => $valeurTotalePaniers,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur, afficher uniquement les statistiques de base
            $totalProduits = $this->produitRepository->count([]);
            $produitsDisponibles = $this->produitRepository->count(['disponibilite' => true]);
            $produitsIndisponibles = $totalProduits - $produitsDisponibles;
            
            return $this->render('admin/stats/index.html.twig', [
                'totalProduits' => $totalProduits,
                'produitsDisponibles' => $produitsDisponibles,
                'produitsIndisponibles' => $produitsIndisponibles,
                'produitsParCategorie' => [],
                'prixMoyensParCategorie' => [],
                'produitsPlusChers' => [],
                'produitsMoinsChers' => [],
                'totalPaniers' => 0,
                'paniersActifs' => 0,
                'totalArticlesDansPaniers' => 0,
                'produitsPlusAjoutes' => [],
                'valeurTotalePaniers' => 0,
            ]);
        }
    }

    private function getProduitsParCategorie(): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('p.categorie as categorie', 'COUNT(p.id) as nombre')
               ->from('App\Entity\Produit', 'p')
               ->groupBy('p.categorie')
               ->setMaxResults(10); // Limiter pour éviter les timeouts
            
            $result = $qb->getQuery()->getResult();
            
            $categories = [];
            foreach ($result as $row) {
                $categories[] = [
                    'categorie' => $row['categorie'] ? $row['categorie']->label() : 'Non défini',
                    'nombre' => (int) $row['nombre']
                ];
            }
            
            return $categories;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getPrixMoyensParCategorie(): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('p.categorie as categorie', 'AVG(p.prix) as prixMoyen')
               ->from('App\Entity\Produit', 'p')
               ->groupBy('p.categorie')
               ->setMaxResults(10); // Limiter pour éviter les timeouts
            
            $result = $qb->getQuery()->getResult();
            
            $categories = [];
            foreach ($result as $row) {
                $categories[] = [
                    'categorie' => $row['categorie'] ? $row['categorie']->label() : 'Non défini',
                    'prixMoyen' => round((float) $row['prixMoyen'], 2)
                ];
            }
            
            return $categories;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getProduitsPlusChers(): array
    {
        return $this->produitRepository->findBy([], ['prix' => 'DESC'], 5);
    }

    private function getProduitsMoinsChers(): array
    {
        return $this->produitRepository->findBy([], ['prix' => 'ASC'], 5);
    }

    private function getPaniersActifs(): int
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('COUNT(c.id)')
               ->from('App\Entity\Cart', 'c')
               ->where('SIZE(c.items) > 0')
               ->setMaxResults(1); // Optimisation
            
            return (int) ($qb->getQuery()->getSingleScalarResult() ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getTotalArticlesDansPaniers(): int
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('SUM(ci.quantite)')
               ->from('App\Entity\CartItem', 'ci')
               ->setMaxResults(1); // Optimisation
            
            return (int) ($qb->getQuery()->getSingleScalarResult() ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getProduitsPlusAjoutesPaniers(): array
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('p.id', 'p.nom', 'p.prix', 'SUM(ci.quantite) as totalQuantite')
               ->from('App\Entity\CartItem', 'ci')
               ->join('ci.produit', 'p')
               ->groupBy('p.id', 'p.nom', 'p.prix')
               ->orderBy('totalQuantite', 'DESC')
               ->setMaxResults(5);
            
            return $qb->getQuery()->getResult() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getValeurTotalePaniers(): float
    {
        try {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('SUM(ci.quantite * ci.prix)')
               ->from('App\Entity\CartItem', 'ci')
               ->setMaxResults(1); // Optimisation
            
            return (float) ($qb->getQuery()->getSingleScalarResult() ?? 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
