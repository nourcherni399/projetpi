<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Cart;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Service\CartSessionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * À la connexion : fusionne le panier session (invité) dans le panier DB de l'utilisateur.
 */
final class CartMergeOnLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartSessionService $cartSessionService,
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => ['onInteractiveLogin', 0],
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if ($cart === null) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
        }

        $this->cartSessionService->mergeIntoUserCart($user, $cart);
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
