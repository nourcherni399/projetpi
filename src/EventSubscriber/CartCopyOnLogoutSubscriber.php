<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\CartRepository;
use App\Service\CartSessionService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * À la déconnexion : copie le panier DB de l'utilisateur dans la session pour qu'il le retrouve en invité.
 */
final class CartCopyOnLogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartRepository $cartRepository,
        private readonly CartSessionService $cartSessionService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => ['onLogout', 0],
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token === null) {
            return;
        }
        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if ($cart !== null && !$cart->isEmpty()) {
            $this->cartSessionService->copyFromUserCart($cart);
        }
    }
}
