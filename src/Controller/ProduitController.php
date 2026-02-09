<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
<<<<<<< HEAD
use Symfony\Component\String\Slugger\SluggerInterface;
=======
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770

#[Route('/admin/produits')]
final class ProduitController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
<<<<<<< HEAD
        private readonly SluggerInterface $slugger,
=======
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
    ) {
    }

    #[Route('', name: 'admin_produit_index', methods: ['GET'])]
    public function index(): Response
    {
        $produits = $this->produitRepository->findBy([], ['nom' => 'ASC']);
        return $this->render('admin/produit/index.html.twig', ['produits' => $produits]);
    }

    #[Route('/new', name: 'admin_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
<<<<<<< HEAD
            $imageFile = $form->get('image')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                
                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $produit->setImage('uploads/produits/'.$newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }
            
=======
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
            $this->entityManager->persist($produit);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le produit a été créé avec succès.');

            return $this->redirectToRoute('admin_produit_index');
        }

        return $this->render('admin/produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }
<<<<<<< HEAD

    #[Route('/{id}', name: 'admin_produit_show', methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        return $this->render('admin/produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_produit_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                
                try {
                    $imageFile->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $produit->setImage('uploads/produits/'.$newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
                }
            }
            
            $this->entityManager->flush();
            $this->addFlash('success', 'Le produit a été modifié avec succès.');

            return $this->redirectToRoute('admin_produit_show', ['id' => $produit->getId()]);
        }

        return $this->render('admin/produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/confirm-delete', name: 'admin_produit_confirm_delete', methods: ['GET'])]
    public function confirmDelete(Produit $produit): Response
    {
        return $this->render('admin/produit/confirm_delete.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}', name: 'admin_produit_delete', methods: ['POST'])]
    public function delete(Request $request, Produit $produit): Response
    {
        if ($this->isCsrfTokenValid('delete'.$produit->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($produit);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le produit a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_produit_index');
    }

    #[Route('/bulk/delete', name: 'admin_produit_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $ids = $request->request->all('ids');
        
        if (!empty($ids) && $this->isCsrfTokenValid('bulk_delete', $request->request->get('_token'))) {
            $produits = $this->produitRepository->findBy(['id' => $ids]);
            
            foreach ($produits as $produit) {
                $this->entityManager->remove($produit);
            }
            
            $this->entityManager->flush();
            $this->addFlash('success', count($produits) . ' produit(s) supprimé(s) avec succès.');
        }

        return $this->redirectToRoute('admin_produit_index');
    }
=======
>>>>>>> 72089269acfd37b80d1154606c1f9a5afd193770
}
