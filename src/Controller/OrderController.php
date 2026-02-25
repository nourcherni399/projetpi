<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Commande;
use App\Entity\LigneCommande;
<<<<<<< HEAD
use App\Entity\Notification;
=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
use App\Entity\Produit;
use App\Form\CommandeType;
use App\Repository\CartRepository;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use App\Repository\UserRepository;
use App\Service\EmailApiService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/commander', name: 'order_')]
final class OrderController extends AbstractController
{
    private const CARD_VERIFY_TTL = 900; // 15 minutes
    private const FROM_EMAIL = 'noreply@auticare.fr';
    private const FROM_NAME = 'AutiCare';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProduitRepository $produitRepository,
        private readonly CartRepository $cartRepository,
        private readonly CommandeRepository $commandeRepository,
<<<<<<< HEAD
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly EmailApiService $emailApiService,
        private readonly CacheInterface $cache,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?string $mailerFromEmail = null,
        private readonly ?StripeService $stripeService = null,
=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
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
<<<<<<< HEAD
        $commande->setModePayment('a_la_livraison');

        $stripePublishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
        $stripeConfigured = ($this->stripeService?->isConfigured() ?? false)
            && $stripePublishableKey !== ''
            && !str_contains($stripePublishableKey, 'VOTRE');

        $form = $this->createForm(CommandeType::class, $commande, [
            'lock_user_fields' => false,
            'stripe_configured' => $stripeConfigured,
=======

        $form = $this->createForm(CommandeType::class, $commande, [
            'lock_user_fields' => true,
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commande = $form->getData();
            $commande->setUser($user);
<<<<<<< HEAD
            $emailCommande = trim((string) ($commande->getEmail() ?? ''));
            if ($emailCommande === '') {
                $commande->setEmail($user->getEmail() ?? '');
            }

            $checkoutData = [
=======
            $commande->setNom($user->getNom() ?? '');
            $commande->setEmail($user->getEmail() ?? '');
            $commande->setTelephone((string) ($user->getTelephone() ?? ''));

            $request->getSession()->set('order_checkout_data', [
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
                'nom' => $commande->getNom(),
                'email' => $commande->getEmail(),
                'telephone' => $commande->getTelephone(),
                'adresse' => $commande->getAdresse(),
                'codePostal' => $commande->getCodePostal(),
                'ville' => $commande->getVille(),
<<<<<<< HEAD
                'modePayment' => $commande->getModePayment() ?? 'a_la_livraison',
            ];
            $paymentMethodId = $request->request->get('stripe_payment_method_id');
            if ($paymentMethodId && ($commande->getModePayment() ?? '') === 'carte_bancaire') {
                $checkoutData['stripe_payment_method_id'] = $paymentMethodId;
            }

            $request->getSession()->set('order_checkout_data', $checkoutData);
=======
                'modePayment' => $commande->getModePayment(),
            ]);
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
            return $this->redirectToRoute('order_review');
        }

        return $this->render('front/order/checkout.html.twig', [
            'form' => $form,
            'cart' => $cart,
            'totalPrice' => $cart->getTotalPrice(),
            'totalItems' => $cart->getTotalItems(),
<<<<<<< HEAD
            'stripePublishableKey' => $stripeConfigured ? $stripePublishableKey : null,
=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        ]);
    }

    #[Route('/annuler', name: 'checkout_cancel', methods: ['GET'])]
    public function cancelCheckout(Request $request): Response
    {
        $request->getSession()->remove('order_checkout_data');
<<<<<<< HEAD
        $request->getSession()->remove('order_verify_email_sent');
=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        $this->addFlash('info', 'Commande annulée. Votre panier est inchangé.');
        return $this->redirectToRoute('cart_index');
    }

<<<<<<< HEAD
    /**
     * Page affichée après envoi de l'email de vérification paiement par carte.
     */
    #[Route('/verification-email-envoyee', name: 'checkout_verify_email', methods: ['GET'])]
    public function checkoutVerifyEmailSent(Request $request): Response
    {
        $email = $request->getSession()->get('order_verify_email_sent');
        if (!$email) {
            $this->addFlash('warning', 'Session invalide. Veuillez repasser par le formulaire de commande.');
            return $this->redirectToRoute('order_checkout');
        }
        return $this->render('front/order/verify_email_sent.html.twig', [
            'email' => $email,
        ]);
    }

    /**
     * Lien reçu par email : vérifie le token et redirige vers le récapitulatif.
     */
    #[Route('/verifier-carte/{token}', name: 'verify_card', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function verifyCard(Request $request, string $token): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Connectez-vous pour confirmer votre paiement par carte.');
            return $this->redirectToRoute('app_login', ['_target_path' => $this->urlGenerator->generate('order_verify_card', ['token' => $token])]);
        }

        $cacheKey = 'order_card_verify_' . $token;
        try {
            $stored = $this->cache->get($cacheKey, function (ItemInterface $item) {
                $item->expiresAfter(0);
                throw new \Symfony\Contracts\Cache\CacheException('miss');
            });
        } catch (\Throwable $e) {
            $stored = null;
        }

        if (!$stored || !isset($stored['user_id'], $stored['data']) || (int) $stored['user_id'] !== (int) $user->getId()) {
            $this->addFlash('error', 'Lien invalide ou expiré. Veuillez refaire le formulaire de commande.');
            return $this->redirectToRoute('order_checkout');
        }

        $this->cache->delete($cacheKey);
        $request->getSession()->set('order_checkout_data', $stored['data']);
        $request->getSession()->remove('order_verify_email_sent');
        $this->addFlash('success', 'Paiement par carte vérifié. Vous pouvez finaliser votre commande.');
        return $this->redirectToRoute('order_review');
    }

