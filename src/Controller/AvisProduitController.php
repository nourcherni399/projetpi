<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AvisProduit;
use App\Entity\Produit;
use App\Repository\AvisProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/produits')]
final class AvisProduitController extends AbstractController
{
    public function __construct(
        private readonly AvisProduitRepository $avisProduitRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/{id}/avis', name: 'user_produit_avis', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function submitAvis(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if ($user === null) {
            $this->addFlash('error', 'Connectez-vous pour noter ce produit.');
            return $this->redirectToRoute('app_login', ['_target_path' => $this->generateUrl('user_product_show', ['id' => $id])]);
        }

        $produit = $this->entityManager->getRepository(Produit::class)->find($id);
        if ($produit === null) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        if (!$this->isCsrfTokenValid('avis_produit_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('user_product_show', ['id' => $id]);
        }

        $note = (int) $request->request->get('note', 0);
        if ($note < 1 || $note > 5) {
            $this->addFlash('error', 'La note doit être entre 1 et 5.');
            return $this->redirectToRoute('user_product_show', ['id' => $id]);
        }

        $avis = $this->avisProduitRepository->findOneByProduitAndUser($produit, $user);
        if ($avis === null) {
            $avis = new AvisProduit();
            $avis->setProduit($produit);
            $avis->setUser($user);
            $this->entityManager->persist($avis);
        }
        $avis->setNote($note);

        $this->entityManager->flush();

        $this->recalculateNoteMoyenne($produit);

        $this->addFlash('success', 'Votre avis a été enregistré.');
        return $this->redirectToRoute('user_product_show', ['id' => $id]);
    }

    private function recalculateNoteMoyenne(Produit $produit): void
    {
        $avisList = $produit->getAvisProduits();
        if ($avisList->isEmpty()) {
            $produit->setNoteMoyenne(0);
            $produit->setNbAvis(0);
        } else {
            $somme = 0;
            foreach ($avisList as $a) {
                $somme += $a->getNote();
            }
            $produit->setNoteMoyenne(round($somme / $avisList->count(), 2));
            $produit->setNbAvis($avisList->count());
        }
        $this->entityManager->flush();
    }
}
