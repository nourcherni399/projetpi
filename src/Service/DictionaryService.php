<?php

declare(strict_types=1);

namespace App\Service;

final class DictionaryService
{
    public function __construct(
        private readonly FreeDictionaryService $freeDictionaryService,
        private readonly WiktionaryService $wiktionaryService,
    ) {
    }

    /**
     * Récupère la définition d'un mot selon la langue du contenu.
     * - Anglais (en) : Free Dictionary API
     * - Français (fr) et autres : Wiktionary FR
     *
     * @return array{word: string, definition: string, phonetic: ?string, partOfSpeech: ?string, example: ?string, source: string}|null
     */
    public function getDefinition(string $word, string $locale = 'fr'): ?array
    {
        $locale = strtolower(trim($locale));
        if ($locale === 'en') {
            return $this->freeDictionaryService->getDefinition($word);
        }

        return $this->wiktionaryService->getDefinition($word);
    }
}