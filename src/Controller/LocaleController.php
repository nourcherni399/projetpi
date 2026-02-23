<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blog')]
final class LocaleController extends AbstractController
{
    public const SUPPORTED_LOCALES = ['fr', 'en', 'es', 'de', 'it', 'ar', 'pt', 'nl', 'ru', 'zh', 'ja', 'ko', 'tr', 'pl', 'sv', 'vi', 'id', 'th', 'uk'];

    #[Route('/locale/{locale}', name: 'blog_set_locale', requirements: ['locale' => 'fr|en|es|de|it|ar|pt|nl|ru|zh|ja|ko|tr|pl|sv|vi|id|th|uk'], methods: ['GET'])]
    public function setLocale(string $locale, Request $request): Response
    {
        $request->getSession()->set('blog_locale', $locale);
        $referer = $request->headers->get('referer');

        return $this->redirect($referer && str_contains($referer, $request->getHost()) ? $referer : $this->generateUrl('user_blog'));
    }
}
