<?php

declare(strict_types=1);

namespace App\Enum;

/** Statut d'un rendez-vous. */
enum StatusRendezVous: string
{
    case EN_ATTENTE = 'en_attente';
    case CONFIRMER = 'confirmer';
    case ANNULER = 'annuler';
}
