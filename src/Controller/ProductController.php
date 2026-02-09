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
        
        $criteria = [];
        
        if ($categorie) {
            try {
                $categorieEnum = Categorie::from($categorie);
                $criteria['categorie'] = $categorieEnum;
            } catch (\ValueError $e) {
                // Invalid category, ignore
            }
        }
        
        $produits = $this->produitRepository->findBy($criteria, ['nom' => 'ASC']);
        
        // Filter by price range in PHP
        if ($minPrice !== null || $maxPrice !== null) {
            $minPrice = $minPrice !== null ? (int)$minPrice : 0;
            $maxPrice = $maxPrice !== null ? (int)$maxPrice : PHP_INT_MAX;
            
            $produits = array_filter($produits, function($produit) use ($minPrice, $maxPrice) {
                return $produit->getPrix() >= $minPrice && $produit->getPrix() <= $maxPrice;
            });
        }
        
        return $this->render('front/products/index.html.twig', [
            'produits' => $produits,
            'categories' => Categorie::cases(),
            'selectedCategorie' => $categorie,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
        ]);
    }
}
