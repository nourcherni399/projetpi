<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Assistant vocal RDV : l'IA comprend la date/heure depuis la parole et renvoie une phrase de confirmation.
 * Utilise la Web Speech API côté navigateur + cet service pour l'extraction de la date.
 */
final class VocalRdvService
{
    private const MSG_NOT_UNDERSTOOD = [
        'fr-FR' => 'Je n\'ai pas bien compris. Pouvez-vous répéter la date et l\'heure souhaitées pour votre rendez-vous ?',
        'en-US' => 'I didn\'t quite understand that. Could you please repeat the date and time you would like for your appointment?',
        'ar-TN' => 'لم أفهم جيداً. هل يمكنك تكرار التاريخ والوقت المطلوبين للموعد؟',
        'ar-SA' => 'لم أفهم جيداً. هل يمكنك تكرار التاريخ والوقت المطلوبين للموعد؟',
        'es-ES' => 'No he entendido bien. ¿Puede repetir la fecha y la hora que desea para su cita?',
        'de-DE' => 'Das habe ich nicht ganz verstanden. Könnten Sie bitte Datum und Uhrzeit für Ihren Termin wiederholen?',
        'it-IT' => 'Non ho capito bene. Può ripetere la data e l\'ora desiderate per l\'appuntamento?',
    ];
    private const MSG_SELECT_SLOT = [
        'fr-FR' => 'Je comprends que vous souhaitez prendre rendez-vous. Pour le moment, veuillez sélectionner un créneau dans la liste ci-dessous.',
        'en-US' => 'I understand you want to book an appointment. Please select a time slot from the list below.',
        'ar-TN' => 'أفهم أنك تريد حجز موعد. يرجى اختيار وقت من القائمة أدناه.',
        'ar-SA' => 'أفهم أنك تريد حجز موعد. يرجى اختيار وقت من القائمة أدناه.',
        'es-ES' => 'Entiendo que desea pedir cita. Por favor, seleccione un horario de la lista siguiente.',
        'de-DE' => 'Ich verstehe, dass Sie einen Termin buchen möchten. Bitte wählen Sie unten einen Zeitfenster aus.',
        'it-IT' => 'Capisco che desidera prendere un appuntamento. Selezioni un orario dalla lista qui sotto.',
    ];
    private const MSG_REQUEST_RECEIVED = [
        'fr-FR' => 'J\'ai bien reçu votre demande. Veuillez sélectionner un créneau dans la liste ci-dessous pour confirmer votre rendez-vous.',
        'en-US' => 'I have received your request. Please select a time slot from the list below to confirm your appointment.',
        'ar-TN' => 'تم استلام طلبك. يرجى اختيار وقت من القائمة أدناه لتأكيد الموعد.',
        'ar-SA' => 'تم استلام طلبك. يرجى اختيار وقت من القائمة أدناه لتأكيد الموعد.',
        'es-ES' => 'He recibido su solicitud. Seleccione un horario en la lista para confirmar su cita.',
        'de-DE' => 'Ihre Anfrage ist eingegangen. Bitte wählen Sie einen Zeitfenster aus der Liste zur Bestätigung.',
        'it-IT' => 'Ho ricevuto la sua richiesta. Selezioni un orario dalla lista per confermare l\'appuntamento.',
    ];

    public function __construct(
        private GroqChatService $groqChat
    ) {
    }

    private function getMessageForLang(array $messages, ?string $userLangCode): string
    {
        $code = $userLangCode ?? 'fr-FR';
        return $messages[$code] ?? $messages['fr-FR'];
    }

