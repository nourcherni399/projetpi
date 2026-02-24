<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Détecte si l'utilisateur confirme sa commande par la voix (toutes langues).
 */
final class VocalCommandeService
{
    public function __construct(
        private GroqChatService $groqChat
    ) {
    }

    /**
     * Détecte l'intention : confirmer la commande, annuler, ou incompris.
     *
     * @return array{intent: 'confirm'|'reject'|'unclear', message: string, lang: string}
     */
    public function detectConfirmIntent(string $transcript, ?string $userLangCode = null): array
    {
        $transcript = trim($transcript);
        $fallbackUnclear = [
            'fr-FR' => 'Je n\'ai pas entendu. Dites « Je confirme ma commande » pour valider.',
            'en-US' => 'I didn\'t hear you. Say "I confirm my order" to validate.',
            'ar-TN' => 'لم أسمع. قل « أؤكد طلبي » للتحقق.',
            'ar-SA' => 'لم أسمع. قل « أؤكد طلبي » للتحقق.',
            'es-ES' => 'No le he oído. Diga « Confirmo mi pedido » para validar.',
            'de-DE' => 'Ich habe Sie nicht verstanden. Sagen Sie „Ich bestätige meine Bestellung“.',
            'it-IT' => 'Non l\'ho sentita. Dica « Confermo il mio ordine » per confermare.',
        ];
        $code = $userLangCode ?? 'fr-FR';
        $msgUnclear = $fallbackUnclear[$code] ?? $fallbackUnclear['fr-FR'];

        if ($transcript === '') {
            return [
                'intent' => 'unclear',
                'message' => $msgUnclear,
                'lang' => $code,
            ];
        }

        $langInstruction = $userLangCode
            ? "\n\nMANDATORY: The user has selected language " . $userLangCode . ". You MUST write \"message\" ONLY in that language and set \"lang\" to \"" . $userLangCode . "\". Never reply in another language."
            : '';

        $systemPrompt = <<<PROMPT
STRICT: Reply with "message" and "lang" in the user's selected language only.
{$langInstruction}

You are a voice assistant for an e-commerce checkout. The user does NOT need to say a perfect sentence. Determine intent from KEYWORDS and short phrases only.

INTENT RULES (keyword-based, any language):
- "confirm" = ANY word or phrase that clearly means YES / CONFIRM / VALIDATE: e.g. "oui", "yes", "نعم", "sí", "ja", "sì", "confirm", "confirme", "أؤكد", "confirmo", "bestätigen", "confermo", "validate", "valider", "ok", "okay", "I confirm", "Je confirme", "confirm my order", "أؤكد الطلب". Single word "oui" or "yes" or "نعم" is enough for confirm.
- "reject" = ANY word or phrase that clearly means NO / CANCEL: e.g. "non", "no", "لا", "cancel", "annuler", "إلغاء", "cancelar", "stornieren", "annulla", "I don't want", "never mind".
- "unclear" = nothing clearly indicating confirm or reject; or mixed/ambiguous; or unrelated words. When in doubt, use "unclear".

Do NOT require a full or grammatically correct sentence. "Oui", "Yes", "Confirm", "نعم" alone = confirm. "Non", "No", "Cancel" = reject. Ignore speech-recognition spelling errors; focus on the intended meaning.

Reply ONLY with a valid JSON object, no markdown: {"intent": "confirm" or "reject" or "unclear", "message": "One short sentence to read aloud in the user's language (confirm: order confirmed; reject: cancelled; unclear: ask to say I confirm)", "lang": "BCP-47 e.g. fr-FR, en-US, ar-TN"}
{$langInstruction}
PROMPT;

        $raw = $this->groqChat->chat('User said: ' . $transcript, [], $systemPrompt);
        $decoded = $this->parseJson($raw);

        if ($decoded !== null && isset($decoded['intent']) && \in_array($decoded['intent'], ['confirm', 'reject', 'unclear'], true)) {
            $outLang = $userLangCode ?? (isset($decoded['lang']) && $decoded['lang'] !== '' ? (string) $decoded['lang'] : 'fr-FR');
            $outMsg = isset($decoded['message']) ? trim((string) $decoded['message']) : ($fallbackUnclear[$outLang] ?? $msgUnclear);
            return [
                'intent' => $decoded['intent'],
                'message' => $outMsg,
                'lang' => $outLang,
            ];
        }

        return [
            'intent' => 'unclear',
            'message' => $msgUnclear,
            'lang' => $code,
        ];
    }

    private function parseJson(string $raw): ?array
    {
        $raw = trim(preg_replace('/^```\w*\s*|\s*```$/m', '', $raw));
        $decoded = json_decode($raw, true);
        return \is_array($decoded) ? $decoded : null;
    }
}
