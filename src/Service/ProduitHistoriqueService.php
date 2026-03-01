<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Produit;
use App\Entity\ProduitHistorique;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre les modifications de produits dans l'historique.
 */
class ProduitHistoriqueService
{
    private const LABELS = [
        'prix' => 'Prix',
        'quantite' => 'Quantité',
        'nom' => 'Nom',
        'description' => 'Description',
        'sku' => 'ID produit',
        'statutPublication' => 'Statut de publication',
        'disponibilite' => 'Disponibilité',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function log(Produit $produit, User $user, string $champ, ?string $ancienneValeur, ?string $nouvelleValeur): void
    {
        $h = new ProduitHistorique();
        $h->setProduit($produit);
        $h->setUser($user);
        $h->setChamp(self::LABELS[$champ] ?? $champ);
        $h->setAncienneValeur($ancienneValeur);
        $h->setNouvelleValeur($nouvelleValeur);
        $this->entityManager->persist($h);
    }
}

