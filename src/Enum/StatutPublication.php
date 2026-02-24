<?php

declare(strict_types=1);

namespace App\Enum;

/** État de publication d'un produit. */
enum StatutPublication: string
{
    case BROUILLON = 'brouillon';
    case PUBLIE = 'publie';

    public function label(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::PUBLIE => 'Publié',
        };
    }
}
