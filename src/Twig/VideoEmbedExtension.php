<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig pour convertir les URLs YouTube/Vimeo en URLs d'embed.
 * Les balises <video> ne supportent pas YouTube/Vimeo ; il faut utiliser des iframes.
 */
final class VideoEmbedExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('video_embed_url', $this->getEmbedUrl(...)),
        ];
    }

    /**
     * Retourne l'URL d'embed si l'URL est YouTube ou Vimeo, null sinon.
     */
    public function getEmbedUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        // YouTube : youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID
        if (preg_match('#(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#i', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Vimeo : vimeo.com/ID, player.vimeo.com/video/ID
        if (preg_match('#(?:vimeo\.com/|player\.vimeo\.com/video/)(\d+)#i', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }
}
