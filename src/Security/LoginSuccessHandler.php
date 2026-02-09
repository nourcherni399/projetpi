<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('home'));
        }

        $role = $user->getRole();

        if ($role === UserRole::ADMIN) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        if ($role === UserRole::MEDECIN) {
            return new RedirectResponse($this->urlGenerator->generate('doctor_dashboard'));
        }

        // Patient ou Parent : priorité au _target_path (inscription événement, etc.)
        $targetPath = $request->request->get('_target_path') ?? $request->query->get('_target_path');
        if (\is_string($targetPath) && $targetPath !== '' && str_starts_with($targetPath, '/') && !str_starts_with($targetPath, '//')) {
            return new RedirectResponse($targetPath);
        }
        if ($targetPath = $this->getTargetPath($request->getSession(), 'main')) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('home'));
    }
}
