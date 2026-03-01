<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Évite les avertissements Chrome "import map rule was removed, conflicted with...":
 * 1. Supprime les link[rel="modulepreload"] (pré-résolution des modules)
 * 2. Nettoie l'import map (garde seulement bare specifiers)
 * 3. Supprime les import map et scripts import 'app' en doublon (garde le 1er seulement)
 */
final class ImportMapPreloadStripSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if (false === $content) {
            return;
        }

        $modified = false;

        // 1. Supprime <link rel="modulepreload" href="...">
        if (str_contains($content, 'modulepreload')) {
            $stripped = preg_replace(
                '/<link\s[^>]*rel=["\']modulepreload["\'][^>]*>\s*/i',
                '',
                $content
            );
            if ($stripped !== null) {
                $content = $stripped;
                $modified = true;
            }
        }

        // 2. Nettoie l'import map : garde seulement les bare specifiers (app, @symfony/...), retire /assets/...
        $cleanedContent = preg_replace_callback(
            '/<script[^>]*type=["\']importmap["\'][^>]*>([\s\S]*?)<\/script>/i',
            function (array $m): string {
                $json = json_decode(trim($m[1]), true);
                if (!\is_array($json) || !isset($json['imports']) || !\is_array($json['imports'])) {
                    return $m[0];
                }
                $cleaned = [];
                $seenUrls = [];
                foreach ($json['imports'] as $specifier => $url) {
                    // Garde uniquement les bare specifiers (app, @hotwired/..., @symfony/...)
                    // Retire : http://, https://, /assets/...
                    if (str_starts_with($specifier, 'http://') || str_starts_with($specifier, 'https://') || str_starts_with($specifier, '/')) {
                        continue;
                    }
                    if (isset($seenUrls[$url])) {
                        continue;
                    }
                    $seenUrls[$url] = true;
                    $cleaned[$specifier] = $url;
                }
                $json['imports'] = $cleaned;
                $newJson = json_encode($json, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG | \JSON_PRETTY_PRINT);
                return str_replace($m[1], "\n            " . trim($newJson) . "\n            ", $m[0]);
            },
            $content
        );
        if ($cleanedContent !== null && $cleanedContent !== $content) {
            $content = $cleanedContent;
            $modified = true;
        }

        // 3. Supprime les doublons : garde uniquement le 1er import map et le 1er script import 'app'
        $firstImportMap = true;
        $firstImportApp = true;
        $beforeDedup = $content;
        $content = preg_replace_callback(
            '/<script([^>]*)>([\s\S]*?)<\/script>/i',
            function (array $m) use (&$firstImportMap, &$firstImportApp): string {
                $attrs = $m[1];
                $inner = $m[2];
                if (preg_match('/type=["\']importmap["\']/i', $attrs)) {
                    if ($firstImportMap) {
                        $firstImportMap = false;
                        return $m[0];
                    }
                    return '';
                }
                if (preg_match('/type=["\']module["\']/i', $attrs) && !str_contains($attrs, 'src=') && preg_match('/import\s+[\'"]app[\'"]/i', trim($inner))) {
                    if ($firstImportApp) {
                        $firstImportApp = false;
                        return $m[0];
                    }
                    return '';
                }
                return $m[0];
            },
            $content
        );
        if ($content !== null && $content !== $beforeDedup) {
            $modified = true;
        }

        if ($modified) {
            $response->setContent($content);
        }
    }
}