    #[Route('/recapitulatif', name: 'review', methods: ['GET'])]
    public function review(Request $request): Response
=======
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
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

<<<<<<< HEAD
        $data = $request->getSession()->get('order_checkout_data');
        if (!$data || !is_array($data)) {
            $this->addFlash('warning', 'Veuillez remplir le formulaire de commande.');
=======
        if (!$this->isCsrfTokenValid('order_confirm', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('cart_index');
        }

        $data = $request->getSession()->get('order_checkout_data');
        if (!$data || !is_array($data)) {
            $this->addFlash('warning', 'Session expirée. Veuillez refaire le formulaire.');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
            return $this->redirectToRoute('order_checkout');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart || $cart->isEmpty()) {
            $request->getSession()->remove('order_checkout_data');
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
<<<<<<< HEAD
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

=======
        }

>>>>>>> 454cf3534cd44ab862139630471999260fa62858
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

        $stripePaymentMethodId = $data['stripe_payment_method_id'] ?? null;
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
<<<<<<< HEAD
        }
        $commande->setTotal($totalPrice);

        if (($data['modePayment'] ?? '') === 'carte_bancaire' && $stripePaymentMethodId) {
            if (!$this->stripeService?->isConfigured()) {
                $this->addFlash('error', 'Le paiement par carte est temporairement indisponible.');
                return $this->redirectToRoute('order_checkout');
            }
            $amountCents = (int) round($totalPrice * 100); // Conversion en centimes (pour EUR)
            $result = $this->stripeService->createAndCapturePaymentIntent($amountCents, $stripePaymentMethodId);
            if (!$result['success']) {
                $this->addFlash('error', 'Paiement refusé : ' . ($result['error'] ?? 'Erreur inconnue'));
                return $this->redirectToRoute('order_review');
            }
            $commande->setStripePaymentIntent($result['paymentIntentId'] ?? null);
            $commande->setStatut('payée');
        }

        $this->entityManager->persist($commande);
        $cart->clear();
        $cart->setUpdatedAt(new \DateTimeImmutable());
        
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        foreach ($admins as $admin) {
            $notif = new Notification();
            $notif->setDestinataire($admin);
            $notif->setType(Notification::TYPE_NOUVELLE_COMMANDE);
            $notif->setCommande($commande);
            $this->entityManager->persist($notif);
        }
        
        $this->entityManager->flush();

        $request->getSession()->remove('order_checkout_data');

        $toEmail = $commande->getEmail() ?? '';
        if ($toEmail !== '') {
            try {
                $this->sendOrderConfirmationEmail($commande);
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Commande enregistrée, mais l\'e-mail de confirmation de paiement n\'a pas pu être envoyé.');
            }
        }
=======
        }
        $commande->setTotal($totalPrice);

        $this->entityManager->persist($commande);
        $cart->clear();
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $request->getSession()->remove('order_checkout_data');
>>>>>>> 454cf3534cd44ab862139630471999260fa62858

        $this->addFlash('success', 'Commande enregistrée avec succès. Numéro : ' . $commande->getId());
        return $this->redirectToRoute('order_confirmation', ['id' => $commande->getId()]);
    }

    #[Route('/mes-commandes', name: 'my_orders', methods: ['GET'])]
    public function myOrders(): Response
