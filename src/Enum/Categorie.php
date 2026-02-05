<?php

declare(strict_types=1);

namespace App\Enum;

/** Énumération des catégories de produits (diagramme UML). */
enum Categorie: string
{
    case SENSORIELS = 'sensoriels';
    case BRUIT_ET_ENVIRONNEMENT = 'bruit_et_environnement';
    case EDUCATION_APPRENTISSAGE = 'education_apprentissage';
    case COMMUNICATION_LANGAGE = 'communication_langage';
    case JEUX_THERAPEUTIQUES_DEVELOPPEMENT = 'jeux_therapeutiques_developpement';
    case BIEN_ETRE_RELAXATION = 'bien_etre_relaxation';
    case VIE_QUOTIDIENNE = 'vie_quotidienne';

    public function label(): string
    {
        return match ($this) {
            self::SENSORIELS => 'Sensoriels',
            self::BRUIT_ET_ENVIRONNEMENT => 'Bruit et environnement',
            self::EDUCATION_APPRENTISSAGE => 'Education & apprentissage',
            self::COMMUNICATION_LANGAGE => 'Communication & langage',
            self::JEUX_THERAPEUTIQUES_DEVELOPPEMENT => 'Jeux thérapeutiques & développement',
            self::BIEN_ETRE_RELAXATION => 'Bien-être & relaxation',
            self::VIE_QUOTIDIENNE => 'Vie quotidienne',
        };
    }
}
