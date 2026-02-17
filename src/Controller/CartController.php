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

        $stockDispo = $this->getStockQuantity($produit);
        if ($stockDispo <= 0) {
            $this->addFlash('error', 'Le produit n\'est pas en stock.');
            return $this->redirectToProductOrCart($request, $produit->getId());
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
        }

        $existingItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getProduit()->getId() === $produit->getId()) {
                $existingItem = $item;
                break;
            }
        }

        $nouvelleQuantite = $existingItem ? $existingItem->getQuantite() + 1 : 1;
        if ($nouvelleQuantite > $stockDispo) {
            $this->addFlash('error', 'Le produit n\'est pas en stock.');
            return $this->redirectToProductOrCart($request, $produit->getId());
        }

        if ($existingItem) {
            $existingItem->setQuantite($nouvelleQuantite);
        } else {
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

    private function getStockQuantity(Produit $produit): int
    {
        $stock = $produit->getStock();
        return $stock !== null ? $stock->getQuantite() : 0;
    }

    private function redirectToProductOrCart(Request $request, int $produitId): RedirectResponse
    {
        $referer = $request->headers->get('Referer', '');
        if (str_contains($referer, '/produits/')) {
            return $this->redirectToRoute('user_product_show', ['id' => $produitId]);
        }
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

        $quantity = (int) $request->request->get('quantite', 1);
        if ($quantity < 1) {
            $this->addFlash('error', 'La quantité doit être au moins 1.');
            return $this->redirectToRoute('cart_index');
        }

        foreach ($cart->getItems() as $item) {
            if ($item->getProduit()->getId() === $id) {
                $produit = $item->getProduit();
                $stockDispo = $this->getStockQuantity($produit);
                if ($stockDispo <= 0) {
                    $this->addFlash('error', 'Le produit n\'est pas en stock.');
                    return $this->redirectToRoute('cart_index');
                }
                if ($quantity > $stockDispo) {
                    $this->addFlash('error', 'Le produit n\'est pas en stock.');
                    return $this->redirectToRoute('cart_index');
                }
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
