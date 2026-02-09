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
final class ProduitPublicController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
    ) {
    }

    #[Route('', name: 'produit_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $categorie = $request->query->get('categorie');
        
        if ($categorie) {
            try {
                $categorieEnum = Categorie::from($categorie);
                $produits = $this->produitRepository->findBy(['categorie' => $categorieEnum], ['nom' => 'ASC']);
            } catch (\ValueError $e) {
                $produits = $this->produitRepository->findBy([], ['nom' => 'ASC']);
            }
        } else {
            $produits = $this->produitRepository->findBy([], ['nom' => 'ASC']);
        }
        
        return $this->render('front/produit/list.html.twig', [
            'produits' => $produits,
            'categories' => Categorie::cases(),
            'selectedCategorie' => $categorie,
        ]);
    }
}
