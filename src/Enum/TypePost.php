<?php

namespace App\Entity\Enum;

enum TypePost: string
{
    case RECOMMANDATION = 'recommandation';
    case PLAINTE = 'plainte';
    case QUESTION = 'question';
    case EXPERIENCE = 'experience';
}