<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Chatbot spécialisé TSA (Trouble du Spectre de l'Autisme) pour les patients.
 * Utilise Groq (prioritaire) ou Gemini. Génère les images automatiquement via HuggingFace ou Pexels.
 */
final class TsaChatService
{
    /** Thèmes TSA pour conversion IMAGE:X → prompt de génération */
    private const THEME_PROMPTS = [
        1 => 'soft pastel illustration emotions wheel or colorful feelings chart, gentle, child-friendly, autism awareness style',
        2 => 'visual schedule morning routine illustration, soft pastel colors, icons calendar, calm and organized',
        3 => 'calm sensory room soft lighting weighted blanket cozy corner, illustration style pastel peaceful',
        4 => 'family support parent and child gentle connection, soft illustration pastel, warmth and safety',
        5 => 'child independence daily life small wins illustration, soft colors positive and reassuring',
        6 => 'family bond parent child together quiet moment, illustration pastel warm supportive',
    ];

    /** Prompts d'images liés au projet AutiCare/TSA — quiz et exploration. Toujours style doux, bienveillant, pas dramatique. */
    private const TSA_IMAGE_PROMPTS_QUIZ = [
        'soft pastel illustration emotions faces happy calm sad gentle, child-friendly, simple shapes',
        'visual schedule or routine icons soft pastel colors, organized calm day, illustration',
        'calm space cozy corner soft lights sensory safe, illustration pastel peaceful',
        'family support parent and child reading or playing together, soft illustration warm',
        'nature calm garden soft green pastel, peaceful atmosphere illustration',
        'communication speech bubbles or connection symbols soft pastel illustration',
    ];

    private const SYSTEM_PROMPT_TSA = <<<'PROMPT'
Tu es l'assistant virtuel d'AutiCare, chaleureux et à l'écoute, spécialisé dans l'accompagnement des personnes avec TSA et leurs familles.

TON ET STYLE :
- Réponds toujours dans la MÊME LANGUE que le message du patient (français, arabe, anglais, etc.).
- Sois empathique, rassurant et bienveillant. Phrases courtes et claires. Évite le jargon.
- Ne pose jamais de diagnostic. Pour toute question médicale ou diagnostic, invite à consulter un professionnel de santé (médecin, psychiatre, etc.).
- Ne juge jamais. Valide les émotions et les expériences avant d'apporter un éclairage.

IMAGES ET EXPLORATION (projet AutiCare / TSA) :
- Les images doivent TOUJOURS être liées au thème du projet : TSA, émotions douces, sensoriel, routine, communication, soutien familial, espaces calmes. Style : illustration douce, pastel, bienveillante. Pas de scènes dramatiques, pas de personnages en détresse ou en tension.
- Pour IMAGE_PROMPT utilise UNIQUEMENT des descriptions en anglais qui correspondent à ce thème. Exemples valides : "soft pastel illustration emotions wheel gentle child-friendly", "visual schedule morning routine soft pastel calm", "calm sensory room soft lighting cozy corner illustration", "family support parent child gentle connection pastel warm", "nature calm garden soft green peaceful illustration". Évite : personnages qui crient/pleurent, scènes de conflit, poses dramatiques.
- Ne génère une image que si le patient demande une photo/image. Réponds en 1–2 phrases puis sur une NOUVELLE LIGNE : IMAGE_PROMPT: description en anglais (thème TSA/émotions douces). Tu ne vois pas l'image : n'en décris pas le contenu, invite seulement à regarder et dire ce qu'il voit ou ressent.
- Quand le patient décrit l'image : remercie, fais le lien bienveillant avec sensorialité/routine/communication. Ne montre jamais le texte IMAGE_PROMPT au patient.

QUIZ — Deux types possibles :

A) QUIZ TEXTUEL (questions / réponses) — si le patient demande "quiz texte", "quiz par questions", "je veux le quiz" sans mention d'images ni Rorschach :
- Lance un quiz de 4 à 5 questions. Une question à la fois. Dis qu'il n'y a pas de bonne ou mauvaise réponse.
- Thèmes : émotions, ce qui me calme, mes repères, ce que j'aime, ma journée. Exemples : "Qu'est-ce qui te aide à te calmer ?" / "Quelle émotion tu ressens le plus en ce moment ?" / "Qu'est-ce qui est important dans ta routine du matin ?" / "Une chose que tu as bien faite cette semaine ?"
- Après chaque réponse : remercie brièvement puis pose la question suivante. À la fin (4-5 réponses) : résumé bienveillant (2-3 phrases), sans jugement. Ne génère pas d'image.

B) QUIZ PAR IMAGES — inspiré du test de Rorschach (si le patient demande "quiz images", "Rorschach", "test de Rorschach", "quiz Rorschach", etc.) :
- Présente-le comme un temps d'exploration inspiré du Rorschach : on montre une image ABSTRAITE (type tache d'encre, symétrique, ambiguë), le patient dit ce qu'il voit ou ressent ; pas de bonne ou mauvaise réponse, juste l'expression libre.
- Tu montres 4 images (une à la fois). Chaque image doit être STYLE RORSCHACH : abstraite, symétrique (effet miroir), ambiguë, ouverte à l'interprétation. PAS de scène figurative (pas de personnages, pas d'objets reconnaissables). Couleurs douces, pastel. OBLIGATOIRE : terminer par IMAGE_PROMPT: abstract symmetrical inkblot soft pastel [thème] ambiguous open to interpretation (en anglais).
- L'ordre des thèmes est imposé par le contexte (émotions, routine, calme, lien). Après chaque réponse : valide avec bienveillance, puis envoie l'image SUIVANTE. À la fin : conclusion bienveillante, sans diagnostic.

