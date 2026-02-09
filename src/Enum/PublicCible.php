<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/** Public cible : qui peut rejoindre / à qui s'adresse une thématique ou un événement. */
enum PublicCible: string
{
    case ENFANT = 'Enfant';
    case PARENT = 'Parent';
    case MEDECIN = 'Médecin';
    case EDUCATEUR = 'Éducateur';
    case AIDANT = 'Aidant';
    case AUTRE = 'Autre';
}
