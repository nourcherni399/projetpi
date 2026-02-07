<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\LigneCommande;
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
    ) {
    }

    #[Route('/checkout', name: 'checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        $cart = $request->getSession()->get('cart', []);
        
        if (empty($cart)) {
            $this->addFlash('warning', 'Votre panier est vide!');
            return $this->redirectToRoute('cart_index');
        }

        if ($request->isMethod('POST')) {
            return $this->processCheckout($request, $cart);
        }

        // Calculate totals
        $totalPrice = 0;
        $totalItems = 0;
        foreach ($cart as $item) {
            $totalPrice += $item['prix'] * $item['quantity'];
            $totalItems += $item['quantity'];
        }

        return $this->render('front/order/checkout.html.twig', [
            'cart' => $cart,
            'totalPrice' => $totalPrice,
            'totalItems' => $totalItems,
        ]);
    }

    private function processCheckout(Request $request, array $cart): Response
    {
        $nom = $request->request->get('nom');
        $email = $request->request->get('email');
        $telephone = $request->request->get('telephone');
        $adresse = $request->request->get('adresse');
        $ville = $request->request->get('ville');
        $codePostal = $request->request->get('code_postal');
        $paiement = $request->request->get('paiement');
        
        // Validation simple
        if (!$nom || !$email || !$telephone || !$adresse || !$ville || !$paiement) {
            $this->addFlash('error', 'Veuillez remplir tous les champs requis!');
            return $this->redirectToRoute('order_checkout');
        }

        // Validation email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse email invalide!');
            return $this->redirectToRoute('order_checkout');
        }

        // Validation téléphone (au moins 8 chiffres)
        if (!preg_match('/^\d{8,}$/', str_replace([' ', '-', '+', '(', ')'], '', $telephone))) {
            $this->addFlash('error', 'Numéro de téléphone invalide!');
            return $this->redirectToRoute('order_checkout');
        }

        // Si paiement par carte, valider les détails
        if ($paiement === 'card') {
            $cardNumber = str_replace(' ', '', $request->request->get('card_number', ''));
            $cardExpiry = $request->request->get('card_expiry', '');
            $cardCvv = $request->request->get('card_cvv', '');

            // Validation numéro de carte (16 chiffres)
            if (!preg_match('/^\d{16}$/', $cardNumber)) {
                $this->addFlash('error', 'Numéro de carte invalide (16 chiffres attendus)!');
                return $this->redirectToRoute('order_checkout');
            }

            // Validation date d'expiration (MM/YY)
            if (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
                $this->addFlash('error', 'Date d\'expiration invalide (format MM/YY)!');
                return $this->redirectToRoute('order_checkout');
            }

            // Validation CVV (3-4 chiffres)
            if (!preg_match('/^\d{3,4}$/', $cardCvv)) {
                $this->addFlash('error', 'CVV invalide (3-4 chiffres attendus)!');
                return $this->redirectToRoute('order_checkout');
            }
        }

        // Créer la commande
        $commande = new Commande();
        $commande->setNom($nom);
        $commande->setEmail($email);
        $commande->setTelephone($telephone);
        $commande->setAdresse($adresse);
        $commande->setVille($ville);
        $commande->setCodePostal($codePostal ?? '');
        $commande->setModePayment($paiement);
        $commande->setStatut('en_attente');

        $totalPrice = 0;

        // Ajouter les articles à la commande
        foreach ($cart as $item) {
            // L'item contient directement les infos, chercher le produit par ID
            $produit = $this->produitRepository->find($item['id']);
            if ($produit) {
                $ligneCommande = new LigneCommande();
                $ligneCommande->setCommande($commande);
                $ligneCommande->setProduit($produit);
                $ligneCommande->setQuantite($item['quantity']);
                $ligneCommande->setPrix($item['prix']);
                $ligneCommande->setSousTotal($item['prix'] * $item['quantity']);
                $commande->addLigne($ligneCommande);
                
                $totalPrice += $item['prix'] * $item['quantity'];
                
                $this->entityManager->persist($ligneCommande);
            }
        }

        $commande->setTotal($totalPrice);
        $this->entityManager->persist($commande);
        $this->entityManager->flush();

        // Vider le panier
        $request->getSession()->set('cart', []);

        $this->addFlash('success', 'Commande passée avec succès! Numéro: ' . $commande->getId());
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