RÉPONSES :
- Réponds en 2 à 5 phrases sauf si le patient demande plus de détails. Évite les pavés.
- Propose parfois une question ou une piste (ex. "Voulez-vous qu'on explore une image sur les émotions ?" ou "Faire un petit quiz pour mieux vous connaître ?").
PROMPT;

    public function __construct(
        private GroqChatService $groqChat,
        private GeminiChatService $geminiChat,
        private HuggingFaceImageService $hfImage,
        private PexelsImageService $pexelsImage,
        private RorschachSvgService $rorschachSvg
    ) {
    }

    /**
     * Envoie un message et retourne la réponse + image.
     *
     * @param array<int, array{role: string, content: string}> $history
     * @return array{reply: string, image: string|null, generated_image_url: string|null, pexels_url: string|null}
     */
    /** Thèmes du quiz Rorschach : images abstraites, symétriques, ambiguës (style taches). Ordre image 1→4. */
    private const QUIZ_IMAGE_THEMES = [
        1 => 'formes abstraites évoquant les émotions (taches organiques, symétriques, couleurs douces pastel, ouvert à l\'interprétation)',
        2 => 'formes abstraites évoquant la routine ou l\'ordre (symétrie, motifs géométriques doux, pastel, ambigu)',
        3 => 'formes abstraites évoquant le calme (taches fluides, symétriques, tons apaisants, pastel)',
        4 => 'formes abstraites évoquant le lien ou le soutien (deux parties reliées ou en miroir, symétrie, pastel chaud)',
    ];

    /** Prompts pour le quiz Rorschach : tache d'encre symétrique type Rorschach, sans scène figurative (pas de feuilles, nature, ombres). */
    private const RORSCHACH_FALLBACK_PROMPTS = [
        'Rorschach inkblot test style, symmetrical mirror image, abstract blob shapes only, no recognizable objects no nature no leaves no trees no shadows, ambiguous, soft pastel colors, psychology',
        'Rorschach inkblot symmetrical, mirror reflection, abstract organic blobs, no figures no nature no plants, pastel tones, order and structure, ambiguous interpretation',
        'Rorschach style inkblot, symmetrical, abstract fluid shapes, no objects no landscape no shadows, calm pastel colors, open to interpretation',
        'Rorschach inkblot symmetrical mirror, two halves connected, abstract blobs only, no people no nature, warm pastel, ambiguous psychology test',
    ];

    public function chat(string $userMessage, array $history = [], ?string $imageBase64 = null): array
    {
        $isQuizWithImages = $this->userAskedForQuizWithImages($userMessage, $history);
        $quizImageNumber = null;
        if ($isQuizWithImages) {
            $modelTurnCount = 0;
            foreach ($history as $item) {
                if (($item['role'] ?? '') === 'assistant' || ($item['role'] ?? '') === 'model') {
                    $modelTurnCount++;
                }
            }
            $quizImageNumber = min($modelTurnCount + 1, 4);
        }

        $systemPrompt = self::SYSTEM_PROMPT_TSA;
        if ($isQuizWithImages && $quizImageNumber !== null) {
            $theme = self::QUIZ_IMAGE_THEMES[$quizImageNumber] ?? self::QUIZ_IMAGE_THEMES[1];
            $systemPrompt .= "\n\n--- CONtexte QUIZ RORSCHACH (à respecter pour ce message) ---\n";
            $systemPrompt .= "Tu es dans le quiz inspiré du test de Rorschach : le patient regarde une image ABSTRAITE (comme une tache d'encre) et dit ce qu'il voit ou ressent (pas de bonne/mauvaise réponse). Tu montres l'IMAGE NUMÉRO " . $quizImageNumber . " sur 4. Thème suggéré pour la forme : " . $theme . ".\n";
            $systemPrompt .= "L'image doit être STYLE RORSCHACH : abstraite, symétrique (miroir), ambiguë, ouverte à l'interprétation. PAS de scène figurative (pas de personnages, pas d'objets reconnaissables). Couleurs douces, pastel. Invite le patient à regarder et à dire ce qu'il voit ou ressent. Puis sur une NOUVELLE LIGNE : IMAGE_PROMPT: abstract symmetrical inkblot soft pastel [détails selon thème] ambiguous open to interpretation. Une seule ligne IMAGE_PROMPT, en anglais.";
        }

        $raw = $this->groqChat->isConfigured()
            ? ($imageBase64 !== null
                ? $this->groqChat->chatWithVision($userMessage, $history, $systemPrompt, $imageBase64)
                : $this->groqChat->chat($userMessage, $history, $systemPrompt))
            : $this->geminiChat->chat($this->buildGeminiPrompt($userMessage, $history, $systemPrompt), []);

        $parsed = $this->parseReplyAndImage($raw);
        $generatedUrl = null;
        $pexelsUrl = null;

        $userAskedForImage = $this->userAskedForImage($userMessage);
        $isQuizWithImages = $this->userAskedForQuizWithImages($userMessage, $history);

        // Extraire le prompt d'image depuis la réponse brute (avant nettoyage) pour générer l'image
        $extractedPrompt = null;
        if (preg_match('/IMAGE[\s_]*PROMPT\s*:\s*(.+)$/im', $raw, $m)) {
            $extractedPrompt = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        if ($extractedPrompt !== null && $extractedPrompt !== '' && ($userAskedForImage['wants'] !== null || $isQuizWithImages)) {
            $prompt = $extractedPrompt;
            $parsed['reply'] = trim($parsed['reply']);
            if ($parsed['reply'] === '') {
                $parsed['reply'] = 'Voici une image pour vous.';
            }
            // Quiz Rorschach : toujours utiliser le prompt fixe pour ce numéro d'image (1→4)
            $promptToUse = $prompt;
            if ($isQuizWithImages && $quizImageNumber !== null) {
                $promptToUse = self::RORSCHACH_FALLBACK_PROMPTS[$quizImageNumber - 1] ?? $prompt;
            }
            if ($this->hfImage->isConfigured()) {
                $result = $this->hfImage->generate($promptToUse);
                if ($result['success'] && $result['image_url'] !== null) {
                    $generatedUrl = $result['image_url'];
                }
            }
            if ($generatedUrl === null && $this->pexelsImage->isConfigured() && !$isQuizWithImages) {
                $pexelsQuery = $this->pexelsImage->buildSearchQueryFromFrench($prompt);
                $generatedUrl = $this->pexelsImage->searchFirstPhoto($pexelsQuery);
            }
            if ($generatedUrl === null && $isQuizWithImages && $quizImageNumber !== null) {
                $generatedUrl = $this->rorschachSvg->generate($quizImageNumber);
            }
            if ($generatedUrl === null) {
                $parsed['reply'] .= "\n\n(Image temporairement indisponible. Vous pouvez continuer à discuter.)";
            }
        }

        if ($parsed['image'] !== null && $generatedUrl === null && ($userAskedForImage['wants'] !== null || $isQuizWithImages)) {
            $key = (int) $parsed['image'];
            $themePrompt = self::THEME_PROMPTS[$key] ?? null;
            if ($themePrompt !== null) {
                if ($this->hfImage->isConfigured()) {
                    $result = $this->hfImage->generate($themePrompt);
                    if ($result['success'] && $result['image_url'] !== null) {
                        $generatedUrl = $result['image_url'];
                    }
                }
                if ($generatedUrl === null && $this->pexelsImage->isConfigured()) {
                    $pexelsQuery = str_replace([' soft pastel style', ', soft pastel style'], '', $themePrompt);
                    $generatedUrl = $this->pexelsImage->searchFirstPhoto($pexelsQuery);
                }
            }
        }

        // Si l'utilisateur a demandé une image mais le modèle n'a pas renvoyé IMAGE_PROMPT, on génère quand même (quiz images ou demande explicite) avec un thème TSA
        if ($generatedUrl === null && ($userAskedForImage['wants'] !== null || $isQuizWithImages)) {
            if ($userAskedForImage['wants'] !== null) {
                $fallbackPrompt = $userAskedForImage['prompt'];
                $pexelsFallbackQuery = null;
            } else {
                // Quiz Rorschach : fallback = prompt fixe pour l'image N (1→4) pour garantir 4 images différentes
                $fallbackIndex = 0;
                if ($quizImageNumber !== null) {
                    $fallbackIndex = $quizImageNumber - 1;
                } else {
                    $modelTurnCount = 0;
                    foreach ($history as $item) {
                        if (($item['role'] ?? '') === 'assistant' || ($item['role'] ?? '') === 'model') {
                            $modelTurnCount++;
                        }
                    }
                    $fallbackIndex = $modelTurnCount % \count(self::RORSCHACH_FALLBACK_PROMPTS);
                }
                $prompts = self::RORSCHACH_FALLBACK_PROMPTS;
                $fallbackPrompt = $prompts[$fallbackIndex];
                $pexelsFallbackQuery = ['abstract inkblot emotions pastel', 'abstract inkblot order symmetry pastel', 'abstract inkblot calm pastel', 'abstract inkblot connection pastel'][$fallbackIndex] ?? 'abstract inkblot art pastel';
            }
            if ($this->hfImage->isConfigured()) {
                $result = $this->hfImage->generate($fallbackPrompt);
                if ($result['success'] && $result['image_url'] !== null) {
                    $generatedUrl = $result['image_url'];
                }
            }
            if ($generatedUrl === null && $this->pexelsImage->isConfigured() && !$isQuizWithImages) {
                $pexelsQuery = $userAskedForImage['wants'] !== null
                    ? $this->pexelsImage->buildSearchQueryFromFrench($userAskedForImage['prompt'])
                    : (is_string($pexelsFallbackQuery ?? null) ? $pexelsFallbackQuery : 'calm sensory room soft light');
                $generatedUrl = $this->pexelsImage->searchFirstPhoto($pexelsQuery);
            }
            if ($generatedUrl === null && $isQuizWithImages) {
                $imgNum = $quizImageNumber ?? (isset($fallbackIndex) ? $fallbackIndex + 1 : 1);
                $generatedUrl = $this->rorschachSvg->generate($imgNum);
            }
        }

        return [
            'reply' => $parsed['reply'],
            'image' => null,
            'generated_image_url' => $generatedUrl ?: $pexelsUrl,
            'pexels_url' => $pexelsUrl,
        ];
    }

    /**
     * Détecte si l'utilisateur demande une image et retourne un prompt par défaut si oui.
     * @return array{wants: string|null, prompt: string}
     */
    private function userAskedForImage(string $message): array
    {
        $lower = mb_strtolower(trim($message));
        $emotions = ['émotion', 'emotions', 'émotions', 'emotion', 'sentiment'];
        $routine = ['routine', 'repères', 'reperes', 'calendrier'];
        $sensoriel = ['sensoriel', 'sensorielle', 'sens'];
        $calme = ['calme', 'apaisant', 'serein', 'peaceful'];
        $family = ['famille', 'family'];
        if (preg_match('/\b(photo|image|picture|img|génère|genere|générer|generer|montre|affiche)\b/u', $lower)) {
            foreach ($emotions as $k) {
                if (mb_strpos($lower, $k) !== false) {
                    return ['wants' => 'emotions', 'prompt' => 'heart with colorful lights representing emotions love and warmth, soft pastel style'];
                }
            }
            foreach ($routine as $k) {
                if (mb_strpos($lower, $k) !== false) {
                    return ['wants' => 'routine', 'prompt' => 'daily routine morning schedule calendar, soft pastel style'];
                }
            }
            foreach ($sensoriel as $k) {
                if (mb_strpos($lower, $k) !== false) {
                    return ['wants' => 'sensory', 'prompt' => 'calm sensory room soft colors, soft pastel style'];
                }
            }
            foreach ($calme as $k) {
                if (mb_strpos($lower, $k) !== false) {
                    return ['wants' => 'calm', 'prompt' => 'calm peaceful child meditation relaxing space, soft pastel style'];
                }
            }
            foreach ($family as $k) {
                if (mb_strpos($lower, $k) !== false) {
                    return ['wants' => 'family', 'prompt' => 'family support together bond connection, soft pastel style'];
                }
            }
            return ['wants' => 'default', 'prompt' => 'calm child emotions soft pastel style'];
        }
        return ['wants' => null, 'prompt' => ''];
    }

    /**
     * Détecte si l'utilisateur a demandé le quiz par images (Rorschach) — dans ce message ou dans l'historique.
     */
    private function userAskedForQuizWithImages(string $userMessage, array $history): bool
    {
        $check = function (string $text): bool {
            $lower = mb_strtolower(trim($text));
            if (mb_strpos($lower, 'rorschach') !== false) {
                return true;
            }
            return (mb_strpos($lower, 'quiz') !== false) && (mb_strpos($lower, 'image') !== false || mb_strpos($lower, 'photo') !== false);
        };
        if ($check($userMessage)) {
            return true;
        }
        foreach ($history as $item) {
            if (($item['role'] ?? '') === 'user' && $check((string) ($item['content'] ?? ''))) {
                return true;
            }
        }
        return false;
    }

    private function buildGeminiPrompt(string $userMessage, array $history, ?string $fullSystemPrompt = null): string
    {
        $intro = ($fullSystemPrompt ?? self::SYSTEM_PROMPT_TSA) . "\n\n";
        if ($history === []) {
            return $intro . "Message du patient : " . $userMessage;
        }
        $conv = '';
        foreach ($history as $item) {
            $who = $item['role'] === 'user' ? 'Patient' : 'Assistant';
            $conv .= $who . " : " . $item['content'] . "\n";
        }
        return $intro . $conv . "Patient : " . $userMessage;
    }

    private function parseReplyAndImage(string $raw): array
    {
        $reply = $raw;
        $image = null;

        if (preg_match('/\bIMAGE:([1-6])\b/i', $reply, $m)) {
            $image = $m[1];
            $reply = preg_replace('/\s*\bIMAGE:[1-6]\b\s*/i', ' ', $reply);
        }
        // Ne jamais afficher IMAGE PROMPT / IMAGE_PROMPT ou contenu technique au patient (accepte espace ou underscore)
        $reply = preg_replace('/\s*IMAGE[\s_]*PROMPT\s*:\s*.+$/im', '', $reply);
        $reply = trim(preg_replace('/\s+/', ' ', $reply));

        return [
            'reply' => $reply,
            'image' => $image,
        ];
    }
}
