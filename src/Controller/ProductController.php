<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProduitRepository;
use App\Enum\Categorie;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/produits')]
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

        $minPriceVal = $minPrice !== null && $minPrice !== '' ? (int) $minPrice : null;
        $maxPriceVal = $maxPrice !== null && $maxPrice !== '' ? (int) $maxPrice : null;
        $searchTerm = $search !== null && $search !== '' ? strtolower(trim((string) $search)) : '';

        if ($minPriceVal !== null || $maxPriceVal !== null || $searchTerm !== '') {
            $minFilter = $minPriceVal ?? 0;
            $maxFilter = $maxPriceVal ?? PHP_INT_MAX;

            $produits = array_filter($produits, function ($produit) use ($minFilter, $maxFilter, $searchTerm) {
                $priceMatch = $produit->getPrix() !== null && (float) $produit->getPrix() >= $minFilter && (float) $produit->getPrix() <= $maxFilter;

                if ($searchTerm === '') {
                    return $priceMatch;
                }

                $nom = $produit->getNom() ?? '';
                $nomMatch = strpos(strtolower($nom), $searchTerm) !== false;
                $descriptionMatch = $produit->getDescription() !== null && strpos(strtolower($produit->getDescription()), $searchTerm) !== false;
                $categorieMatch = $produit->getCategorie() !== null && strpos(strtolower($produit->getCategorie()->label()), $searchTerm) !== false;

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
            'cart_add' => true,
        ]);
    }
}
