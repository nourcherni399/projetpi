<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProduitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ancien contrôleur produit public.
 * La liste des produits est désormais gérée par ProductController (route /produits, name: user_products_index).
 */
#[Route('/produits')]
final class ProduitPublicController extends AbstractController
{
    public function __construct(
        private readonly ProduitRepository $produitRepository,
    ) {
    }
}
