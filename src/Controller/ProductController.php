<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProduitRepository;
use App\Enum\Categorie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
    ) {
    }

    #[Route('', name: 'user_products_index', methods: ['GET'])]
    public function index(Request $request): Response
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
}
