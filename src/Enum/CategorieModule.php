<?php

declare(strict_types=1);

namespace App\Enum;

/** Énumération des catégories de modules. */
enum CategorieModule: string
{
    case EMPTY = '';
    case COMPRENDRE_TSA = 'COMPRENDRE_TSA';
    case AUTONOMIE = 'AUTONOMIE';
    case COMMUNICATION = 'COMMUNICATION';
    case EMOTIONS = 'EMOTIONS';
    case VIE_QUOTIDIENNE = 'VIE_QUOTIDIENNE';
    case ACCOMPAGNEMENT = 'ACCOMPAGNEMENT';

    public function label(): string
    {
        return match ($this) {
            self::EMPTY => '',
            self::COMPRENDRE_TSA => 'Comprendre le TSA',
            self::AUTONOMIE => 'Autonomie',
            self::COMMUNICATION => 'Communication',
            self::EMOTIONS => 'Émotions',
            self::VIE_QUOTIDIENNE => 'Vie quotidienne',
            self::ACCOMPAGNEMENT => 'Accompagnement',
        };
    }
}
