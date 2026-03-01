<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sert les assets demandés sans digest (ex: controllers.js) en les résolvant
 * vers le fichier avec digest. Le loader Stimulus utilise une import relative
 * "./controllers.js" que le navigateur résout sans digest.
 */
final class AssetMapperWithoutDigestSubscriber implements EventSubscriberInterface
{
    private const ASSETS_PREFIX = '/assets/';

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priorité > 35 pour s'exécuter avant AssetMapperDevServerSubscriber
            KernelEvents::REQUEST => [['onKernelRequest', 36]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $pathInfo = rawurldecode($event->getRequest()->getPathInfo());

        if (!str_starts_with($pathInfo, self::ASSETS_PREFIX)) {
            return;
        }

        // Vérifie si un asset correspond au chemin sans digest
        $asset = null;
        foreach ($this->assetMapper->allAssets() as $candidate) {
            if ($pathInfo === $candidate->publicPathWithoutDigest) {
                $asset = $candidate;
                break;
            }
        }

        if (null === $asset) {
            return;
        }

        if (null !== $asset->content) {
            $response = new Response($asset->content);
        } else {
            $response = new BinaryFileResponse($asset->sourcePath, autoLastModified: false);
        }

        $response
            ->setPublic()
            ->setMaxAge(604800)
            ->setImmutable()
            ->setEtag($asset->digest);

        $extension = pathinfo($asset->publicPath, \PATHINFO_EXTENSION);
        $mimeTypes = [
            'js' => 'text/javascript',
            'mjs' => 'text/javascript',
            'css' => 'text/css',
        ];
        if (isset($mimeTypes[$extension])) {
            $response->headers->set('Content-Type', $mimeTypes[$extension]);
        }

        $response->headers->set('X-Assets-Dev', '1');

        $event->setResponse($response);
        $event->stopPropagation();
    }
}
