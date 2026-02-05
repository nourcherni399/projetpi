<?php

namespace App\Entity\Enum;

enum Niveau: string
{
    case DIFFICILE = 'difficile';
    case MOYEN = 'moyen';
    case FACILE = 'facile';
}