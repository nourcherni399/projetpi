<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Doctrine\DBAL\Exception\InvalidFieldNameException;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\Notification;
use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/commandes')]
final class CommandeController extends AbstractController
{
    public function __construct(
        private readonly CommandeRepository $commandeRepository,
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Crée une commande de démo (comme si le client avait envoyé le formulaire) pour tester le flux.
     */
    #[Route('/creer-demo', name: 'admin_commande_creer_demo', methods: ['GET'])]
    public function creerDemo(): Response
    {
        $user = $this->getUser();
        $produits = $this->produitRepository->findBy(['disponibilite' => true], ['nom' => 'ASC'], 1);
        if (\count($produits) === 0) {
            $this->addFlash('error', 'Aucun produit disponible. Ajoutez au moins un produit pour créer une commande démo.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        $produit = $produits[0];
        $prix = (float) $produit->getPrix();
        $quantite = 1;
        $total = $prix * $quantite;

        $commande = new Commande();
        $commande->setUser($user);
        $commande->setNom($user ? (trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')) ?: 'Admin') : 'Client Démo');
        $commande->setEmail($user ? $user->getEmail() : 'demo@auticare.fr');
        $commande->setTelephone($user ? (string) ($user->getTelephone() ?? '00000000') : '00000000');
        $commande->setAdresse('Adresse démo');
        $commande->setCodePostal('10000');
        $commande->setVille('Tunis');
        $commande->setTotal($total);
        $commande->setStatut('en_attente');
        $commande->setModePayment('a_la_livraison');

        $ligne = new LigneCommande();
        $ligne->setCommande($commande);
        $ligne->setProduit($produit);
        $ligne->setQuantite($quantite);
        $ligne->setPrix($prix);
        $ligne->setSousTotal($total);
        $commande->addLigne($ligne);

        $this->entityManager->persist($commande);
        $this->entityManager->persist($ligne);
        $this->entityManager->flush();

        $this->addFlash('success', 'Commande démo #' . $commande->getId() . ' créée. Vous pouvez voir la page confirmation ou gérer les étapes dans la liste.');
        return $this->redirectToRoute('order_confirmation', ['id' => $commande->getId()]);
    }

    #[Route('', name: 'admin_commandes_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $commandes = $this->commandeRepository->findAllOrderedByDate();

        return $this->render('admin/commande/index.html.twig', [
            'commandes' => $commandes,
        ]);
    }

    #[Route('/{id}/changer-statut', name: 'admin_commande_changer_statut', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changerStatut(Request $request, int $id): Response
    {
        $commande = $this->commandeRepository->find($id);
        if ($commande === null) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        $token = $request->request->get('_token');
        $nouveauStatut = $request->request->get('statut');

        $statutsValides = ['confirmer', 'livraison', 'recu', 'annulée'];
        if (!\in_array($nouveauStatut, $statutsValides, true)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        if (!$this->isCsrfTokenValid('commande_statut_' . $id, $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        $statutActuel = $commande->getStatut();
        $ordre = ['en_attente' => 0, 'confirmer' => 1, 'livraison' => 2, 'recu' => 3, 'annulée' => -1];
        $ordreActuel = $ordre[$statutActuel] ?? 0;
        $ordreCible = $ordre[$nouveauStatut] ?? 0;

        if ($nouveauStatut === 'annulée') {
            $commande->setStatut('annulée');
            $this->entityManager->flush();
            $this->addFlash('success', 'Commande #' . $id . ' annulée.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        if ($ordreCible <= $ordreActuel) {
            $this->addFlash('warning', 'Cette commande est déjà à ce stade ou au-delà.');
            return $this->redirectToRoute('admin_commandes_index');
        }

        $commande->setStatut($nouveauStatut);

        $destinataire = $commande->getUser();
        if ($destinataire !== null) {
            $type = match ($nouveauStatut) {
                'confirmer' => Notification::TYPE_COMMANDE_CONFIRMEE,
                'livraison' => Notification::TYPE_COMMANDE_LIVRAISON,
                'recu' => Notification::TYPE_COMMANDE_RECU,
                default => null,
            };
            if ($type !== null) {
                $notif = new Notification();
                $notif->setDestinataire($destinataire);
                $notif->setType($type);
                $notif->setCommande($commande);
                $this->entityManager->persist($notif);
            }
        }

        try {
            $this->entityManager->flush();
        } catch (InvalidFieldNameException $e) {
            // Colonne commande_id absente (migration non exécutée) : enregistrer uniquement le statut
            if ($destinataire !== null && isset($notif)) {
                $this->entityManager->remove($notif);
                $this->entityManager->flush();
            }
        }

        $messages = [
            'confirmer' => 'Commande #' . $id . ' confirmée. Elle passe en préparation.',
            'livraison' => 'Commande #' . $id . ' marquée en livraison.',
            'recu' => 'Commande #' . $id . ' marquée comme reçue.',
        ];
        $this->addFlash('success', $messages[$nouveauStatut]);

        return $this->redirectToRoute('admin_commandes_index');
    }
}
