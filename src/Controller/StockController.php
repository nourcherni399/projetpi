<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/stocks')]
final class StockController extends AbstractController
{
    public function __construct(
        private readonly StockRepository $stockRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_stock_index', methods: ['GET'])]
    public function index(): Response
    {
        $stocks = $this->stockRepository->findAll();
        return $this->render('admin/stock/index.html.twig', ['stocks' => $stocks]);
    }

    #[Route('/new', name: 'admin_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $produit = $stock->getProduit();
            if ($produit !== null) {
                $produit->setStock($stock);
            }
            $this->entityManager->persist($stock);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le stock a été créé avec succès.');

            return $this->redirectToRoute('admin_stock_index');
        }

        return $this->render('admin/stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }
}
