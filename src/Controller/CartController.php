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
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $this->render('front/cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'add', methods: ['POST'])]
    public function add(Produit $produit, Request $request): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour ajouter des produits au panier.');
            return $this->redirectToRoute('login');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
        }

        // Vérifier si le produit est déjà dans le panier
        $existingItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getProduit()->getId() === $produit->getId()) {
                $existingItem = $item;
                break;
            }
        }

        if ($existingItem) {
            // Augmenter la quantité
            $existingItem->setQuantite($existingItem->getQuantite() + 1);
        } else {
            // Ajouter un nouvel item
            $cartItem = new CartItem();
            $cartItem->setProduit($produit);
            $cartItem->setQuantite(1);
            $cart->addItem($cartItem);
            $this->entityManager->persist($cartItem);
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Produit ajouté au panier!');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/supprimer/{id}', name: 'remove', methods: ['POST'])]
    public function removeItem(Request $request, int $id): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            return $this->redirectToRoute('cart_index');
        }

        // Trouver l'item à supprimer
        foreach ($cart->getItems() as $item) {
            if ($item->getProduit()->getId() === $id) {
                $cart->removeItem($item);
                $this->entityManager->remove($item);
                break;
            }
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Produit supprimé du panier!');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/mettre-a-jour/{id}', name: 'update', methods: ['POST'])]
    public function updateQuantity(Request $request, int $id): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            return $this->redirectToRoute('cart_index');
        }

        $quantity = (int)$request->request->get('quantite', 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        // Trouver l'item à mettre à jour
        foreach ($cart->getItems() as $item) {
            if ($item->getProduit()->getId() === $id) {
                $item->setQuantite($quantity);
                break;
            }
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/vider', name: 'clear', methods: ['POST'])]
    public function clear(Request $request): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if ($cart) {
            $cart->clear();
            $cart->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
        
        $this->addFlash('success', 'Panier vidé!');
        return $this->redirectToRoute('cart_index');
    }
}
