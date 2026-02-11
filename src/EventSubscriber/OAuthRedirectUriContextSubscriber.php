<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Force le contexte du routeur depuis APP_URL pour que l'URI de redirection OAuth
 * (ex. Google) soit toujours celle configurée dans la console du fournisseur,
 * évitant l'erreur redirect_uri_mismatch (localhost vs 127.0.0.1, etc.).
 */
final class OAuthRedirectUriContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ?string $appUrl,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 33],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($this->appUrl === null || $this->appUrl === '') {
            return;
        }

        $parts = parse_url($this->appUrl);
        if ($parts === false || !isset($parts['host'])) {
            return;
        }

        $context = $this->router->getContext();
        $context->setScheme($parts['scheme'] ?? 'http');
        $context->setHost($parts['host']);

        $port = $parts['port'] ?? null;
        if ($port !== null) {
            if (($parts['scheme'] ?? 'http') === 'https') {
                $context->setHttpsPort($port);
            } else {
                $context->setHttpPort($port);
            }
        }
    }
}
