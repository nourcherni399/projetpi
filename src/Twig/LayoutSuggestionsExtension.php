<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\LayoutSuggestionsService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fournit layout_suggestions() pour la bande du layout utilisateur.
 * Retourne les suggestions personnalisées (produits, événements) ou null si non connecté.
 */
final class LayoutSuggestionsExtension extends AbstractExtension
{
    public function __construct(
        private readonly LayoutSuggestionsService $layoutSuggestionsService,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('layout_suggestions', [$this, 'getLayoutSuggestions']),
        ];
    }

    /**
     * Retourne les suggestions pour l'utilisateur connecté, ou null sinon.
     *
     * @return array{productSuggestions: list<object>, eventSuggestions: list<object>, doctorSuggestions: list<object>}|null
     */
    public function getLayoutSuggestions(): ?array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->layoutSuggestionsService->getSuggestionsForUser($user);
    }
}
