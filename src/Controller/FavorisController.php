<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Favoris;
use App\Entity\Produit;
use App\Repository\FavorisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/favoris', name: 'favoris_')]
final class FavorisController extends AbstractController
{
    public function __construct(
        private readonly FavorisRepository $favorisRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $favoris = $this->favorisRepository->findByUser($user);

        return $this->render('front/favoris/index.html.twig', [
            'favoris' => $favoris,
        ]);
    }

    #[Route('/ajouter/{id}', name: 'add', methods: ['POST'])]
    public function add(Produit $produit, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Vérifier si le produit est déjà dans les favoris
        $existingFavori = $this->favorisRepository->findOneByUserAndProduit($user, $produit);
        
        if ($existingFavori) {
            $this->addFlash('info', 'Ce produit est déjà dans vos favoris.');
            return $this->redirectToRoute('user_products_index');
        }

        // Créer le nouveau favori
        $favori = new Favoris();
        $favori->setUser($user);
        $favori->setProduit($produit);

        $this->entityManager->persist($favori);
        $this->entityManager->flush();

        $this->addFlash('success', 'Produit ajouté aux favoris!');
        
        // Rediriger vers la page d'origine ou la liste des produits
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }
        
        return $this->redirectToRoute('user_products_index');
    }

    #[Route('/supprimer/{id}', name: 'remove', methods: ['POST'])]
    public function remove(Produit $produit, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $favori = $this->favorisRepository->findOneByUserAndProduit($user, $produit);
        
        if (!$favori) {
            $this->addFlash('error', 'Ce produit n\'est pas dans vos favoris.');
            return $this->redirectToRoute('favoris_index');
        }

        $this->entityManager->remove($favori);
        $this->entityManager->flush();

        $this->addFlash('success', 'Produit retiré des favoris!');
        
        // Rediriger vers la page d'origine ou la liste des favoris
        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, $request->getHost())) {
            return $this->redirect($referer);
        }
        
        return $this->redirectToRoute('favoris_index');
    }
}
