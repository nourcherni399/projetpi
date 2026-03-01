<?php

declare(strict_types=1);

namespace App\Controller\Traits;

use App\Entity\Produit;

trait FuzzyProductSearchTrait
{
    /**
     * Recherche avancée : accepte les mots avec lettre manquante ou incorrecte (fuzzy).
     */
    private function fuzzySearchMatch(string $searchTerm, Produit $produit): bool
    {
        $search = strtolower(trim($searchTerm));
        if ($search === '') {
            return true;
        }

        // Prix : correspondance exacte uniquement
        if (preg_match('/^[\d.,\s]+$/', $search)) {
            if (str_contains((string) ($produit->getPrix() ?? ''), $search)) {
                return true;
            }
        }

        $fields = [
            $produit->getNom() ?? '',
            $produit->getDescription() ?? '',
            $produit->getCategorie()?->label() ?? '',
        ];

        foreach ($fields as $text) {
            $text = strtolower($text);
            if ($text === '') {
                continue;
            }

            // 1. Correspondance exacte (comportement classique type LIKE)
            if (str_contains($text, $search)) {
                return true;
            }

            // 2. Recherche fuzzy par mot : tolère 1 faute par 3 caractères
            $searchWords = preg_split('/[\s\-]+/u', $search, -1, PREG_SPLIT_NO_EMPTY);
            $textWords = preg_split('/[\s\-]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

            $allWordsMatch = true;
            foreach ($searchWords as $sw) {
                if (mb_strlen($sw) < 2) {
                    if (str_contains($text, $sw)) {
                        continue;
                    }
                    $allWordsMatch = false;
                    break;
                }

                $found = false;
                $swLower = mb_strtolower($sw);
                foreach ($textWords as $tw) {
                    if (mb_strlen($tw) < 2) {
                        continue;
                    }
                    $twLower = mb_strtolower($tw);
                    similar_text($swLower, $twLower, $percent);
                    if ($percent >= 70) {
                        $found = true;
                        break;
                    }
                    if (strlen($sw) === mb_strlen($sw) && strlen($tw) === mb_strlen($tw)) {
                        $lev = levenshtein($swLower, $twLower);
                        $maxLen = max(mb_strlen($sw), mb_strlen($tw));
                        if ($lev <= max(1, (int) ceil($maxLen / 3))) {
                            $found = true;
                            break;
                        }
                    }
                }
                if (!$found) {
                    $allWordsMatch = false;
                    break;
                }
            }
            if ($allWordsMatch && !empty($searchWords)) {
                return true;
            }
        }

        return false;
    }
}