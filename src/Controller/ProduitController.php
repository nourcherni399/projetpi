<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Traits\FuzzyProductSearchTrait;
use App\Entity\Produit;
use App\Enum\StatutPublication;
use App\Form\ProduitType;
use App\Repository\CartItemRepository;
use App\Repository\LigneCommandeRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProduitHistoriqueRepository;
use App\Repository\ProduitRepository;
use App\Repository\StockRepository;
use App\Service\AIPredictionService;
use App\Service\ProduitHistoriqueService;
use App\Service\ProductDescriptionSuggestionService;
use App\Service\ProductPriceSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/produits')]
final class ProduitController extends AbstractController
{
    use FuzzyProductSearchTrait;

    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly LigneCommandeRepository $ligneCommandeRepository,
        private readonly OrderItemRepository $orderItemRepository,
        private readonly CartItemRepository $cartItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly AIPredictionService $aiPredictionService,
        private readonly ProductPriceSearchService $priceSearchService,
        private readonly ProduitHistoriqueService $produitHistoriqueService,
        private readonly ProduitHistoriqueRepository $produitHistoriqueRepository,
        private readonly ProductDescriptionSuggestionService $descriptionSuggestionService,
        private readonly StockRepository $stockRepository,
    ) {
    }

    #[Route('', name: 'admin_produit_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sortBy', 'nom');
        $sortOrder = $request->query->get('sortOrder', 'asc');

        $produits = $this->produitRepository->findBy([], ['id' => 'ASC']);

        if ($search !== null && $search !== '') {
            $searchTerm = trim($search);
            $produits = array_filter($produits, function ($produit) use ($searchTerm) {
                return $this->fuzzySearchMatch($searchTerm, $produit);
            });
        }

        $produits = $this->applySort($produits, $sortBy, $sortOrder);

        $stats = $this->getProduitStats();

        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produits,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'stats' => $stats,
        ]);
    }

    /**
     * Applique le tri simple sur les produits.
     *
     * @param Produit[] $produits
     * @return Produit[]
     */
    private function applySort(array $produits, string $sortBy, string $sortOrder): array
    {
        $order = $sortOrder === 'desc' ? -1 : 1;
        usort($produits, function (Produit $a, Produit $b) use ($sortBy, $order): int {
            $cmp = match ($sortBy) {
                'prix' => ((float) ($a->getPrix() ?? 0)) <=> ((float) ($b->getPrix() ?? 0)),
                default => strcasecmp($a->getNom() ?? '', $b->getNom() ?? ''),
            };
            return $cmp * $order;
        });
        return $produits;
    }

    #[Route('/prediction', name: 'admin_produit_prediction', methods: ['GET'])]
    public function prediction(): Response
    {
        $report = $this->aiPredictionService->fullReport();
        $predictions = $report['predictions'];
        $needStock = array_values(array_filter($predictions, fn ($r) => ($r['stock_to_order'] ?? 0) > 0));

        return $this->render('admin/produit/prediction.html.twig', [
            'predictions' => $predictions,
            'byLabel' => $report['byLabel'],
            'byStrategic' => $report['byStrategic'],
            'ca_estime_annee' => $report['ca_estime_annee'],
            'annee_cible' => $report['annee_cible'],
            'total_ventes_prevu_mois' => $report['total_ventes_prevu_mois'],
            'mois_cible' => $report['mois_cible'],
            'needStock' => $needStock,
        ]);
    }

    #[Route('/export-excel', name: 'admin_produit_export_excel', methods: ['GET'])]
    public function exportExcel(): Response
    {
        $produits = $this->produitRepository->findBy([], ['nom' => 'ASC']);
        $filename = 'export_produits_' . (new \DateTimeImmutable())->format('Y-m-d_His') . '.xlsx';

        $spreadsheet = $this->buildExcelSpreadsheet($produits);

        $response = new StreamedResponse(static function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * @param list<Produit> $produits
     */
    private function buildExcelSpreadsheet(array $produits): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produits');

        $headers = ['ID', 'Nom', 'Description', 'Catégorie', 'Prix (DT)', 'Stock', 'Quantité', 'Valeur totale du stock', 'Disponible'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('A7C7E7');
        $sheet->getStyle('A1:I1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:I1')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        $row = 2;
        foreach ($produits as $p) {
            $stock = $p->getStock();
            $sheet->setCellValue('A' . $row, $p->getId());
            $sheet->setCellValue('B' . $row, $p->getNom() ?? '');
            $sheet->setCellValue('C' . $row, $p->getDescription() ?? '');
            $sheet->setCellValue('D' . $row, $p->getCategorie() ? $p->getCategorie()->label() : '');
            $sheet->setCellValue('E' . $row, $p->getPrix() !== null ? (float) $p->getPrix() : '');
            $sheet->setCellValue('F' . $row, $stock ? $stock->getNom() : '');
            $sheet->setCellValue('G' . $row, $p->getQuantite());
            $sheet->setCellValue('I' . $row, $p->isDisponibilite() ? 'Oui' : 'Non');
            $sheet->setCellValue('H' . $row, '=E' . $row . '*G' . $row);
            $row++;
        }

        $dataLastRow = $row - 1;
        $sheet->getStyle('E2:E' . $dataLastRow)->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('H2:H' . $dataLastRow)->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        $condRouge = new Conditional();
        $condRouge->setConditionType(Conditional::CONDITION_CELLIS);
        $condRouge->setOperatorType(Conditional::OPERATOR_EQUAL);
        $condRouge->addCondition(0);
        $condRouge->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFCCCB');
        $condRouge->setPriority(1);

        $condOrange = new Conditional();
        $condOrange->setConditionType(Conditional::CONDITION_CELLIS);
        $condOrange->setOperatorType(Conditional::OPERATOR_LESSTHAN);
        $condOrange->addCondition(5);
        $condOrange->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFA500');
        $condOrange->setPriority(2);

        $condVert = new Conditional();
        $condVert->setConditionType(Conditional::CONDITION_CELLIS);
        $condVert->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL);
        $condVert->addCondition(5);
        $condVert->getStyle()->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('90EE90');
        $condVert->setPriority(3);

        $sheet->getStyle('G2:G' . $dataLastRow)->setConditionalStyles([$condRouge, $condOrange, $condVert]);

        $lastRow = $row;
        $sheet->setCellValue('A' . $lastRow, 'Total');
        $sheet->setCellValue('B' . $lastRow, count($produits) . ' produit(s) exporté(s)');
        $sheet->setCellValue('H' . $lastRow, '=SUM(H2:H' . $dataLastRow . ')');
        $sheet->getStyle('A' . $lastRow . ':I' . $lastRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $lastRow . ':I' . $lastRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F5F1EB');
        $sheet->getStyle('H' . $lastRow)->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        foreach (range('A', 'I') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /**
     * @return array{total: int, disponibles: int, indisponibles: int, parCategorie: list<array{categorie: string, nombre: int}>}
     */
    private function getProduitStats(): array
    {
        $total = $this->produitRepository->count([]);
        $disponibles = $this->produitRepository->count(['disponibilite' => true]);
        $indisponibles = $total - $disponibles;
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p.categorie', 'COUNT(p.id) as nombre')
            ->from(Produit::class, 'p')
            ->groupBy('p.categorie');
        $rows = $qb->getQuery()->getResult();
        $parCategorie = [];
        foreach ($rows as $row) {
            $categorie = $row['categorie'];
            $parCategorie[] = [
                'categorie' => $categorie ? $categorie->label() : 'Non défini',
                'nombre' => (int) $row['nombre'],
            ];
        }

        return [
            'total' => $total,
            'disponibles' => $disponibles,
            'indisponibles' => $indisponibles,
            'parCategorie' => $parCategorie,
        ];
    }

    #[Route('/new', name: 'admin_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $produit = new Produit();
        $produit->setStatutPublication(StatutPublication::PUBLIE);
        $form = $this->createForm(ProduitType::class, $produit, ['show_statut_publication' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $errors = $this->validateProduitForm($form, $produit, true, $request);

            if (!empty($errors)) {
                $this->addFormErrorsFromCodes($form, $errors);
                return $this->render('admin/produit/new.html.twig', [
                    'produit' => $produit,
                    'form' => $form,
                ]);
            }

            if ($form->isValid()) {
                $imageFile = $form->get('image')->getData();
                if ($imageFile) {
                    $saved = $this->handleImageUpload($imageFile, $produit);
                    if (!$saved) {
                        return $this->render('admin/produit/new.html.twig', [
                            'produit' => $produit,
                            'form' => $form,
                        ]);
                    }
                }
                $produit->setValide(true);
                $this->entityManager->persist($produit);
                $this->entityManager->flush();
                $produit->setSku('PRD-' . str_pad((string) $produit->getId(), 6, '0', STR_PAD_LEFT));
                $this->entityManager->flush();
                $this->addFlash('success', 'Ajouté avec succès.');
                return $this->redirectToRoute('admin_produit_index');
            }
        }

        return $this->render('admin/produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_produit_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Produit $produit): Response
    {
        $historique = $this->produitHistoriqueRepository->findByProduitOrderByCreatedDesc($produit);
        return $this->render('admin/produit/show.html.twig', [
            'produit' => $produit,
            'historique' => $historique,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_produit_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit): Response
    {
        $user = $this->getUser();
        $oldPrix = $produit->getPrix();
        $oldQuantite = $produit->getQuantite();
        $oldStatut = $produit->getStatutPublication();
        $oldDispo = $produit->isDisponibilite();

        // Corriger référence invalide (stock_id=0 ou Stock supprimé)
        $this->ensureProduitHasValidStock($produit);

        $form = $this->createForm(ProduitType::class, $produit, ['show_statut_publication' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $errors = $this->validateProduitForm($form, $produit, false, $request);

            if (!empty($errors)) {
                $this->addFormErrorsFromCodes($form, $errors);
                return $this->render('admin/produit/edit.html.twig', [
                    'produit' => $produit,
                    'form' => $form,
                ]);
            }

            if ($form->isValid()) {
                if ($user instanceof \App\Entity\User) {
                    if ((string) $oldPrix !== (string) $produit->getPrix()) {
                        $this->produitHistoriqueService->log($produit, $user, 'prix', (string) $oldPrix, (string) $produit->getPrix());
                    }
                    if ($oldQuantite !== $produit->getQuantite()) {
                        $this->produitHistoriqueService->log($produit, $user, 'quantite', (string) $oldQuantite, (string) $produit->getQuantite());
                    }
                    if ($oldStatut !== $produit->getStatutPublication()) {
                        $this->produitHistoriqueService->log($produit, $user, 'statutPublication', $oldStatut->label(), $produit->getStatutPublication()->label());
                    }
                    if ($oldDispo !== $produit->isDisponibilite()) {
                        $this->produitHistoriqueService->log($produit, $user, 'disponibilite', $oldDispo ? 'Oui' : 'Non', $produit->isDisponibilite() ? 'Oui' : 'Non');
                    }
                }
                $imageFile = $form->get('image')->getData();
                if ($imageFile) {
                    $saved = $this->handleImageUpload($imageFile, $produit);
                    if (!$saved) {
                        return $this->render('admin/produit/edit.html.twig', [
                            'produit' => $produit,
                            'form' => $form,
                        ]);
                    }
                }
                $this->entityManager->flush();
                $this->addFlash('success', 'Le produit a été modifié avec succès.');
                return $this->redirectToRoute('admin_produit_show', ['id' => $produit->getId()]);
            }
        }

        return $this->render('admin/produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    private function ensureProduitHasValidStock(Produit $produit): void
    {
        $conn = $this->entityManager->getConnection();
        $row = $conn->fetchAssociative('SELECT stock_id FROM produit WHERE id = ?', [$produit->getId()]);
        $stockId = $row['stock_id'] ?? null;

        $needsFix = $stockId === null || $stockId === 0 || $stockId === '0';
        if (!$needsFix) {
            $stockExists = $this->stockRepository->find((int) $stockId);
            if ($stockExists !== null) {
                return;
            }
            $needsFix = true;
        }

        $firstStock = $this->stockRepository->findOneBy([], ['id' => 'ASC']);
        if ($firstStock === null) {
            $this->addFlash('error', 'Aucun stock n\'existe. Créez d\'abord un stock dans la gestion des stocks pour pouvoir modifier ce produit.');
            throw $this->createNotFoundException('Aucun stock existant.');
        }

        $produit->setStock($firstStock);
        $this->entityManager->flush();
        $this->addFlash('info', 'La référence au stock de ce produit a été corrigée (stock invalide ou supprimé).');
    }

    #[Route('/suggerer-description', name: 'admin_produit_suggerer_description', methods: ['POST'])]
    public function suggererDescription(Request $request): JsonResponse
    {
        $nom = trim((string) ($request->request->get('nom') ?? ''));
        if ($nom === '') {
            return new JsonResponse(['error' => 'Veuillez saisir le nom du produit'], 400);
        }

        if (!$this->descriptionSuggestionService->isConfigured()) {
            return new JsonResponse(['error' => 'CHAT_API_KEY non configurée'], 503);
        }

        $description = $this->descriptionSuggestionService->suggestDescription($nom);
        if ($description === null) {
            return new JsonResponse(['error' => 'Impossible de générer une suggestion'], 500);
        }

        return new JsonResponse(['description' => $description]);
    }

    #[Route('/search-images-global', name: 'admin_produit_search_images_global', methods: ['POST'])]
    public function searchImagesGlobal(Request $request): JsonResponse
    {
        $query = $request->request->get('query', '');
        
        if (empty($query)) {
            return new JsonResponse(['error' => 'Requête vide'], 400);
        }
        
        try {
            $searchResults = $this->priceSearchService->searchProduct($query);
            
            $images = [];
            if (!empty($searchResults['results'])) {
                foreach ($searchResults['results'] as $result) {
                    if (!empty($result['image_url'])) {
                        $images[] = [
                            'url' => $result['image_url'],
                            'source' => $result['source'] ?? 'Web',
                            'title' => $result['name'] ?? $query,
                        ];
                    }
                }
            }
            
            $uniqueImages = [];
            $seenUrls = [];
            foreach ($images as $img) {
                if (!in_array($img['url'], $seenUrls)) {
                    $seenUrls[] = $img['url'];
                    $uniqueImages[] = $img;
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'images' => array_slice($uniqueImages, 0, 8),
                'query' => $query,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la recherche: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/search-images', name: 'admin_produit_search_images', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function searchImages(Request $request, Produit $produit): JsonResponse
    {
        $query = $request->request->get('query', $produit->getNom());
        
        if (empty($query)) {
            return new JsonResponse(['error' => 'Requête vide'], 400);
        }
        
        try {
            $searchResults = $this->priceSearchService->searchProduct($query);
            
            $images = [];
            if (!empty($searchResults['results'])) {
                foreach ($searchResults['results'] as $result) {
                    if (!empty($result['image_url'])) {
                        $priceDisplay = isset($result['price_tnd']) 
                            ? number_format($result['price_tnd'], 2, ',', ' ') . ' DT'
                            : null;
                        
                        $images[] = [
                            'url' => $result['image_url'],
                            'source' => $result['source'] ?? 'Web',
                            'price' => $priceDisplay,
                            'title' => $result['name'] ?? $query,
                        ];
                    }
                }
            }
            
            $uniqueImages = [];
            $seenUrls = [];
            foreach ($images as $img) {
                if (!in_array($img['url'], $seenUrls)) {
                    $seenUrls[] = $img['url'];
                    $uniqueImages[] = $img;
                }
            }
            
            return new JsonResponse([
                'success' => true,
                'images' => array_slice($uniqueImages, 0, 8),
                'query' => $query,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la recherche: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/apply-external-image', name: 'admin_produit_apply_external_image', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function applyExternalImage(Request $request, Produit $produit): JsonResponse
    {
        $imageUrl = $request->request->get('image_url');
        
        if (empty($imageUrl)) {
            return new JsonResponse(['error' => 'URL d\'image requise'], 400);
        }
        
        try {
            $imageContent = @file_get_contents($imageUrl);
            if ($imageContent === false) {
                return new JsonResponse(['error' => 'Impossible de télécharger l\'image'], 400);
            }
            
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);
            
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            
            $safeFilename = $this->slugger->slug($produit->getNom());
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/produits';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filePath = $uploadDir . '/' . $newFilename;
            file_put_contents($filePath, $imageContent);
            
            $produit->setImage('uploads/produits/' . $newFilename);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'image_path' => 'uploads/produits/' . $newFilename,
                'message' => 'Image appliquée avec succès',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/valider', name: 'admin_produit_valider', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function valider(Request $request, Produit $produit): Response
    {
        if (!$this->isCsrfTokenValid('valider' . $produit->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_produit_edit', ['id' => $produit->getId()]);
        }
        
        $produit->setValide(true);
        $produit->setStatutPublication(StatutPublication::PUBLIE);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Le produit "' . $produit->getNom() . '" a été validé et est maintenant visible.');
        return $this->redirectToRoute('admin_produit_index');
    }

    #[Route('/{id}/confirm-delete', name: 'admin_produit_confirm_delete', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function confirmDelete(Produit $produit): Response
    {
        return $this->render('admin/produit/confirm_delete.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}', name: 'admin_produit_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Produit $produit): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $produit->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_produit_index');
        }
        $this->removeProduitFromCommandes($produit);
        $this->removeProduitFromOrders($produit);
        $this->removeProduitFromCartItems($produit);
        $this->entityManager->remove($produit);
        $this->entityManager->flush();
        $this->addFlash('success', 'Le produit a été retiré des commandes, paniers et supprimé avec succès.');
        return $this->redirectToRoute('admin_produit_index');
    }

    #[Route('/bulk/delete', name: 'admin_produit_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $ids = $request->request->all('ids');

        if (empty($ids) || !$this->isCsrfTokenValid('bulk_delete', $request->request->get('_token'))) {
            return $this->redirectToRoute('admin_produit_index');
        }
        $produits = $this->produitRepository->findBy(['id' => $ids]);
        $deleted = 0;
        foreach ($produits as $produit) {
            $this->removeProduitFromCommandes($produit);
            $this->removeProduitFromOrders($produit);
            $this->removeProduitFromCartItems($produit);
            $this->entityManager->remove($produit);
            $deleted++;
        }
        $this->entityManager->flush();
        if ($deleted > 0) {
            $this->addFlash('success', $deleted . ' produit(s) retiré(s) des commandes, paniers et supprimé(s) avec succès.');
        }
        return $this->redirectToRoute('admin_produit_index');
    }

    private function removeProduitFromCommandes(Produit $produit): void
    {
        $lignes = $this->ligneCommandeRepository->findByProduit($produit);
        foreach ($lignes as $ligne) {
            $commande = $ligne->getCommande();
            if ($commande) {
                $commande->removeLigne($ligne);
                $newTotal = 0.0;
                foreach ($commande->getLignes() as $l) {
                    $newTotal += (float) ($l->getSousTotal() ?? 0);
                }
                $commande->setTotal($newTotal);
            }
            $this->entityManager->remove($ligne);
        }
    }

    private function removeProduitFromOrders(Produit $produit): void
    {
        $items = $this->orderItemRepository->findByProduit($produit);
        foreach ($items as $item) {
            $order = $item->getOrder();
            if ($order) {
                $order->removeItem($item);
                $newTotal = 0.0;
                foreach ($order->getItems() as $i) {
                    $newTotal += $i->getTotalPrice();
                }
                $order->setTotalPrice($newTotal);
            }
            $this->entityManager->remove($item);
        }
    }

    private function removeProduitFromCartItems(Produit $produit): void
    {
        $items = $this->cartItemRepository->findByProduit($produit);
        foreach ($items as $item) {
            $cart = $item->getCart();
            if ($cart) {
                $cart->removeItem($item);
            }
            $this->entityManager->remove($item);
        }
    }

    /**
     * @return list<string>
     */
    private function validateProduitForm($form, Produit $produit, bool $requireImage, Request $request): array
    {
        $errors = [];
        $data = $form->getData();
        $dataArray = $request->request->all('produit') ?? [];
        $dataArray = is_array($dataArray) ? $dataArray : [];

        $nom = isset($dataArray['nom']) ? trim((string) $dataArray['nom']) : '';
        if ($nom === '') {
            $errors[] = 'nom_vide';
        } elseif (is_numeric($nom)) {
            $errors[] = 'nom_nombre';
        } elseif (strlen($nom) < 3) {
            $errors[] = 'nom_court';
        } elseif (strlen($nom) > 255) {
            $errors[] = 'nom_long';
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-\'àâäéèêëïîöôùûçÀÂÄÉÈÊËÏÎÖÔÙÛÇ.,]+$/', $nom)) {
            $errors[] = 'nom_caracteres';
        }

        $description = isset($dataArray['description']) ? trim((string) $dataArray['description']) : '';
        if ($description === '') {
            $errors[] = 'description_vide';
        } elseif (strlen($description) < 3) {
            $errors[] = 'description_courte';
        } elseif (strlen($description) > 1000) {
            $errors[] = 'description_longue';
        }

        $prixVal = isset($dataArray['prix']) ? $dataArray['prix'] : null;
        if ($prixVal === null || $prixVal === '') {
            $errors[] = 'prix_vide';
        } elseif (!is_numeric($prixVal)) {
            $errors[] = 'prix_non_numerique';
        } elseif ((float) $prixVal < 0) {
            $errors[] = 'prix_negatif';
        } elseif ((float) $prixVal > 999999.99) {
            $errors[] = 'prix_trop_eleve';
        }

        if (empty($dataArray['categorie'])) {
            $errors[] = 'categorie_vide';
        }

        $imageFile = $form->get('image')->getData();
        if ($requireImage && !$imageFile && !$produit->getImage()) {
            $errors[] = 'image_manquante';
        }
        if ($imageFile) {
            if ($imageFile->getSize() > 5 * 1024 * 1024) {
                $errors[] = 'image_trop_grosse';
            }
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                $errors[] = 'image_format_invalide';
            }
            $imageSize = @getimagesize($imageFile->getPathname());
            if ($imageSize !== false) {
                [$width, $height] = $imageSize;
                if ($width < 100 || $height < 100) {
                    $errors[] = 'image_trop_petite';
                }
                if ($width > 2000 || $height > 2000) {
                    $errors[] = 'image_trop_grande';
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<string> $errorCodes
     */
    private function addFormErrorsFromCodes($form, array $errorCodes): void
    {
        $messages = [
            'nom_vide' => ['nom', 'Le nom est requis.'],
            'nom_nombre' => ['nom', 'Le nom ne peut pas être un nombre.'],
            'nom_court' => ['nom', 'Le nom doit faire au moins 3 caractères.'],
            'nom_long' => ['nom', 'Le nom est trop long.'],
            'nom_caracteres' => ['nom', 'Le nom contient des caractères non autorisés.'],
            'description_vide' => ['description', 'La description est requise.'],
            'description_courte' => ['description', 'La description doit faire au moins 3 caractères.'],
            'description_longue' => ['description', 'La description est trop longue.'],
            'prix_vide' => ['prix', 'Le prix est requis.'],
            'prix_non_numerique' => ['prix', 'Le prix doit être un nombre.'],
            'prix_negatif' => ['prix', 'Le prix doit être positif ou nul.'],
            'prix_trop_eleve' => ['prix', 'Le prix est trop élevé.'],
            'categorie_vide' => ['categorie', 'La catégorie est requise.'],
            'image_manquante' => ['image', 'L\'image est requise.'],
            'image_trop_grosse' => ['image', 'L\'image ne doit pas dépasser 5 Mo.'],
            'image_format_invalide' => ['image', 'Format d\'image invalide.'],
            'image_trop_petite' => ['image', 'L\'image doit faire au moins 100×100 px.'],
            'image_trop_grande' => ['image', 'L\'image ne doit pas dépasser 2000×2000 px.'],
        ];
        foreach ($errorCodes as $code) {
            if (isset($messages[$code])) {
                [$field, $message] = $messages[$code];
                $form->get($field)->addError(new FormError($message));
            }
        }
    }

    private function handleImageUpload($imageFile, Produit $produit): bool
    {
        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        try {
            $dir = $this->getParameter('uploads_directory');
            $imageFile->move($dir, $newFilename);
            $produit->setImage('uploads/produits/' . $newFilename);
            return true;
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de l\'image.');
            return false;
        }
    }

    private function downloadExternalImage(string $imageUrl, string $productName): ?string
    {
        try {
            $imageContent = @file_get_contents($imageUrl);
            if ($imageContent === false) {
                return null;
            }
            
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);
            
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => 'jpg',
            };
            
            $safeFilename = $this->slugger->slug($productName);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $extension;
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/produits';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filePath = $uploadDir . '/' . $newFilename;
            file_put_contents($filePath, $imageContent);
            
            return 'uploads/produits/' . $newFilename;
        } catch (\Exception $e) {
            return null;
        }
    }

}