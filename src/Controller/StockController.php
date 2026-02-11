<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

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

    #[Route('/{id}/edit', name: 'admin_stock_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock): Response
    {
        $form = $this->createFormBuilder($stock)
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'constraints' => [
                    new NotBlank(message: 'La quantité est obligatoire.'),
                    new PositiveOrZero(message: 'La quantité doit être positive ou nulle.'),
                ],
                'attr' => [
                    'class' => 'mt-1 block w-full rounded-lg border border-[#E5E0D8] bg-white px-4 py-2.5 text-[#4B5563] focus:outline focus:ring-2 focus:ring-[#A7C7E7]',
                    'min' => 0,
                ],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Le stock a été modifié avec succès.');
            return $this->redirectToRoute('admin_stock_index');
        }

        return $this->render('admin/stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_stock_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Stock $stock): Response
    {
        if ($this->isCsrfTokenValid('delete_stock_' . $stock->getId(), (string) $request->request->get('_token'))) {
            $produit = $stock->getProduit();
            if ($produit !== null) {
                $produit->setStock(null);
            }
            $this->entityManager->remove($stock);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le stock a été supprimé.');
        }
        return $this->redirectToRoute('admin_stock_index');
    }
}
