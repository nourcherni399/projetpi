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
=======
use Symfony\Component\String\Slugger\SluggerInterface;
>>>>>>> origin/integreModule

#[Route('/admin/produits')]
final class ProduitController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
        private readonly EntityManagerInterface $entityManager,
<<<<<<< HEAD
=======
        private readonly SluggerInterface $slugger,
>>>>>>> origin/integreModule
    ) {
    }

    #[Route('', name: 'admin_produit_index', methods: ['GET'])]
<<<<<<< HEAD
    public function index(): Response
    {
        $produits = $this->produitRepository->findBy([], ['nom' => 'ASC']);
        return $this->render('admin/produit/index.html.twig', ['produits' => $produits]);
=======
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sortBy', 'nom'); // 'nom' ou 'prix'
        $sortOrder = $request->query->get('sortOrder', 'asc'); // 'asc' ou 'desc'
        
        // Définir l'ordre de tri
        $orderBy = [];
        if ($sortBy === 'prix') {
            $orderBy['prix'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        } else {
            $orderBy['nom'] = $sortOrder === 'desc' ? 'DESC' : 'ASC';
        }
        
        $produits = $this->produitRepository->findBy([], $orderBy);
        
        // Filtrer par recherche si nécessaire
        if ($search !== null && $search !== '') {
            $searchTerm = strtolower(trim($search));
            $produits = array_filter($produits, function($produit) use ($searchTerm) {
                $nomMatch = strpos(strtolower($produit->getNom()), $searchTerm) !== false;
                $descriptionMatch = $produit->getDescription() && strpos(strtolower($produit->getDescription()), $searchTerm) !== false;
                $categorieMatch = $produit->getCategorie() && strpos(strtolower($produit->getCategorie()->label()), $searchTerm) !== false;
                $prixMatch = strpos((string)$produit->getPrix(), $searchTerm) !== false;
                
                return $nomMatch || $descriptionMatch || $categorieMatch || $prixMatch;
            });
        }
        
        return $this->render('admin/produit/index.html.twig', [
            'produits' => $produits,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
>>>>>>> origin/integreModule
    }

    #[Route('/new', name: 'admin_produit_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

<<<<<<< HEAD
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($produit);
            $this->entityManager->flush();
            $this->addFlash('success', 'Le produit a été créé avec succès.');

            return $this->redirectToRoute('admin_produit_index');
=======
        if ($form->isSubmitted()) {
            // Contrôles de saisie PHP personnalisés
            $errors = [];
            
            // Récupérer les données du formulaire
            $data = $request->request->all('produit');
            
            // Contrôle du nom
            if (empty($data['nom'])) {
                $errors[] = 'nom_vide';
            } elseif (is_numeric($data['nom'])) {
                $errors[] = 'nom_nombre';
            } elseif (strlen($data['nom']) < 3) {
                $errors[] = 'nom_court';
            } elseif (strlen($data['nom']) > 255) {
                $errors[] = 'nom_long';
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-\'àâäéèêëïîöôùûçÀÂÄÉÈÊËÏÎÖÔÙÛÇ]+$/', $data['nom'])) {
                $errors[] = 'nom_caracteres';
            }
            
            // Contrôle de la description
            if (empty($data['description'])) {
                $errors[] = 'description_vide';
            } elseif (strlen($data['description']) < 10) {
                $errors[] = 'description_courte';
            } elseif (strlen($data['description']) > 1000) {
                $errors[] = 'description_longue';
            }
            
            // Contrôle du prix
            if (empty($data['prix'])) {
                $errors[] = 'prix_vide';
            } elseif (!is_numeric($data['prix'])) {
                $errors[] = 'prix_non_numerique';
            } elseif ((float)$data['prix'] <= 0) {
                $errors[] = 'prix_negatif';
            } elseif ((float)$data['prix'] > 999999.99) {
                $errors[] = 'prix_trop_eleve';
            }
            
            // Contrôle de la catégorie
            if (empty($data['categorie'])) {
                $errors[] = 'categorie_vide';
            }
            
            // Contrôle de la disponibilité
            if (!isset($data['disponibilite'])) {
                $errors[] = 'disponibilite_vide';
            }
            
            // Contrôle de l'image
            $imageFile = $form->get('image')->getData();
            if (!$imageFile && !$produit->getImage()) {
                $errors[] = 'image_manquante';
            } elseif ($imageFile) {
                // Vérifier la taille de l'image (max 5MB)
                if ($imageFile->getSize() > 5 * 1024 * 1024) {
                    $errors[] = 'image_trop_grosse';
                }
                
                // Vérifier le type de l'image
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                    $errors[] = 'image_format_invalide';
                }
                
                // Vérifier les dimensions de l'image (min 100x100, max 2000x2000)
                $imageSize = getimagesize($imageFile->getPathname());
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
            
            // Si il y a des erreurs, les afficher et ne pas continuer
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    switch ($error) {
                        case 'nom_vide':
                            $this->addFlash('error', 'nom_vide');
                            break;
                        case 'nom_nombre':
                            $this->addFlash('error', 'nom_nombre');
                            break;
                        case 'nom_court':
                            $this->addFlash('error', 'nom_court');
                            break;
                        case 'nom_long':
                            $this->addFlash('error', 'nom_long');
                            break;
                        case 'nom_caracteres':
                            $this->addFlash('error', 'nom_caracteres');
                            break;
                        case 'description_vide':
                            $this->addFlash('error', 'description_vide');
                            break;
                        case 'description_courte':
                            $this->addFlash('error', 'description_courte');
                            break;
                        case 'description_longue':
                            $this->addFlash('error', 'description_longue');
                            break;
                        case 'prix_vide':
                            $this->addFlash('error', 'prix_vide');
                            break;
                        case 'prix_non_numerique':
                            $this->addFlash('error', 'prix_non_numerique');
                            break;
                        case 'prix_negatif':
                            $this->addFlash('error', 'prix_negatif');
                            break;
                        case 'prix_trop_eleve':
                            $this->addFlash('error', 'prix_trop_eleve');
                            break;
                        case 'categorie_vide':
                            $this->addFlash('error', 'categorie_vide');
                            break;
                        case 'disponibilite_vide':
                            $this->addFlash('error', 'disponibilite_vide');
                            break;
                        case 'image_manquante':
                            $this->addFlash('error', 'image_manquante');
                            break;
                        case 'image_trop_grosse':
                            $this->addFlash('error', 'image_trop_grosse');
                            break;
                        case 'image_format_invalide':
                            $this->addFlash('error', 'image_format_invalide');
                            break;
                        case 'image_trop_petite':
                            $this->addFlash('error', 'image_trop_petite');
                            break;
                        case 'image_trop_grande':
                            $this->addFlash('error', 'image_trop_grande');
                            break;
                    }
                }
                
                return $this->render('admin/produit/new.html.twig', [
                    'produit' => $produit,
                    'form' => $form,
                    'flash_errors' => $errors,
                ]);
            }
            
            // Si le formulaire est valide, continuer avec le traitement normal
            if ($form->isValid()) {
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
>>>>>>> origin/integreModule
        }

        return $this->render('admin/produit/new.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }
<<<<<<< HEAD
=======

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

        if ($form->isSubmitted()) {
            // Contrôles de saisie PHP personnalisés
            $errors = [];
            
            // Récupérer les données du formulaire
            $data = $request->request->all('produit');
            
            // Contrôle du nom
            if (empty($data['nom'])) {
                $errors[] = 'nom_vide';
            } elseif (is_numeric($data['nom'])) {
                $errors[] = 'nom_nombre';
            } elseif (strlen($data['nom']) < 3) {
                $errors[] = 'nom_court';
            } elseif (strlen($data['nom']) > 255) {
                $errors[] = 'nom_long';
            } elseif (!preg_match('/^[a-zA-Z0-9\s\-\'àâäéèêëïîöôùûçÀÂÄÉÈÊËÏÎÖÔÙÛÇ]+$/', $data['nom'])) {
                $errors[] = 'nom_caracteres';
            }
            
            // Contrôle de la description
            if (empty($data['description'])) {
                $errors[] = 'description_vide';
            } elseif (strlen($data['description']) < 10) {
                $errors[] = 'description_courte';
            } elseif (strlen($data['description']) > 1000) {
                $errors[] = 'description_longue';
            }
            
            // Contrôle du prix
            if (empty($data['prix'])) {
                $errors[] = 'prix_vide';
            } elseif (!is_numeric($data['prix'])) {
                $errors[] = 'prix_non_numerique';
            } elseif ((float)$data['prix'] <= 0) {
                $errors[] = 'prix_negatif';
            } elseif ((float)$data['prix'] > 999999.99) {
                $errors[] = 'prix_trop_eleve';
            }
            
            // Contrôle de la catégorie
            if (empty($data['categorie'])) {
                $errors[] = 'categorie_vide';
            }
            
            // Contrôle de la disponibilité
            if (!isset($data['disponibilite'])) {
                $errors[] = 'disponibilite_vide';
            }
            
            // Contrôle de l'image (uniquement si nouvelle image uploadée)
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                // Vérifier la taille de l'image (max 5MB)
                if ($imageFile->getSize() > 5 * 1024 * 1024) {
                    $errors[] = 'image_trop_grosse';
                }
                
                // Vérifier le type de l'image
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
                    $errors[] = 'image_format_invalide';
                }
                
                // Vérifier les dimensions de l'image (min 100x100, max 2000x2000)
                $imageSize = getimagesize($imageFile->getPathname());
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
            
            // Si il y a des erreurs, les afficher et ne pas continuer
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    switch ($error) {
                        case 'nom_vide':
                            $this->addFlash('error', 'nom_vide');
                            break;
                        case 'nom_nombre':
                            $this->addFlash('error', 'nom_nombre');
                            break;
                        case 'nom_court':
                            $this->addFlash('error', 'nom_court');
                            break;
                        case 'nom_long':
                            $this->addFlash('error', 'nom_long');
                            break;
                        case 'nom_caracteres':
                            $this->addFlash('error', 'nom_caracteres');
                            break;
                        case 'description_vide':
                            $this->addFlash('error', 'description_vide');
                            break;
                        case 'description_courte':
                            $this->addFlash('error', 'description_courte');
                            break;
                        case 'description_longue':
                            $this->addFlash('error', 'description_longue');
                            break;
                        case 'prix_vide':
                            $this->addFlash('error', 'prix_vide');
                            break;
                        case 'prix_non_numerique':
                            $this->addFlash('error', 'prix_non_numerique');
                            break;
                        case 'prix_negatif':
                            $this->addFlash('error', 'prix_negatif');
                            break;
                        case 'prix_trop_eleve':
                            $this->addFlash('error', 'prix_trop_eleve');
                            break;
                        case 'categorie_vide':
                            $this->addFlash('error', 'categorie_vide');
                            break;
                        case 'disponibilite_vide':
                            $this->addFlash('error', 'disponibilite_vide');
                            break;
                        case 'image_trop_grosse':
                            $this->addFlash('error', 'image_trop_grosse');
                            break;
                        case 'image_format_invalide':
                            $this->addFlash('error', 'image_format_invalide');
                            break;
                        case 'image_trop_petite':
                            $this->addFlash('error', 'image_trop_petite');
                            break;
                        case 'image_trop_grande':
                            $this->addFlash('error', 'image_trop_grande');
                            break;
                    }
                }
                
                return $this->render('admin/produit/edit.html.twig', [
                    'produit' => $produit,
                    'form' => $form,
                    'flash_errors' => $errors,
                ]);
            }
            
            // Si le formulaire est valide, continuer avec le traitement normal
            if ($form->isValid()) {
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
>>>>>>> origin/integreModule
}
