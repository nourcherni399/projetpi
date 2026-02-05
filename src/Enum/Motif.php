<?php

declare(strict_types=1);

namespace App\Enum;

/** Motif de consultation pour un rendez-vous. */
enum Motif: string
{
    case URGENCE = 'urgence';
    case SUIVIE = 'suivie';
    case NORMAL = 'normal';
}