    /**
     * À partir du transcript vocal (toute langue), extrait la date/heure et génère une phrase de confirmation à lire à voix haute.
     * La confirmation est dans la MÊME LANGUE que le transcript (ou la langue indiquée par l'utilisateur).
     *
     * @param string $transcript Phrase prononcée par l'utilisateur (toute langue)
     * @param string|null $doctorName Nom du médecin
     * @param string|null $userLangCode Code langue choisi par l'utilisateur (ex. en-US, ar-TN) pour forcer la réponse dans cette langue
     * @return array{date: string|null, time: string|null, confirmation_text: string, lang: string}
     */
    public function understandAndConfirm(string $transcript, ?string $doctorName = null, ?string $userLangCode = null): array
    {
        $transcript = trim($transcript);
        $lang = $userLangCode ?? 'fr-FR';
        if ($transcript === '') {
            return [
                'date' => null,
                'time' => null,
                'confirmation_text' => $this->getMessageForLang(self::MSG_NOT_UNDERSTOOD, $userLangCode),
                'lang' => $lang,
            ];
        }

        if (!$this->groqChat->isConfigured()) {
            return [
                'date' => null,
                'time' => null,
                'confirmation_text' => $this->getMessageForLang(self::MSG_SELECT_SLOT, $userLangCode),
                'lang' => $lang,
            ];
        }

        $now = new \DateTimeImmutable('now');
        $today = $now->format('Y-m-d');
        $todayFr = $now->format('d/m/Y');
        $dayName = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'][(int) $now->format('w')];

        $doctorContext = $doctorName !== null && $doctorName !== ''
            ? ' The appointment is with Dr. ' . $doctorName . '.'
            : '';

        $langMap = [
            'fr-FR' => 'FRENCH (français)',
            'en-US' => 'ENGLISH',
            'ar-TN' => 'ARABIC (العربية)',
            'ar-SA' => 'ARABIC (العربية)',
            'es-ES' => 'SPANISH (español)',
            'de-DE' => 'GERMAN (Deutsch)',
            'it-IT' => 'ITALIAN (italiano)',
        ];
        $langName = $langMap[$userLangCode ?? ''] ?? $userLangCode ?? 'fr-FR';
        $requestedLangCode = $userLangCode ?? 'fr-FR';
        $langInstruction = "\n\nMANDATORY — LANGUAGE: The user has selected language: " . $langName . " (code: " . $requestedLangCode . "). You MUST:\n- Write confirmation_text ONLY in that language. Never use French if they chose English; never use English if they chose Arabic; etc.\n- Set \"lang\" in your JSON to exactly \"" . $requestedLangCode . "\".\nAny other language in confirmation_text is forbidden.";

        $systemPrompt = <<<PROMPT
STRICT RULE — RESPONSE LANGUAGE: You must reply ONLY in the user's selected language. The "confirmation_text" field must be written EXCLUSIVELY in that language. The "lang" field in your JSON must be exactly the code of the selected language (see below).
{$langInstruction}

You are AutiCare's voice assistant for booking medical appointments. The patient does NOT need to speak a perfect or complete sentence. You must understand from KEYWORDS and partial phrases.

INTERPRETATION RULES:
- Focus on KEYWORDS: numbers (25, 14, 10), weekdays (lundi, Monday, الاثنين), month names (février, February, فبراير), time words (14h, 10h, matin, afternoon, صباحاً), and relative words (demain, tomorrow, غداً, next week).
- Accept incomplete or messy input: "demain 14h", "lundi matin", "25 février 10", "next Monday 2pm", "الاثنين العاشرة", single words or short fragments. Extract whatever date/time you can.
- The transcript may have speech-recognition errors: wrong spelling, missing letters, mixed language. Ignore grammar and extract meaning from keywords only.
- If you find at least one date OR one time, use it. If only a weekday is said, use the next occurrence of that day. If only a time is said with no date, you may use today's date or null for date.
- If the transcript is too vague (no number, no weekday, no month, no time word), use null for date and time and ask kindly to repeat with a date or time (in the user's language).

REFERENCE: Today is {$today} ({$dayName} {$todayFr}). Prefer dates in the NEXT 4 WEEKS only.

1) DATE (output YYYY-MM-DD or null):
   - French: "25 février", "demain", "lundi", "après-demain", "la semaine prochaine".
   - English: "February 25", "tomorrow", "Monday", "next Tuesday".
   - Arabic: "غداً", "الاثنين", "25 فبراير", numbers and month names.
   - Spanish: "25 de febrero", "mañana", "lunes".
   - German: "25. Februar", "morgen", "Montag".
   - Italian: "25 febbraio", "domani", "lunedì".
   - Do not confuse February with April. If only a weekday: use the NEXT occurrence.

2) TIME (output HH:MM, 24h or null):
   - Any number + "h" or "heure(s)" or "pm/am" or "Uhr" or "o'clock" or Arabic time words → convert to HH:MM.
   - "14h", "2pm", "deux heures", "الساعة الثانية", "14 Uhr", "las dos" → 14:00.
   - "matin" / "morning" / "صباحاً" → e.g. 09:00 or 10:00 if no hour; "après-midi" / "afternoon" → 14:00 or 15:00 if no hour.
   - If no time is clear, use null.

3) CONFIRMATION_TEXT: One short sentence IN THE USER'S LANGUAGE. Say the date and time you understood (if any). If unclear, ask kindly to repeat with a date or time.

4) Reply ONLY with a valid JSON object, no markdown, no text before or after:
{"date": "YYYY-MM-DD or null", "time": "HH:MM or null", "confirmation_text": "One short friendly sentence in the user's language", "lang": "fr-FR or en-US or ar-TN or ar-SA or es-ES or de-DE or it-IT"}
{$doctorContext}

Reply with the JSON only.
PROMPT;

        $userMessage = 'User said (do not translate, keep as-is): ' . $transcript;

        $raw = $this->groqChat->chat($userMessage, [], $systemPrompt);
        $decoded = $this->parseJsonResponse($raw);

        if ($decoded !== null && isset($decoded['confirmation_text'])) {
            // Toujours privilégier la langue sélectionnée par l'utilisateur pour la réponse vocale
            $outLang = $userLangCode ?? (isset($decoded['lang']) && \is_string($decoded['lang']) && $decoded['lang'] !== '' ? $decoded['lang'] : 'fr-FR');
            return [
                'date' => isset($decoded['date']) && \is_string($decoded['date']) && $decoded['date'] !== '' ? $decoded['date'] : null,
                'time' => isset($decoded['time']) && \is_string($decoded['time']) && $decoded['time'] !== '' ? $decoded['time'] : null,
                'confirmation_text' => trim((string) $decoded['confirmation_text']),
                'lang' => $outLang,
            ];
        }

        return [
            'date' => null,
            'time' => null,
            'confirmation_text' => $this->getMessageForLang(self::MSG_REQUEST_RECEIVED, $userLangCode),
            'lang' => $lang,
        ];
    }

    private function parseJsonResponse(string $raw): ?array
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```\w*\s*|\s*```$/m', '', $raw);
        $decoded = json_decode($raw, true);
        return \is_array($decoded) ? $decoded : null;
    }
}
