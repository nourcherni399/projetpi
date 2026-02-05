<?php

declare(strict_types=1);

namespace App\Enum;

/** Jour de la semaine pour les disponibilités. */
enum Jour: string
{
    case LUNDI = 'lundi';
    case MARDI = 'mardi';
    case MERCREDI = 'mercredi';
    case JEUDI = 'jeudi';
    case VENDREDI = 'vendredi';
    case SAMEDI = 'samedi';
    case DIMANCHE = 'dimanche';
}
