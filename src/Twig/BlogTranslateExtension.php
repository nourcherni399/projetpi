<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\LibreTranslateService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class BlogTranslateExtension extends AbstractExtension
{
    public function __construct(
        private readonly LibreTranslateService $libreTranslate,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('blog_translate', $this->translate(...), ['is_safe' => ['html']]),
            new TwigFilter('blog_translate_type', $this->translateType(...), ['is_safe' => ['html']]),
        ];
    }

    public function translate(?string $text, ?string $targetLocale = null): string
    {
        $text = trim($text ?? '');
        if ($text === '') {
            return '';
        }

        $locale = $targetLocale ?? $this->requestStack->getCurrentRequest()?->getSession()?->get('blog_locale', 'fr');
        if ($locale === 'fr') {
            return $text;
        }

        return $this->libreTranslate->translate($text, 'fr', $locale);
    }

    /** Traduit le type d'article (recommandation, plainte, question, experience) depuis le français. */
    public function translateType(string $typeKey): string
    {
        $labels = [
            'recommandation' => 'Recommandation',
            'plainte' => 'Plainte',
            'question' => 'Question',
            'experience' => 'Expérience',
        ];
        $text = $labels[$typeKey] ?? ucfirst($typeKey);

        return $this->translate($text);
    }
}
