<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Niveau de difficulté pour une thématique. */
enum NiveauDifficulte: string
{
    case DEBUTANT = 'Débutant';
    case INTERMEDIAIRE = 'Intermédiaire';
    case AVANCE = 'Avancé';
}
