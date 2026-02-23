<?php

declare(strict_types=1);

namespace App\Service;

final class ProfanityFilterService
{
    /** @var list<string>|null */
    private ?array $badWords = null;

    public function __construct(
        private readonly string $badWordsFile = '',
    ) {
    }

    public function containsBadWords(string $text): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $words = $this->getBadWords();
        $textLower = mb_strtolower($text, 'UTF-8');

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }
            $pattern = '/\b' . preg_quote($word, '/') . '\b/ui';
            if (preg_match($pattern, $textLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function getBadWords(): array
    {
        if ($this->badWords !== null) {
            return $this->badWords;
        }

        $words = [];

        if ($this->badWordsFile !== '' && is_readable($this->badWordsFile)) {
            $lines = file($this->badWordsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line !== '' && str_starts_with($line, '#') === false) {
                        $words[] = $line;
                    }
                }
            }
        }

        $this->badWords = $words;

        return $this->badWords;
    }
}
