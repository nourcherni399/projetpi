<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Fournit les planches du quiz Rorschach : planches officielles (photos) si présentes,
 * sinon génération SVG type tache d'encre en secours.
 * Les planches officielles (domaine public) peuvent être téléchargées depuis Wikimedia Commons.
 */
final class RorschachSvgService
{
    /** Extensions acceptées pour les planches statiques (ordre de priorité). */
    private const PLATE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'svg', 'webp'];

    /** Nuances de noir/gris pour la génération SVG de secours. */
    private const INK_COLORS = [
        '#1a1a1a',
        '#2d2d2d',
        '#252525',
        '#222222',
    ];

    public function __construct(
        private string $uploadDir,
        private string $platesDir
    ) {
    }

    /**
     * Retourne l'URL d'une planche Rorschach (1 à 4 pour le quiz).
     * Priorité : planche statique (photo officielle) dans $platesDir, sinon SVG généré.
     *
     * @param int $imageNumber Numéro de planche (1 à 4)
     * @return string|null URL relative (ex. /images/rorschach/plate_1.jpg ou /uploads/tsa_generated/rorschach_1_xxx.svg)
     */
    public function generate(int $imageNumber): ?string
    {
        $imageNumber = max(1, min(4, $imageNumber));
        $baseName = 'plate_' . $imageNumber;
        $dir = rtrim($this->platesDir, '/\\');

        if (is_dir($dir)) {
            foreach (self::PLATE_EXTENSIONS as $ext) {
                $path = $dir . '/' . $baseName . '.' . $ext;
                if (is_file($path)) {
                    return '/images/rorschach/' . $baseName . '.' . $ext;
                }
            }
        }

        return $this->generateSvgFallback($imageNumber);
    }

    private function generateSvgFallback(int $imageNumber): ?string
    {
        $seed = $imageNumber * 7919 + (int) (microtime(true) * 100);
        mt_srand($seed);
        $color = self::INK_COLORS[$imageNumber - 1] ?? self::INK_COLORS[0];
        $svg = $this->buildSvg($color);

        $dir = rtrim($this->uploadDir, '/\\');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'rorschach_' . $imageNumber . '_' . uniqid('', true) . '.svg';
        $path = $dir . '/' . $filename;
        if (file_put_contents($path, $svg) === false) {
            return null;
        }

        return '/uploads/tsa_generated/' . $filename;
    }

    private function buildSvg(string $fillColor): string
    {
        $w = 200;
        $h = 200;
        $cx = $w / 2;
        $cy = $h / 2;

        $paths = [];
        // Tache centrale symétrique (corps de l'encre)
        $paths[] = sprintf('<ellipse cx="%d" cy="%d" rx="%d" ry="%d" fill="%s"/>', $cx, $cy, 28, 45, $fillColor);
        // Formes gauches + miroir droite (au moins 8 paires pour un rendu type Rorschach)
        for ($i = 0; $i < 8; $i++) {
            $rx = 18 + mt_rand(0, 32);
            $ry = 18 + mt_rand(0, 38);
            $lx = 15 + mt_rand(0, (int) $cx - 50);
            $ly = 25 + mt_rand(0, $h - 50);
            $paths[] = sprintf('<ellipse cx="%d" cy="%d" rx="%d" ry="%d" fill="%s"/>', $lx, $ly, $rx, $ry, $fillColor);
            $paths[] = sprintf('<ellipse cx="%d" cy="%d" rx="%d" ry="%d" fill="%s"/>', $w - $lx, $ly, $rx, $ry, $fillColor);
        }
        $pathStr = implode("\n    ", $paths);

        return <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}">
  <rect width="{$w}" height="{$h}" fill="#ffffff"/>
  {$pathStr}
</svg>
SVG;
    }
}
