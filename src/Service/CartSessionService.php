<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Produit;
use App\Entity\User;
use App\Repository\ProduitRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Panier en session pour les utilisateurs non connectés.
 * À la connexion : fusion avec le panier DB. À la déconnexion : copie du panier DB vers la session.
 */
final class CartSessionService
{
    private const SESSION_KEY = 'cart_items';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProduitRepository $produitRepository,
    ) {
    }

    /** @return array<int, int> [produit_id => quantite] */
    public function getItems(): array
    {
        $session = $this->requestStack->getSession();
        $data = $session->get(self::SESSION_KEY, []);
        return \is_array($data) ? $data : [];
    }

    public function add(int $produitId, int $quantite = 1): void
    {
        $items = $this->getItems();
        $items[$produitId] = ($items[$produitId] ?? 0) + $quantite;
        $this->requestStack->getSession()->set(self::SESSION_KEY, $items);
    }

    public function setQuantity(int $produitId, int $quantite): void
    {
        $items = $this->getItems();
        if ($quantite < 1) {
            unset($items[$produitId]);
        } else {
            $items[$produitId] = $quantite;
        }
        $this->requestStack->getSession()->set(self::SESSION_KEY, $items);
    }

    public function remove(int $produitId): void
    {
        $items = $this->getItems();
        unset($items[$produitId]);
        $this->requestStack->getSession()->set(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, []);
    }

    /**
     * Retourne un objet "vue panier" compatible avec le template (getItems(), getTotalItems(), getTotalPrice(), isEmpty()).
     */
    public function getCartView(): CartView
    {
        $items = $this->getItems();
        $viewItems = [];
        foreach ($items as $produitId => $qty) {
            $produit = $this->produitRepository->find($produitId);
            if ($produit !== null && $qty > 0) {
                $viewItems[] = new CartViewItem($produit, $qty);
            }
        }
        return new CartView($viewItems);
    }

    /**
     * Copie le panier DB de l'utilisateur dans la session (à la déconnexion).
     */
    public function copyFromUserCart(Cart $cart): void
    {
        $items = [];
        foreach ($cart->getItems() as $item) {
            $p = $item->getProduit();
            if ($p !== null) {
                $items[$p->getId()] = $item->getQuantite();
            }
        }
        $this->requestStack->getSession()->set(self::SESSION_KEY, $items);
    }

    /**
     * Fusionne le panier session dans le panier DB de l'utilisateur (à la connexion), puis vide la session.
     */
    public function mergeIntoUserCart(User $user, Cart $userCart): void
    {
        $sessionItems = $this->getItems();
        if ($sessionItems === []) {
            return;
        }
        foreach ($sessionItems as $produitId => $qty) {
            $produit = $this->produitRepository->find($produitId);
            if ($produit === null || $qty < 1) {
                continue;
            }
            $existing = null;
            foreach ($userCart->getItems() as $item) {
                if ($item->getProduit()?->getId() === $produitId) {
                    $existing = $item;
                    break;
                }
            }
            if ($existing !== null) {
                $existing->setQuantite($existing->getQuantite() + $qty);
            } else {
                $cartItem = new CartItem();
                $cartItem->setProduit($produit);
                $cartItem->setQuantite($qty);
                $userCart->addItem($cartItem);
            }
        }
        $this->clear();
    }
}

/**
 * Vue du panier (session ou agrégat) pour le template.
 */
final class CartView
{
    /** @param CartViewItem[] $items */
    public function __construct(
        private readonly array $items,
    ) {
    }

    /** @return CartViewItem[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalItems(): int
    {
        $n = 0;
        foreach ($this->items as $item) {
            $n += $item->getQuantite();
        }
        return $n;
    }

    public function getTotalPrice(): float
    {
        $t = 0.0;
        foreach ($this->items as $item) {
            $t += $item->getTotalPrice();
        }
        return $t;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}

final class CartViewItem
{
    public function __construct(
        private readonly Produit $produit,
        private readonly int $quantite,
    ) {
    }

    public function getProduit(): Produit
    {
        return $this->produit;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getPrix(): float
    {
        return $this->produit->getPrix() ?? 0.0;
    }

    public function getTotalPrice(): float
    {
        return $this->getPrix() * $this->quantite;
    }
}
