<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Notification;
use App\Repository\CartRepository;
use App\Repository\InscritEventsRepository;
use App\Repository\NotificationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class InscriptionNotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly ManagerRegistry $managerRegistry,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly CartRepository $cartRepository,
    ) {
    }

    public function getGlobals(): array
    {
        $globals = [
            'admin_pending_inscriptions' => [],
            'user_inscription_notifications' => [],
            'user_rdv_notifications' => [],
            'app_cart_count' => 0,
        ];

        $request = $this->requestStack->getCurrentRequest();
        $route = $request?->attributes->get('_route', '');
        $isAdmin = $route !== null && str_starts_with((string) $route, 'admin_');

        if ($isAdmin) {
            $globals['admin_pending_inscriptions'] = $this->inscritEventsRepository->findPendingOrderByDate();
        }

        $user = $this->security->getUser();
        if ($user !== null && !$isAdmin) {
            $inscriptions = $this->inscritEventsRepository->findAccepteRefuseOuEnAttenteForUser($user);
            $globals['user_inscription_notifications'] = $this->buildInscriptionNotificationMessages($inscriptions);
            $globals['user_rdv_notifications'] = $this->buildRdvNotificationMessages($user, $this->managerRegistry->getRepository(Notification::class));
            $cart = $this->cartRepository->findOneBy(['user' => $user]);
            $globals['app_cart_count'] = $cart !== null ? $cart->getTotalItems() : 0;
        }

        return $globals;
    }

    /**
     * Construit les messages de notification d'inscription aux événements (texte + type pour le style et icône).
     * @param array<int, object> $inscriptions
     * @return list<array{message: string, type: string, eventId: int}>
     */
    private function buildInscriptionNotificationMessages(array $inscriptions): array
    {
        $result = [];
        foreach ($inscriptions as $inscrit) {
            $evenement = $inscrit->getEvenement();
            $titre = $evenement !== null ? $evenement->getTitle() ?? 'Événement' : 'Événement';
            $eventId = $evenement !== null ? (int) $evenement->getId() : 0;
            $statut = $inscrit->getStatut();
            if ($statut === 'accepte') {
                $result[] = [
                    'message' => sprintf('Votre inscription à « %s » a été acceptée.', $titre),
                    'type' => 'acceptee',
                    'eventId' => $eventId,
                ];
            } elseif ($statut === 'en_attente') {
                $result[] = [
                    'message' => sprintf('Votre inscription à « %s » est en liste d\'attente.', $titre),
                    'type' => 'liste_attente',
                    'eventId' => $eventId,
                ];
            } else {
                $result[] = [
                    'message' => sprintf('Votre inscription à « %s » a été refusée.', $titre),
                    'type' => 'refusee',
                    'eventId' => $eventId,
                ];
            }
        }
        return $result;
    }

    /**
     * Construit les messages de notification de rendez-vous (accepté / refusé) pour l'utilisateur.
     *
     * @return list<array{message: string, type: string, link: string|null}>
     */
    private function buildRdvNotificationMessages(object $user, NotificationRepository $notificationRepository): array
    {
        $notifications = $notificationRepository->findRdvForDestinataireOrderByCreatedDesc($user, 15);
        $result = [];
        foreach ($notifications as $n) {
            $type = $n->getType();
            $rv = $n->getRendezVous();
            $label = $rv && $rv->getDateRdv() ? $rv->getDateRdv()->format('d/m/Y') : 'Rendez-vous';
            if ($type === Notification::TYPE_RDV_ACCEPTE) {
                $result[] = [
                    'message' => sprintf('Votre demande de rendez-vous du %s a été acceptée.', $label),
                    'type' => 'rdv_accepte',
                    'link' => null,
                ];
            } elseif ($type === Notification::TYPE_RDV_REFUSE) {
                $result[] = [
                    'message' => sprintf('Votre demande de rendez-vous du %s a été refusée.', $label),
                    'type' => 'rdv_refuse',
                    'link' => null,
                ];
            }
        }
        return $result;
    }
}
