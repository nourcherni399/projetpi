<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        if ($user->isActive() === false) {
            throw new CustomUserMessageAuthenticationException('Votre compte est désactivé. Contactez l’équipe pour le réactiver.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}