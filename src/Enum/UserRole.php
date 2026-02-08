<?php

namespace App\Enum;

enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case PARENT = 'ROLE_PARENT';
    case PATIENT = 'ROLE_PATIENT';
    case MEDECIN = 'ROLE_MEDECIN';
    case USER = 'ROLE_USER';
}