<<<<<<< HEAD
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour voir vos commandes.');
            return $this->redirectToRoute('app_login');
        }
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/confirmation/{id}/recu', name: 'receipt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function receipt(int $id): Response
    {
=======
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour voir vos commandes.');
            return $this->redirectToRoute('app_login');
        }

        $commandes = $this->commandeRepository->findByUserOrderedByDate($user);

        return $this->render('front/order/list.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/confirmation/{id}', name: 'confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function confirmation(int $id): Response
    {
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande introuvable');
        }

        $user = $this->getUser();
        $isOwner = $user && $commande->getUser() && $commande->getUser()->getId() === $user->getId();
        if (!$isOwner && !$this->isGranted('ROLE_ADMIN')) {
<<<<<<< HEAD
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter ce reçu.');
        }

        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->addFlash('error', 'Téléchargement du reçu indisponible.');
            return $this->redirectToRoute('order_confirmation', ['id' => $id]);
        }

        $html = $this->renderView('front/order/receipt_pdf.html.twig', ['commande' => $commande]);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        $dompdf->getOptions()->set('defaultFont', 'DejaVu Sans');
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        $filename = 'recu_commande_' . $id . '_' . date('Y-m-d') . '.pdf';
        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $response;
    }

    #[Route('/confirmation/{id}', name: 'confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function confirmation(int $id): Response
    {
        $commande = $this->commandeRepository->find($id);
        if (!$commande) {
            throw $this->createNotFoundException('Commande introuvable');
        }

        $user = $this->getUser();
        $isOwner = $user && $commande->getUser() && $commande->getUser()->getId() === $user->getId();
        if (!$isOwner && !$this->isGranted('ROLE_ADMIN')) {
=======
>>>>>>> 454cf3534cd44ab862139630471999260fa62858
            throw $this->createAccessDeniedException('Vous ne pouvez pas consulter cette commande.');
        }

        return $this->render('front/order/confirmation.html.twig', [
            'commande' => $commande,
        ]);
    }

    private function sendCardVerificationEmail(string $toEmail, string $token): void
    {
        $verifyUrl = $this->urlGenerator->generate('order_verify_card', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $verifyUrl = htmlspecialchars($verifyUrl, \ENT_QUOTES, 'UTF-8');
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><style>body{font-family:sans-serif;background:#f5f1eb;padding:20px;color:#4B5563;} .box{max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #E5E0D8;} p{line-height:1.6;} .btn{display:inline-block;margin:16px 0;padding:14px 28px;background:#A7C7E7;color:#fff!important;text-decoration:none;border-radius:8px;font-weight:bold;} .footer{font-size:12px;color:#6B7280;margin-top:24px;}</style></head>
        <body>
        <div class="box">
        <p>Bonjour,</p>
        <p>Vous avez choisi le <strong>paiement par carte bancaire</strong> pour votre commande sur AutiCare.</p>
        <p>Pour vérifier que c'est bien vous qui effectuez ce paiement, cliquez sur le bouton ci-dessous (lien valide 15 minutes) :</p>
        <p style="text-align:center;"><a href="{$verifyUrl}" class="btn">Confirmer que c'est bien moi</a></p>
        <p>Si vous n'êtes pas à l'origine de cette demande de paiement par carte, ignorez cet e-mail et ne cliquez pas sur le lien.</p>
        <p class="footer">— L'équipe AutiCare</p>
        </div>
        </body>
        </html>
        HTML;

        $subject = 'Vérification de votre paiement par carte - AutiCare';
        $fromEmail = ($this->mailerFromEmail !== null && $this->mailerFromEmail !== '') ? $this->mailerFromEmail : self::FROM_EMAIL;
        if ($this->emailApiService->isConfigured()) {
            $this->emailApiService->send($toEmail, $subject, $html, $fromEmail, self::FROM_NAME);
        } else {
            $email = (new Email())
                ->from(new Address($fromEmail, self::FROM_NAME))
                ->to($toEmail)
                ->subject($subject)
                ->html($html);
            $this->mailer->send($email);
        }
    }

    private function sendOrderConfirmationEmail(Commande $commande): void
    {
        $toEmail = $commande->getEmail() ?? '';
        if ($toEmail === '') {
            return;
        }

        $id = $commande->getId();
        $total = number_format((float) $commande->getTotal(), 2, ',', ' ');
        $lignesHtml = '';
        foreach ($commande->getLignes() as $ligne) {
            $produit = $ligne->getProduit();
            $nom = $produit ? htmlspecialchars($produit->getNom() ?? 'Produit', \ENT_QUOTES, 'UTF-8') : 'Produit';
            $qte = $ligne->getQuantite();
            $st = number_format((float) $ligne->getSousTotal(), 2, ',', ' ');
            $lignesHtml .= "<tr><td>{$nom}</td><td>{$qte}</td><td>{$st} DT</td></tr>";
        }
        $adresse = htmlspecialchars($commande->getAdresse() ?? '', \ENT_QUOTES, 'UTF-8');
        $codePostal = htmlspecialchars($commande->getCodePostal() ?? '', \ENT_QUOTES, 'UTF-8');
        $ville = htmlspecialchars($commande->getVille() ?? '', \ENT_QUOTES, 'UTF-8');

        $modePayment = $commande->getModePayment() ?? '';
        $isPaiementEnLigne = $modePayment === 'carte_bancaire' || str_starts_with($modePayment, 'carte_') || $commande->getStripePaymentIntent() !== null;

        $recuMsg = $isPaiementEnLigne
            ? 'Téléchargez votre <strong>bon de livraison (preuve de paiement)</strong> sur la page de confirmation et présentez-le au livreur pour la remise du colis. Aucun paiement à effectuer à la livraison.'
            : 'Vous pouvez télécharger votre <strong>bon de commande</strong> depuis la page de confirmation.';

        $introMsg = $isPaiementEnLigne
            ? 'Validation de paiement : nous avons bien reçu votre commande et votre paiement en ligne.'
            : 'Nous avons bien reçu votre commande.';

        $subject = $isPaiementEnLigne
            ? 'Validation de paiement - Commande n°' . $id . ' - AutiCare'
            : 'Confirmation de votre commande n°' . $id . ' - AutiCare';

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><style>body{font-family:sans-serif;background:#f5f1eb;padding:20px;color:#4B5563;} .box{max-width:520px;margin:0 auto;background:#fff;border-radius:12px;padding:24px;border:1px solid #E5E0D8;} p{line-height:1.6;} table{width:100%;border-collapse:collapse;margin:16px 0;} th,td{padding:8px;text-align:left;border-bottom:1px solid #eee;} .total{font-weight:bold;font-size:1.1em;} .footer{font-size:12px;color:#6B7280;margin-top:24px;} .highlight{background:#F0F9FF;padding:12px;border-radius:8px;margin:16px 0;}</style></head>
        <body>
        <div class="box">
        <p>Bonjour {$this->e($commande->getNom())},</p>
        <p>{$introMsg}</p>
        <p><strong>Commande n°{$id}</strong></p>
        <table>
        <thead><tr><th>Produit</th><th>Qté</th><th>Sous-total</th></tr></thead>
        <tbody>{$lignesHtml}</tbody>
        <tfoot><tr><td colspan="2" class="total">Total</td><td class="total">{$total} DT</td></tr></tfoot>
        </table>
        <p>Adresse de livraison :<br>{$adresse}, {$codePostal} {$ville}</p>
        <div class="highlight"><p>{$recuMsg}</p></div>
        <p class="footer">— L'équipe AutiCare</p>
        </div>
        </body>
        </html>
        HTML;
        $fromEmail = ($this->mailerFromEmail !== null && $this->mailerFromEmail !== '') ? $this->mailerFromEmail : self::FROM_EMAIL;
        if ($this->emailApiService->isConfigured()) {
            $this->emailApiService->send($toEmail, $subject, $html, $fromEmail, self::FROM_NAME);
        } else {
            $email = (new Email())
                ->from(new Address($fromEmail, self::FROM_NAME))
                ->to($toEmail)
                ->subject($subject)
                ->html($html);
            $this->mailer->send($email);
        }
    }

    private function e(?string $s): string
    {
        return htmlspecialchars($s ?? '', \ENT_QUOTES, 'UTF-8');
    }
}