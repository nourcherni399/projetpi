<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DemandeProduit;
use App\Entity\Notification;
use App\Entity\Produit;
use App\Entity\Stock;
use App\Enum\Categorie;
use App\Repository\DemandeProduitRepository;
use App\Repository\StockRepository;
use App\Service\DemandeProduitEnrichmentService;
use App\Service\ProductAutoCreateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/demandes-produit')]
final class DemandeProduitController extends AbstractController
{
    public function __construct(
        private readonly DemandeProduitRepository $demandeProduitRepository,
        private readonly StockRepository $stockRepository,
        private readonly DemandeProduitEnrichmentService $enrichmentService,
        private readonly ProductAutoCreateService $productAutoCreateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $uploadsDirectory,
    ) {
    }

    #[Route('', name: 'admin_demande_produit_index', methods: ['GET'])]
    public function index(): Response
    {
        $demandes = $this->demandeProduitRepository->findAllOrdered();

        return $this->render('admin/demande_produit/index.html.twig', [
            'demandes' => $demandes,
        ]);
    }

    #[Route('/export/pdf', name: 'admin_demande_produit_export_pdf_list', methods: ['GET'])]
    public function exportPdfList(): Response
    {
        $demandes = $this->demandeProduitRepository->findAllOrdered();
        $html = $this->renderView('admin/demande_produit/pdf_list.html.twig', ['demandes' => $demandes]);
        return $this->streamPdf($html, 'demandes_produit_liste.pdf');
    }

    #[Route('/{id}', name: 'admin_demande_produit_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(DemandeProduit $demande): Response
    {
        return $this->render('admin/demande_produit/show.html.twig', [
            'demande' => $demande,
        ]);
    }

    #[Route('/{id}/creer-produit', name: 'admin_demande_produit_creer_produit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function creerProduit(Request $request, DemandeProduit $demande): Response
    {
        if ($demande->getProduit() !== null) {
            $this->addFlash('warning', 'Un produit a déjà été créé à partir de cette demande.');
            return $this->redirectToRoute('admin_demande_produit_show', ['id' => $demande->getId()]);
        }

        $stocks = $this->stockRepository->findBy([], ['nom' => 'ASC']);
        if (empty($stocks)) {
            $this->addFlash('error', 'Aucun stock disponible. Créez un stock avant de créer un produit.');
            return $this->redirectToRoute('admin_demande_produit_show', ['id' => $demande->getId()]);
        }

        $enriched = $this->enrichmentService->enrich($demande);
        $stockId = (int) $request->request->get('stock_id', $stocks[0]->getId());

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('creer_produit_' . $demande->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Token de sécurité invalide.');
                return $this->redirectToRoute('admin_demande_produit_creer_produit', ['id' => $demande->getId()]);
            }
            $stock = $this->stockRepository->find($stockId);
            if (!$stock) {
                $this->addFlash('error', 'Stock invalide.');
                return $this->redirectToRoute('admin_demande_produit_creer_produit', ['id' => $demande->getId()]);
            }

            $selectedImage = $request->request->get('selected_image', '');
            $imageUrl = null;
            $imagePath = null;
            
            if (!empty($selectedImage)) {
                if (str_starts_with($selectedImage, 'uploads/')) {
                    $imagePath = $selectedImage;
                } else {
                    $imageUrl = $selectedImage;
                }
            }

            $proposition = [
                'nom' => $enriched['nom'],
                'description' => $enriched['description'],
                'categorie' => $enriched['categorie'],
                'prix_estime' => $enriched['prix'],
                'donnees_externes' => $imageUrl ? ['image_url' => $imageUrl] : null,
                'image_from_search' => $imagePath,
            ];

            $produit = $this->productAutoCreateService->createFromProposition(
                $proposition,
                $stock,
                $this->getUser()
            );

            $produit->setGenereParIa(true);
            $produit->setValide(true);
            $this->entityManager->persist($produit);

            $demande->setProduit($produit);
            $demande->setStatut(DemandeProduit::STATUT_APPROUVE);
            $demande->setValidatedAt(new \DateTimeImmutable());
            $demande->setValidatedBy($this->getUser());
            $this->entityManager->flush();

            $demandeur = $demande->getDemandeur();
            if ($demandeur !== null) {
                $notif = new Notification();
                $notif->setDestinataire($demandeur);
                $notif->setType(Notification::TYPE_DEMANDE_PRODUIT_IA);
                $notif->setDemandeProduit($demande);
                $notif->setProduit($produit);
                $this->entityManager->persist($notif);
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Produit créé avec succès. Vous pouvez le modifier dans la gestion des produits.');
            return $this->redirectToRoute('admin_produit_show', ['id' => $produit->getId()]);
        }

        return $this->render('admin/demande_produit/creer_produit.html.twig', [
            'demande' => $demande,
            'enriched' => $enriched,
            'stocks' => $stocks,
        ]);
    }

    #[Route('/{id}/approuver', name: 'admin_demande_produit_approuver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approuver(Request $request, DemandeProduit $demande): Response
    {
        if (!$this->isCsrfTokenValid('approuver_demande_' . $demande->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_demande_produit_index');
        }

        if ($demande->getStatut() !== DemandeProduit::STATUT_EN_ATTENTE) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('admin_demande_produit_show', ['id' => $demande->getId()]);
        }

        $demande->setStatut(DemandeProduit::STATUT_APPROUVE);
        $demande->setValidatedAt(new \DateTimeImmutable());
        $demande->setValidatedBy($this->getUser());
        $this->entityManager->flush();

        $this->addFlash('success', 'Demande approuvée. Vous pouvez maintenant créer le produit.');
        return $this->redirectToRoute('admin_demande_produit_creer_produit', ['id' => $demande->getId()]);
    }

    #[Route('/{id}/rejeter', name: 'admin_demande_produit_rejeter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejeter(Request $request, DemandeProduit $demande): Response
    {
        if (!$this->isCsrfTokenValid('rejeter_demande_' . $demande->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_demande_produit_index');
        }

        if ($demande->getStatut() !== DemandeProduit::STATUT_EN_ATTENTE) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('admin_demande_produit_show', ['id' => $demande->getId()]);
        }

        $demande->setStatut(DemandeProduit::STATUT_REJETE);
        $demande->setValidatedAt(new \DateTimeImmutable());
        $demande->setValidatedBy($this->getUser());
        $this->entityManager->flush();

        $this->addFlash('success', 'Demande rejetée.');
        return $this->redirectToRoute('admin_demande_produit_index');
    }

    #[Route('/{id}/export/pdf', name: 'admin_demande_produit_export_pdf_fiche', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exportPdfFiche(DemandeProduit $demande): Response
    {
        $html = $this->renderView('admin/demande_produit/pdf_fiche.html.twig', ['demande' => $demande]);
        $filename = 'demande_produit_' . $demande->getId() . '_fiche.pdf';
        return $this->streamPdf($html, $filename);
    }

    private function streamPdf(string $html, string $filename): Response
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->addFlash('error', 'Export PDF indisponible : installez "composer require dompdf/dompdf"');
            return $this->redirectToRoute('admin_demande_produit_index');
        }

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        $dompdf->getOptions()->set('defaultFont', 'DejaVu Sans');
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        $response = new Response($pdf);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        return $response;
    }
}