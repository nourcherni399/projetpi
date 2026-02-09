<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $role = $user->getRole();
        $route = match ($role?->value) {
            'ROLE_ADMIN' => 'admin_dashboard',
            'ROLE_MEDECIN' => 'doctor_dashboard',
            default => 'home',
        };

        return new Response('', Response::HTTP_FOUND, [
            'Location' => $this->urlGenerator->generate($route),
        ]);
    }
}
