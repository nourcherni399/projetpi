<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\InscritEventsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class InscriptionNotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        $globals = [
            'admin_pending_inscriptions' => [],
            'user_inscription_notifications' => [],
        ];

        $request = $this->requestStack->getCurrentRequest();
        $route = $request?->attributes->get('_route', '');
        $isAdmin = $route !== null && str_starts_with((string) $route, 'admin_');

        if ($isAdmin) {
            $globals['admin_pending_inscriptions'] = $this->inscritEventsRepository->findPendingOrderByDate();
        }

        $user = $this->security->getUser();
        if ($user !== null && !$isAdmin) {
            $globals['user_inscription_notifications'] = $this->inscritEventsRepository->findAccepteOrRefuseForUser($user);
        }

        return $globals;
    }
}
