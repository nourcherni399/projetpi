<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Produit;
use App\Repository\CartRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/panier', name: 'cart_')]
final class CartController extends AbstractController
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }


    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $cart = $request->getSession()->get('cart', []);
        return $this->render('front/cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'add', methods: ['POST'])]
    public function add(Produit $produit, Request $request): RedirectResponse
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);
        
        $key = 'produit_' . $produit->getId();
        if (isset($cart[$key])) {
            $cart[$key]['quantity']++;
        } else {
            $cart[$key] = [
                'id' => $produit->getId(),
                'nom' => $produit->getNom(),
                'prix' => $produit->getPrix(),
                'image' => $produit->getImage(),
                'quantity' => 1,
            ];
        }
        
        $session->set('cart', $cart);
        return $this->redirectToRoute('cart_index');
        
        $this->addFlash('success', 'Produit ajouté au panier!');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/supprimer/{id}', name: 'remove', methods: ['POST'])]
    public function removeItem(Request $request, int $id): RedirectResponse
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);
        
        $key = 'produit_' . $id;
        if (isset($cart[$key])) {
            unset($cart[$key]);
        }
        
        $session->set('cart', $cart);
        $this->addFlash('success', 'Produit supprimé du panier!');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/mettre-a-jour/{id}', name: 'update', methods: ['POST'])]
    public function updateQuantity(Request $request, int $id): RedirectResponse
    {
        $session = $request->getSession();
        $cart = $session->get('cart', []);
        
        $quantity = (int)$request->request->get('quantite', 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        $key = 'produit_' . $id;
        if (isset($cart[$key])) {
            $cart[$key]['quantity'] = $quantity;
        }
        
        $session->set('cart', $cart);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/vider', name: 'clear', methods: ['POST'])]
    public function clear(Request $request): RedirectResponse
    {
        $session = $request->getSession();
        $session->set('cart', []);
        $this->addFlash('success', 'Panier vidé!');
        return $this->redirectToRoute('cart_index');
    }
}
