<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Produit;
use App\Form\ProduitType;
use App\Repository\LigneCommandeRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/produits')]
final class ProduitController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly LigneCommandeRepository $ligneCommandeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    #[Route('', name: 'admin_produit_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sortBy', 'nom');
        $sortOrder = $request->query->get('sortOrder', 'asc');

        $orderBy = [];
        if ($sortBy === 'prix') {
            $orderBy['prix'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        } else {
            $orderBy['nom'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        }

        $produits = $this->produitRepository->findBy([], $orderBy);

        if ($search !== null && $search !== '') {
            $searchTerm = strtolower(trim($search));
            $produits = array_filter($produits, function ($produit) use ($searchTerm) {
                $nomMatch = strpos(strtolower($produit->getNom() ?? ''), $searchTerm) !== false;
                $descriptionMatch = $produit->getDescription() && strpos(strtolower($produit->getDescription()), $searchTerm) !== false;
                $categorieMatch = $produit->getCategorie() && strpos(strtolower($produit->getCategorie()->label()), $searchTerm) !== false;
                $prixMatch = strpos((string) $produit->getPrix(), $searchTerm) !== false;

                return $nomMatch || $descriptionMatch || $categorieMatch || $prixMatch;
            });
        }

        $stats = $this->getProduitStats();
        $produitIdsAvecCommandes = $this->ligneCommandeRepository->getProduitIdsAvecCommandes();

        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produits,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'stats' => $stats,
            'produitIdsAvecCommandes' => $produitIdsAvecCommandes,
        ]);
    }

    #[Route('/export-excel', name: 'admin_produit_export_excel', methods: ['GET'])]
    public function exportExcel(): Response
    {
        $produits = $this->produitRepository->findBy([], ['nom' => 'ASC']);
        $filename = 'export_produits_' . (new \DateTimeImmutable())->format('Y-m-d_His') . '.csv';

        $response = new Response($this->buildCsvContent($produits));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * @param list<Produit> $produits
     */
    private function buildCsvContent(array $produits): string
    {
        $out = fopen('php://temp', 'r+');
        fprintf($out, "\xEF\xBB\xBF"); // BOM UTF-8 for Excel
        fputcsv($out, ['ID', 'Nom', 'Description', 'Catégorie', 'Prix (د.ت)', 'Disponible'], ';');
        foreach ($produits as $p) {
            fputcsv($out, [
                $p->getId(),
                $p->getNom() ?? '',
                $p->getDescription() ?? '',
                $p->getCategorie() ? $p->getCategorie()->label() : '',
                $p->getPrix() !== null ? number_format((float) $p->getPrix(), 2, ',', ' ') : '',
                $p->isDisponibilite() ? 'Oui' : 'Non',
            ], ';');
        }
        fputcsv($out, ['Total', count($produits) . ' produit(s) exporté(s)', '', '', '', ''], ';');
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return $content;
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
        $form = $this->createForm(ProduitType::class, $produit);
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

                $this->entityManager->persist($produit);
                $this->entityManager->flush();
                $this->addFlash('success', 'Le produit a été créé avec succès.');
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
        return $this->render('admin/produit/show.html.twig', [
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_produit_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Produit $produit): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
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
        if ($this->ligneCommandeRepository->countByProduit($produit) > 0) {
            $this->addFlash('error', 'Ce produit ne peut pas être supprimé car il est présent dans une ou plusieurs commandes.');
            return $this->redirectToRoute('admin_produit_index');
        }
        $this->entityManager->remove($produit);
        $this->entityManager->flush();
        $this->addFlash('success', 'Le produit a été supprimé avec succès.');
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
        $blocked = [];
        foreach ($produits as $produit) {
            if ($this->ligneCommandeRepository->countByProduit($produit) > 0) {
                $blocked[] = $produit->getNom();
                continue;
            }
            $this->entityManager->remove($produit);
            $deleted++;
        }
        $this->entityManager->flush();
        if ($deleted > 0) {
            $this->addFlash('success', $deleted . ' produit(s) supprimé(s) avec succès.');
        }
        if ($blocked !== []) {
            $this->addFlash('error', 'Produit(s) non supprimé(s) (présents dans des commandes) : ' . implode(', ', $blocked) . '.');
        }
        return $this->redirectToRoute('admin_produit_index');
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
}