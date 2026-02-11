<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Produit;
use App\Form\CommandeType;
use App\Repository\CartRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commander', name: 'order_')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProduitRepository $produitRepository,
        private readonly CartRepository $cartRepository,
    ) {
    }

    /**
     * Formulaire de commande pour un seul produit (clic sur le panier).
     */
    #[Route('/produit/{id}', name: 'from_product', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function fromProduct(Produit $produit, Request $request): Response
    {
        if (!$produit->isDisponibilite()) {
            $this->addFlash('warning', 'Ce produit n\'est pas disponible.');
            return $this->redirectToRoute('user_product_show', ['id' => $produit->getId()]);
        }

        $commande = new Commande();
        $user = $this->getUser();
        if ($user) {
            $commande->setUser($user);
            $commande->setNom($user->getNom() ?? '');
            $commande->setEmail($user->getEmail() ?? '');
            $commande->setTelephone((string) ($user->getTelephone() ?? ''));
        }
        $commande->setStatut('en_attente');
        $commande->setTotal($produit->getPrix() ?? 0);

        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commande = $form->getData();
            $prix = (float) ($produit->getPrix() ?? 0);
            $quantite = max(1, (int) $request->request->get('quantite', 1));

            $ligne = new LigneCommande();
            $ligne->setCommande($commande);
            $ligne->setProduit($produit);
            $ligne->setQuantite($quantite);
            $ligne->setPrix($prix);
            $ligne->setSousTotal($prix * $quantite);
            $commande->addLigne($ligne);
            $commande->setTotal($prix * $quantite);

            $this->entityManager->persist($commande);
            $this->entityManager->persist($ligne);
            $this->entityManager->flush();

            $this->addFlash('success', 'Commande enregistrée avec succès. Numéro : ' . $commande->getId());
            return $this->redirectToRoute('order_confirmation', ['id' => $commande->getId()]);
        }

        return $this->render('front/order/commande_form.html.twig', [
            'form' => $form,
            'produit' => $produit,
            'commande' => $commande,
        ]);
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour passer commande.');
            return $this->redirectToRoute('app_login');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart || $cart->isEmpty()) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        foreach ($cart->getItems() as $item) {
            if ($item->getQuantite() <= 0) {
                $this->addFlash('error', 'La quantité de chaque produit doit être au moins 1. Veuillez corriger les quantités dans le panier.');
                return $this->redirectToRoute('cart_index');
            }
        }

        $commande = new Commande();
        $commande->setUser($user);
        $commande->setNom($user->getNom() ?? '');
        $commande->setEmail($user->getEmail() ?? '');
        $commande->setTelephone((string) ($user->getTelephone() ?? ''));
        $commande->setStatut('en_attente');
        $commande->setTotal($cart->getTotalPrice());

        $form = $this->createForm(CommandeType::class, $commande, [
            'lock_user_fields' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commande = $form->getData();
            $commande->setUser($user);
            $commande->setNom($user->getNom() ?? '');
            $commande->setEmail($user->getEmail() ?? '');
            $commande->setTelephone((string) ($user->getTelephone() ?? ''));

            $request->getSession()->set('order_checkout_data', [
                'nom' => $commande->getNom(),
                'email' => $commande->getEmail(),
                'telephone' => $commande->getTelephone(),
                'adresse' => $commande->getAdresse(),
                'codePostal' => $commande->getCodePostal(),
                'ville' => $commande->getVille(),
                'modePayment' => $commande->getModePayment(),
            ]);
            return $this->redirectToRoute('order_review');
        }

        return $this->render('front/order/checkout.html.twig', [
            'form' => $form,
            'cart' => $cart,
            'totalPrice' => $cart->getTotalPrice(),
            'totalItems' => $cart->getTotalItems(),
        ]);
    }

    #[Route('/annuler', name: 'checkout_cancel', methods: ['GET'])]
    public function cancelCheckout(Request $request): Response
    {
        $request->getSession()->remove('order_checkout_data');
        $this->addFlash('info', 'Commande annulée. Votre panier est inchangé.');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/recapitulatif', name: 'review', methods: ['GET'])]
    public function review(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $data = $request->getSession()->get('order_checkout_data');
        if (!$data || !is_array($data)) {
            $this->addFlash('warning', 'Veuillez remplir le formulaire de commande.');
            return $this->redirectToRoute('order_checkout');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart || $cart->isEmpty()) {
            $request->getSession()->remove('order_checkout_data');
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        foreach ($cart->getItems() as $item) {
            if ($item->getQuantite() <= 0) {
                $this->addFlash('error', 'La quantité de chaque produit doit être au moins 1. Veuillez corriger le panier.');
                return $this->redirectToRoute('cart_index');
            }
        }

        return $this->render('front/order/review.html.twig', [
            'orderData' => $data,
            'cart' => $cart,
            'totalPrice' => $cart->getTotalPrice(),
            'totalItems' => $cart->getTotalItems(),
        ]);
    }

    #[Route('/confirmer', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('order_confirm', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('cart_index');
        }

        $data = $request->getSession()->get('order_checkout_data');
        if (!$data || !is_array($data)) {
            $this->addFlash('warning', 'Session expirée. Veuillez refaire le formulaire.');
            return $this->redirectToRoute('order_checkout');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart || $cart->isEmpty()) {
            $request->getSession()->remove('order_checkout_data');
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        foreach ($cart->getItems() as $item) {
            if ($item->getQuantite() <= 0) {
                $this->addFlash('error', 'La quantité de chaque produit doit être au moins 1. Veuillez corriger le panier.');
                return $this->redirectToRoute('cart_index');
            }
        }

        $commande = new Commande();
        $commande->setUser($user);
        $commande->setNom($data['nom'] ?? '');
        $commande->setEmail($data['email'] ?? '');
        $commande->setTelephone($data['telephone'] ?? '');
        $commande->setAdresse($data['adresse'] ?? '');
        $commande->setCodePostal($data['codePostal'] ?? '');
        $commande->setVille($data['ville'] ?? '');
        $commande->setModePayment($data['modePayment'] ?? 'a_la_livraison');
        $commande->setStatut('en_attente');

        $totalPrice = 0;
        foreach ($cart->getItems() as $item) {
            $ligne = new LigneCommande();
            $ligne->setCommande($commande);
            $ligne->setProduit($item->getProduit());
            $ligne->setQuantite($item->getQuantite());
            $ligne->setPrix($item->getPrix());
            $ligne->setSousTotal($item->getTotalPrice());
            $commande->addLigne($ligne);
            $totalPrice += $item->getTotalPrice();
            $this->entityManager->persist($ligne);
        }
        $commande->setTotal($totalPrice);

        $this->entityManager->persist($commande);
        $cart->clear();
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $request->getSession()->remove('order_checkout_data');

        $this->addFlash('success', 'Commande enregistrée avec succès. Numéro : ' . $commande->getId());
        return $this->redirectToRoute('order_confirmation', ['id' => $commande->getId()]);
    }

    #[Route('/confirmation/{id}', name: 'confirmation', methods: ['GET'])]
    public function confirmation(int $id): Response
    {
        $commande = $this->entityManager->getRepository(Commande::class)->find($id);
        
        if (!$commande) {
            throw $this->createNotFoundException('Commande introuvable');
        }

        return $this->render('front/order/confirmation.html.twig', [
            'commande' => $commande,
        ]);
    }
}
