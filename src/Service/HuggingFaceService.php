<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Appels à l'API Hugging Face Inference (gratuite, limitée).
 * Personnalisé pour le projet AutiCare et la gestion d'événements (familles, inclusion, autisme).
 */
final class HuggingFaceService
{
    /** Router : seul l'endpoint chat/completions est exposé en HTTP. */
    private const ROUTER_CHAT_URL = 'https://router.huggingface.co/v1/chat/completions';
    /** Modèle par défaut (souvent disponible sur Groq/Together/HF). */
    private const DEFAULT_CHAT_MODEL = 'meta-llama/Llama-3.2-3B-Instruct:fastest';
    private const MAX_INPUT_LENGTH = 1024;

    /** Contexte métier AutiCare : utilisé dans tous les prompts pour personnaliser l'IA. */
    private const CONTEXTE_AUTICARE = 'Contexte : AutiCare est une plateforme de gestion d\'événements destinée aux familles et à l\'inclusion (autisme, handicaps). Les messages analysés proviennent de participants inscrits à des événements (ateliers, sorties, sensibilisation). L\'équipe admin gère les inscriptions et les échanges avec les familles.';

    /** Dernière erreur API : ['status' => int, 'message' => string] */
    private ?array $lastApiError = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey = null,
        private readonly string $projectDir = '',
    ) {
    }

    /** Retourne la dernière erreur API (code HTTP + message) pour affichage. */
    public function getLastApiError(): ?array
    {
        return $this->lastApiError;
    }

    private function getApiKey(): ?string
    {
        $key = ($this->apiKey !== null && $this->apiKey !== '') ? trim($this->apiKey) : null;
        if ($key !== null && $key !== '') {
            return $key;
        }
        $env = trim((string) ($_ENV['HUGGINGFACE_API_KEY'] ?? getenv('HUGGINGFACE_API_KEY') ?: ''));
        if ($env !== '') {
            return $env;
        }
        // Dernier recours : lire la clé depuis .env.local puis .env sur le disque
        foreach (['.env.local', '.env'] as $file) {
            $path = $this->projectDir . \DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            $key = $this->parseEnvKey($content, 'HUGGINGFACE_API_KEY');
            if ($key !== null && $key !== '') {
                return trim($key);
            }
        }
        return null;
    }

    private function parseEnvKey(string $content, string $name): ?string
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            $eq = strpos($line, '=');
            $key = trim(substr($line, 0, $eq));
            if ($key !== $name) {
                continue;
            }
            $value = trim(substr($line, $eq + 1), " \t\"'");
            return $value;
        }
        return null;
    }

    /** Indique si une clé API est disponible (pour distinguer "clé absente" de "erreur API"). */
    public function hasApiKey(): bool
    {
        return $this->getApiKey() !== null;
    }

    /** Retourne le contexte métier AutiCare (gestion d'événements). Utilisé dans tous les prompts. */
    public static function getContexteAutiCare(): string
    {
        return self::CONTEXTE_AUTICARE;
    }

    /** Convertit un message d'erreur (string ou array) en string pour éviter "Array to string conversion". */
    private function errorToString(mixed $msg): string
    {
        if (\is_array($msg)) {
            return isset($msg['message']) ? (string) $msg['message'] : json_encode($msg, \JSON_UNESCAPED_UNICODE);
        }
        return (string) $msg;
    }

    /** Retourne l'ID du modèle chat (env HUGGINGFACE_MODEL ou défaut). */
    private function getChatModel(): string
    {
        $v = trim((string) ($_ENV['HUGGINGFACE_MODEL'] ?? getenv('HUGGINGFACE_MODEL') ?: ''));
        if ($v !== '') {
            return $v;
        }
        foreach (['.env.local', '.env'] as $file) {
            $path = $this->projectDir . \DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            $parsed = $this->parseEnvKey($content, 'HUGGINGFACE_MODEL');
            if ($parsed !== null && $parsed !== '') {
                return trim($parsed);
            }
        }
        return self::DEFAULT_CHAT_MODEL;
    }

    /**
     * Résume le texte (idéal pour description longue → courte).
     * Retourne null si l'API est indisponible ou non configurée.
     */
    public function summarize(string $text): ?string
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return null;
        }
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > self::MAX_INPUT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_INPUT_LENGTH);
        }

        $this->lastApiError = null;
        try {
            $prompt = "Résume le texte suivant en une courte phrase ou un paragraphe. Conserve l'essentiel. Réponds uniquement avec le résumé, sans préambule.\n\n" . $text;
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 150,
                ],
                'timeout' => 45,
            ]);
            $data = $response->toArray();
            if (isset($data['choices'][0]['message']['content'])) {
                return trim((string) $data['choices'][0]['message']['content']);
            }
            if (isset($data['error'])) {
                $msg = $data['error'];
                $this->lastApiError = ['status' => $response->getStatusCode(), 'message' => $this->errorToString($msg)];
                return null;
            }
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            try {
                $response = $e->getResponse();
                $status = $response->getStatusCode();
                $body = $response->getContent(false);
                $msg = \is_string($body) ? $body : 'Erreur API';
                $decoded = json_decode($msg, true, 32);
                if (\is_array($decoded) && isset($decoded['error'])) {
                    $msg = $decoded['error'];
                    if (isset($decoded['estimated_time'])) {
                        $msg = $this->errorToString($msg) . ' Réessayez dans ' . ((int) $decoded['estimated_time']) . ' secondes.';
                    } else {
                        $msg = $this->errorToString($msg);
                    }
                } else {
                    $msg = $this->errorToString($msg);
                }
                $this->lastApiError = ['status' => $status, 'message' => $msg];
            } catch (\Throwable) {
                $this->lastApiError = ['status' => 0, 'message' => $e->getMessage()];
            }
            return null;
        } catch (\Throwable $t) {
            $this->lastApiError = ['status' => 0, 'message' => $this->errorToString($t->getMessage())];
            return null;
        }
        return null;
    }

    /**
     * Analyse le sentiment du texte (positif / négatif / neutre).
     * Personnalisé AutiCare : contexte gestion d'événements et messages participants.
     * $context optionnel : surcharge le contexte par défaut.
     * Retourne ['label' => 'positive'|'negative'|'neutral', 'score' => float] ou [] si erreur.
     */
    public function getSentiment(string $text, ?string $context = null): array
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return [];
        }
        $text = trim($text);
        if ($text === '') {
            return ['label' => 'neutral', 'score' => 0.0];
        }
        if (mb_strlen($text) > 512) {
            $text = mb_substr($text, 0, 512);
        }

        $ctx = $context !== null && trim($context) !== '' ? trim($context) : self::CONTEXTE_AUTICARE;
        $prompt = $ctx . "\n\nTu analyses le sentiment du message suivant (écrit par un participant à un événement AutiCare). Réponds par un seul mot : positive, negative, ou neutral.\n\nMessage : " . $text;

        try {
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 10,
                ],
                'timeout' => 20,
            ]);
            $data = $response->toArray();
            if (isset($data['choices'][0]['message']['content'])) {
                $reply = strtolower(trim((string) $data['choices'][0]['message']['content']));
                $label = 'neutral';
                if (str_contains($reply, 'positive')) {
                    $label = 'positive';
                } elseif (str_contains($reply, 'negative')) {
                    $label = 'negative';
                }
                return ['label' => $label, 'score' => $label !== 'neutral' ? 0.85 : 0.5];
            }
        } catch (\Throwable) {
            return [];
        }
        return [];
    }

    /**
     * Classifie le type de message (gestion événements AutiCare).
     * Catégories adaptées aux échanges participant / admin : question pratique, demande d'info, remerciement, inquiétude, demande de modification, autre.
     * Retourne ['label' => string, 'score' => float].
     */
    public function getMessageCategory(string $text, ?string $context = null): array
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return [];
        }
        $text = trim($text);
        if ($text === '') {
            return ['label' => 'autre', 'score' => 0.0];
        }
        if (mb_strlen($text) > 512) {
            $text = mb_substr($text, 0, 512);
        }

        $ctx = $context !== null && trim($context) !== '' ? trim($context) : self::CONTEXTE_AUTICARE;
        $prompt = $ctx . "\n\nClasse ce message participant dans exactement une catégorie. Réponds uniquement avec la clé de la catégorie.\n"
            . "Catégories : question_pratique (question pratique : horaires, lieu, annulation), demande_info (demande d'information sur l'événement), remerciement (remerciement), inquiétude (inquiétude ou besoin de rassurance), demande_modification (demande de modification : date, désinscription), autre.\n\n"
            . "Message : " . $text;

        try {
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 20,
                ],
                'timeout' => 25,
            ]);
            $data = $response->toArray();
            if (isset($data['choices'][0]['message']['content'])) {
                $reply = strtolower(trim((string) $data['choices'][0]['message']['content']));
                $allowed = ['question_pratique', 'demande_info', 'remerciement', 'inquiétude', 'demande_modification', 'autre'];
                $label = 'autre';
                foreach ($allowed as $l) {
                    if (str_contains($reply, $l)) {
                        $label = $l;
                        break;
                    }
                }
                return ['label' => $label, 'score' => $label !== 'autre' ? 0.85 : 0.5];
            }
        } catch (\Throwable) {
            return [];
        }
        return [];
    }

    /**
     * Suggère une réponse au message d'un participant (gestion événements AutiCare).
     * Ton : équipe AutiCare, bienveillant, rassurant, professionnel. Retourne null si erreur.
     */
    public function suggestReply(string $participantMessage): ?string
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return null;
        }
        $participantMessage = trim($participantMessage);
        if ($participantMessage === '') {
            return null;
        }
        if (mb_strlen($participantMessage) > 800) {
            $participantMessage = mb_substr($participantMessage, 0, 800);
        }

        $this->lastApiError = null;
        $prompt = self::CONTEXTE_AUTICARE . "\n\nTu rédiges une réponse au nom de l'équipe AutiCare (gestion des événements). Un participant a envoyé le message suivant. Propose une réponse courte (2 à 4 phrases), bienveillante, rassurante et professionnelle, adaptée aux familles et à l'inclusion. Réponds UNIQUEMENT avec le texte de la réponse, sans préambule ni guillemets.\n\nMessage du participant : " . $participantMessage;

        try {
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 250,
                ],
                'timeout' => 45,
            ]);
            $data = $response->toArray();
            if (isset($data['choices'][0]['message']['content'])) {
                return trim((string) $data['choices'][0]['message']['content']);
            }
            if (isset($data['error'])) {
                $this->lastApiError = ['status' => $response->getStatusCode(), 'message' => $this->errorToString($data['error'])];
            }
        } catch (\Throwable $t) {
            $this->lastApiError = ['status' => 0, 'message' => $t->getMessage()];
        }
        return null;
    }

    /**
     * Suggère une réponse en FALC (Facile à Lire et à Comprendre) : phrases courtes, mots simples, une idée par phrase.
     * Adapté à l'inclusion (autisme, handicap cognitif). Retourne null si erreur.
     */
    public function suggestReplyFALC(string $participantMessage): ?string
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return null;
        }
        $participantMessage = trim($participantMessage);
        if ($participantMessage === '') {
            return null;
        }
        if (mb_strlen($participantMessage) > 800) {
            $participantMessage = mb_substr($participantMessage, 0, 800);
        }

        $this->lastApiError = null;
        $prompt = self::CONTEXTE_AUTICARE . "\n\nTu rédiges une réponse au nom de l'équipe AutiCare, en FALC (Facile à Lire et à Comprendre) : phrases courtes, mots simples, une seule idée par phrase, pas de jargon. Un participant a envoyé le message suivant. Propose une réponse bienveillante et rassurante en FALC (3 à 5 phrases courtes). Réponds UNIQUEMENT avec le texte de la réponse, sans préambule ni guillemets.\n\nMessage du participant : " . $participantMessage;

        try {
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 300,
                ],
                'timeout' => 45,
            ]);
            $data = $response->toArray();
            if (isset($data['choices'][0]['message']['content'])) {
                return trim((string) $data['choices'][0]['message']['content']);
            }
            if (isset($data['error'])) {
                $this->lastApiError = ['status' => $response->getStatusCode(), 'message' => $this->errorToString($data['error'])];
            }
        } catch (\Throwable $t) {
            $this->lastApiError = ['status' => 0, 'message' => $t->getMessage()];
        }
        return null;
    }

    /**
     * Détecte des indices de besoins d'adaptation dans le message (pour l'admin, discret).
     * Retourne ['labels' => string[], 'hint' => string] : labels = clés (debutant, rassurer_cadre, sensoriel, anxiete, horaire, calme, autre), hint = phrase pour l'admin.
     */
    public function detectAdaptationNeeds(string $message): array
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return ['labels' => [], 'hint' => ''];
        }
        $message = trim($message);
        if ($message === '') {
            return ['labels' => [], 'hint' => ''];
        }
        if (mb_strlen($message) > 512) {
            $message = mb_substr($message, 0, 512);
        }

        $this->lastApiError = null;
        $prompt = self::CONTEXTE_AUTICARE . "\n\nTu analyses un message de participant pour repérer des indices de besoins d'adaptation (inclusion, autisme, familles). Réponds UNIQUEMENT avec un JSON valide (sans markdown) : {\"labels\":[\"debutant\" ou \"rassurer_cadre\" ou \"sensoriel\" ou \"anxiete\" ou \"horaire\" ou \"calme\" ou \"autre\" (un ou plusieurs)], \"hint\":\"Une courte phrase pour l'équipe admin (ex. Rassurer sur le déroulement et préciser si l'atelier convient aux débutants.)\"}. Ne mets que les labels pertinents. Si aucun indice particulier, renvoie {\"labels\":[], \"hint\":\"\"}.\n\nMessage : " . $message;

        try {
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 120,
                ],
                'timeout' => 30,
            ]);
            $data = $response->toArray();
            if (!isset($data['choices'][0]['message']['content'])) {
                if (isset($data['error'])) {
                    $this->lastApiError = ['status' => $response->getStatusCode(), 'message' => $this->errorToString($data['error'])];
                }
                return ['labels' => [], 'hint' => ''];
            }
            $content = trim((string) $data['choices'][0]['message']['content']);
            $content = preg_replace('/^```\w*\s*|\s*```$/m', '', $content);
            $decoded = json_decode($content, true, 64);
            if (!\is_array($decoded)) {
                return ['labels' => [], 'hint' => ''];
            }
            $labels = isset($decoded['labels']) && \is_array($decoded['labels']) ? $decoded['labels'] : [];
            $labels = array_values(array_filter(array_map('strval', $labels), static fn ($v) => $v !== ''));
            $allowed = ['debutant', 'rassurer_cadre', 'sensoriel', 'anxiete', 'horaire', 'calme', 'autre'];
            $labels = array_values(array_intersect($labels, $allowed));
            $hint = isset($decoded['hint']) && \is_string($decoded['hint']) ? trim(mb_substr($decoded['hint'], 0, 250)) : '';
            return ['labels' => $labels, 'hint' => $hint];
        } catch (\Throwable $t) {
            $this->lastApiError = ['status' => 0, 'message' => $t->getMessage()];
            return ['labels' => [], 'hint' => ''];
        }
    }

    /**
     * Analyse complète (AutiCare) : sentiment + catégorie + recommandation pour l'admin.
     * Retourne ['sentiment' => ..., 'category' => ..., 'recommandation' => string] ou [].
     */
    public function analyzeMessageComplete(string $text): array
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return [];
        }
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) > 512) {
            $text = mb_substr($text, 0, 512);
        }

        $this->lastApiError = null;
        $prompt = self::CONTEXTE_AUTICARE . "\n\nTu analyses un message reçu dans la gestion des événements AutiCare. Message : " . $text . "\n\nRéponds UNIQUEMENT avec un JSON valide (sans markdown) : {\"sentiment\":\"positive\" ou \"negative\" ou \"neutral\", \"category\":\"question_pratique\" ou \"demande_info\" ou \"remerciement\" ou \"inquiétude\" ou \"demande_modification\" ou \"autre\", \"recommandation\":\"Une seule phrase en français pour guider l'équipe AutiCare dans sa réponse (ex. Répondre avec bienveillance en précisant les horaires et le lieu.)\"}";

        try {
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 150,
                ],
                'timeout' => 35,
            ]);
            $data = $response->toArray();
            if (!isset($data['choices'][0]['message']['content'])) {
                if (isset($data['error'])) {
                    $this->lastApiError = ['status' => $response->getStatusCode(), 'message' => $this->errorToString($data['error'])];
                }
                return [];
            }
            $content = trim((string) $data['choices'][0]['message']['content']);
            $content = preg_replace('/^```\w*\s*|\s*```$/m', '', $content);
            $decoded = json_decode($content, true, 64);
            if (!\is_array($decoded)) {
                return [];
            }
            $sentiment = isset($decoded['sentiment']) && \in_array($decoded['sentiment'], ['positive', 'negative', 'neutral'], true) ? $decoded['sentiment'] : 'neutral';
            $category = $decoded['category'] ?? 'autre';
            $recommandation = isset($decoded['recommandation']) && \is_string($decoded['recommandation']) ? trim($decoded['recommandation']) : '';
            return [
                'sentiment' => $sentiment,
                'category' => $category,
                'recommandation' => mb_substr($recommandation, 0, 300),
            ];
        } catch (\Throwable $t) {
            $this->lastApiError = ['status' => 0, 'message' => $t->getMessage()];
            return [];
        }
    }

    /**
     * Synthétise un texte (ex. résultats de recherche web) en idées d'événements pour AutiCare.
     * Si $searchKeywords est fourni, les idées doivent être en lien direct avec ce thème de recherche.
     *
     * @return array<int, array{titre: string, description: string, theme: string, pourquoi: string}>
     */
    public function suggestEventIdeasFromText(string $searchText, ?string $searchKeywords = null): array
    {
        $key = $this->getApiKey();
        if ($key === null) {
            return [];
        }
        $searchText = trim($searchText);
        if ($searchText === '') {
            return [];
        }
        if (mb_strlen($searchText) > 3500) {
            $searchText = mb_substr($searchText, 0, 3500);
        }

        $this->lastApiError = null;
        $themeInstruction = '';
        if ($searchKeywords !== null && trim($searchKeywords) !== '') {
            $kw = trim($searchKeywords);
            $themeInstruction = "\n\nRÈGLE STRICTE : La recherche de l'utilisateur est : « " . $kw . " ». Tu dois proposer UNIQUEMENT des idées d'événements qui correspondent à CE THÈME (ex. recherche « education » → uniquement idées sur l'éducation ; recherche « atelier jardinage » → uniquement jardinage, potager, plantes). Interdis-toi toute idée hors sujet. Base-toi sur les extraits ci-dessous. Réponds UNIQUEMENT avec des idées en lien direct avec « " . $kw . " ».\n\n";
        }
        $prompt = self::CONTEXTE_AUTICARE . $themeInstruction . "Voici des extraits trouvés sur le web :\n\n---\n" . $searchText . "\n---\n\nPropose 3 à 5 idées d'événements pour AutiCare, TOUTES en lien avec le thème de recherche indiqué ci-dessus. Pour chaque idée : titre (court), description (2 à 3 phrases), theme, pourquoi (une phrase). Réponds UNIQUEMENT avec un tableau JSON valide, sans markdown : [{\"titre\":\"...\",\"description\":\"...\",\"theme\":\"...\",\"pourquoi\":\"...\"}]";

        try {
            $response = $this->httpClient->request('POST', self::ROUTER_CHAT_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->getChatModel(),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 800,
                ],
                'timeout' => 60,
            ]);
            $data = $response->toArray();
            if (!isset($data['choices'][0]['message']['content'])) {
                if (isset($data['error'])) {
                    $this->lastApiError = ['status' => $response->getStatusCode(), 'message' => $this->errorToString($data['error'])];
                }
                return [];
            }
            $content = trim((string) $data['choices'][0]['message']['content']);
            $content = preg_replace('/^```\w*\s*|\s*```$/m', '', $content);
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($decoded)) {
                return [];
            }
            $ideas = [];
            foreach ($decoded as $item) {
                if (!\is_array($item)) {
                    continue;
                }
                $titre = isset($item['titre']) && \is_string($item['titre']) ? trim($item['titre']) : '';
                $description = isset($item['description']) && \is_string($item['description']) ? trim($item['description']) : '';
                $theme = isset($item['theme']) && \is_string($item['theme']) ? trim($item['theme']) : '';
                $pourquoi = isset($item['pourquoi']) && \is_string($item['pourquoi']) ? trim($item['pourquoi']) : '';
                if ($titre !== '') {
                    $ideas[] = [
                        'titre' => mb_substr($titre, 0, 255),
                        'description' => mb_substr($description, 0, 2000),
                        'theme' => mb_substr($theme, 0, 100),
                        'pourquoi' => mb_substr($pourquoi, 0, 500),
                    ];
                }
            }
            return $ideas;
        } catch (\JsonException) {
            return [];
        } catch (ClientExceptionInterface|ServerExceptionInterface $e) {
            try {
                $response = $e->getResponse();
                $this->lastApiError = ['status' => $response->getStatusCode(), 'message' => $e->getMessage()];
            } catch (\Throwable) {
                $this->lastApiError = ['status' => 0, 'message' => $e->getMessage()];
            }
            return [];
        } catch (\Throwable $t) {
            $this->lastApiError = ['status' => 0, 'message' => $t->getMessage()];
            return [];
        }
    }

    /**
     * Suggestion de courte description à partir du lieu, thème et date.
     * Pour l'instant template simple ; on peut brancher un modèle de génération plus tard.
     */
    public function suggestDescription(string $lieu, string $thematique, string $dateStr): string
    {
        $lieu = trim($lieu);
        $thematique = trim($thematique);
        $parts = [];
        if ($thematique !== '') {
            $parts[] = 'Activité ' . $thematique;
        }
        if ($lieu !== '') {
            $parts[] = 'au ' . $lieu;
        }
        if ($dateStr !== '') {
            $parts[] = 'le ' . $dateStr;
        }
        if ($parts === []) {
            return 'Description de l\'événement.';
        }
        return implode('. ', $parts) . '.';
    }

    /**
     * Teste si la clé Hugging Face permet d'appeler l'API image (sans sauvegarder d'image).
     * Retourne ['ok' => bool, 'message' => string].
     */
    public function testImageKey(): array
    {
        $key = $this->getApiKey();
        if ($key === null || $key === '') {
            return ['ok' => false, 'message' => 'Aucune clé configurée.'];
        }
        $modelsToTry = [
            'ByteDance/SDXL-Lightning',
            'runwayml/stable-diffusion-v1-5',
            'CompVis/stable-diffusion-v1-4',
        ];
        $lastMessage = '';
        foreach ($modelsToTry as $model) {
            $url = 'https://api-inference.huggingface.co/models/' . $model;
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                    'json' => ['inputs' => 'test'],
                    'timeout' => 25,
                ]);
                $status = $response->getStatusCode();
                if ($status === 200) {
                    return ['ok' => true, 'message' => 'Clé valide. La génération d\'images est disponible.'];
                }
                if ($status === 401) {
                    return ['ok' => false, 'message' => 'Token invalide ou expiré. Sur huggingface.co/settings/tokens, vérifiez que le token a la permission "Read" (et "Inference" si proposé), ou créez un nouveau token.'];
                }
                if ($status === 403) {
                    return ['ok' => false, 'message' => 'Accès refusé (403). Le token doit avoir la permission d\'appeler l\'API Inference. Vérifiez sur huggingface.co/settings/tokens.'];
                }
                if ($status === 503) {
                    $lastMessage = 'Service temporairement occupé (503). Réessayez dans 1–2 minutes.';
                    continue;
                }
                if ($status === 410) {
                    $lastMessage = 'Le modèle de test n\'est plus disponible (410). Votre clé est reconnue ; essayez quand même « Générer une image (IA) » sur la fiche événement (d\'autres modèles seront utilisés).';
                    continue;
                }
                $lastMessage = 'Erreur ' . $status . ' sur le modèle de test.';
            } catch (\Throwable $t) {
                $msg = $t->getMessage();
                if (str_contains($msg, '401')) {
                    return ['ok' => false, 'message' => 'Token invalide ou expiré. Créez un nouveau token sur huggingface.co/settings/tokens avec la permission "Read".'];
                }
                if (str_contains($msg, '410')) {
                    $lastMessage = 'Le modèle de test n\'est plus disponible (410). Votre clé est reconnue ; essayez « Générer une image (IA) » sur la fiche événement.';
                    continue;
                }
                $lastMessage = 'Erreur : ' . $msg;
            }
        }
        return ['ok' => false, 'message' => $lastMessage ?: 'Impossible de valider la clé. Essayez quand même « Générer une image (IA) » sur la fiche événement.'];
    }

    /**
     * Génère une image à partir d'un prompt via Hugging Face Inference API (text-to-image).
     * Sauvegarde le fichier dans public/uploads/evenements/ et retourne le chemin + contenu binaire.
     *
     * @param string   $prompt   Description pour l'image (ex. titre + thème événement).
     * @param int|null $eventId  Si fourni, nomme le fichier event-{id}.png (écrase au regénération).
     * @return array{path: string, content: string}|null ['path' => chemin relatif, 'content' => binaire] ou null en cas d'erreur.
     */
    public function generateImageFromPrompt(string $prompt, ?int $eventId = null): ?array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }
        if (mb_strlen($prompt) > 1000) {
            $prompt = mb_substr($prompt, 0, 1000);
        }

        $key = $this->getApiKey();
        if ($key === null || $key === '') {
            return $this->generateImageViaPollinations($prompt, $eventId);
        }

        $modelsToTry = [
            'ByteDance/SDXL-Lightning',
            'black-forest-labs/FLUX.1-schnell',
            'runwayml/stable-diffusion-v1-5',
            'CompVis/stable-diffusion-v1-4',
            'stabilityai/stable-diffusion-2-1',
        ];
        $this->lastApiError = null;

        foreach ($modelsToTry as $modelId) {
            $url = 'https://api-inference.huggingface.co/models/' . $modelId;
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $key,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => ['inputs' => $prompt],
                    'timeout' => 120,
                ]);
                $statusCode = $response->getStatusCode();
                if ($statusCode === 410) {
                    continue;
                }
                if ($statusCode === 503) {
                    sleep(20);
                    $response = $this->httpClient->request('POST', $url, [
                        'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
                        'json' => ['inputs' => $prompt],
                        'timeout' => 120,
                    ]);
                    $statusCode = $response->getStatusCode();
                }
                $content = $response->getContent();
                if ($statusCode !== 200 || $content === '') {
                    $this->lastApiError = ['status' => $statusCode, 'message' => 'Modèle ' . $modelId . ' : erreur ' . $statusCode . '.'];
                    continue;
                }
                if (strlen($content) < 100 && str_starts_with(trim($content), '{')) {
                    $this->lastApiError = ['status' => $statusCode, 'message' => 'Modèle ' . $modelId . ' : réponse invalide.'];
                    continue;
                }
                $dir = $this->projectDir . \DIRECTORY_SEPARATOR . 'public' . \DIRECTORY_SEPARATOR . 'uploads' . \DIRECTORY_SEPARATOR . 'evenements';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = $eventId !== null ? 'event-' . $eventId . '.png' : 'event-' . uniqid('', true) . '.png';
                $path = $dir . \DIRECTORY_SEPARATOR . $filename;
                file_put_contents($path, $content);
                return ['path' => 'uploads/evenements/' . $filename, 'content' => $content];
            } catch (\Throwable $t) {
                $this->lastApiError = ['status' => 0, 'message' => $t->getMessage()];
                if (str_contains($t->getMessage(), '410')) {
                    continue;
                }
                continue;
            }
        }

        return $this->generateImageViaPollinations($prompt, $eventId);
    }

    private function getPollinationsApiKey(): ?string
    {
        $env = trim((string) ($_ENV['POLLINATIONS_API_KEY'] ?? getenv('POLLINATIONS_API_KEY') ?: ''));
        if ($env !== '') {
            return $env;
        }
        foreach (['.env.local', '.env'] as $file) {
            $path = $this->projectDir . \DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                continue;
            }
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            $key = $this->parseEnvKey($content, 'POLLINATIONS_API_KEY');
            if ($key !== null && trim($key) !== '') {
                return trim($key);
            }
        }
        return null;
    }

    /**
     * Secours gratuit : génère l'image via Pollinations.ai.
     * Avec POLLINATIONS_API_KEY (gratuit sur enter.pollinations.ai) la génération est plus fiable.
     * @return array{path: string, content: string}|null
     */
    private function generateImageViaPollinations(string $prompt, ?int $eventId = null): ?array
    {
        $shortPrompt = mb_substr(preg_replace('/\s+/', ' ', trim($prompt)), 0, 180);
        if ($shortPrompt === '') {
            $this->lastApiError = ['status' => 0, 'message' => 'Prompt vide.'];
            return null;
        }
        $encoded = rawurlencode($shortPrompt);
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', trim(preg_replace('/\s+/', ' ', $shortPrompt)));
        $slug = mb_substr($slug, 0, 120);
        $pollinationsKey = $this->getPollinationsApiKey();
        $keySuffix = $pollinationsKey !== null && $pollinationsKey !== '' ? '?key=' . rawurlencode($pollinationsKey) : '';
        $endpoints = [
            'https://gen.pollinations.ai/image/' . $encoded . $keySuffix,
            'https://image.pollinations.ai/prompt/' . $encoded . $keySuffix,
            'https://pollinations.ai/p/' . rawurlencode($slug) . $keySuffix,
        ];
        $options = [
            'timeout' => 90,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; AutiCare/1.0; +https://github.com)',
                'Accept' => 'image/png,image/jpeg,image/webp,*/*',
            ],
        ];
        $this->lastApiError = null;
        foreach ($endpoints as $url) {
            $result = $this->tryPollinationsUrl($url, $options, $eventId);
            if ($result !== null) {
                return $result;
            }
            if ($this->lastApiError !== null && $this->lastApiError['status'] >= 500 && $this->lastApiError['status'] < 600) {
                sleep(2);
                $result = $this->tryPollinationsUrl($url, $options, $eventId);
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }

    /**
     * @return array{path: string, content: string}|null
     */
    private function tryPollinationsUrl(string $url, array $options, ?int $eventId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', $url, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            if ($statusCode !== 200 || $content === '') {
                $this->lastApiError = ['status' => $statusCode, 'message' => 'La génération n\'a pas abouti. Réessayez dans un instant.'];
                return null;
            }
            $dir = $this->projectDir . \DIRECTORY_SEPARATOR . 'public' . \DIRECTORY_SEPARATOR . 'uploads' . \DIRECTORY_SEPARATOR . 'evenements';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = $eventId !== null ? 'event-' . $eventId . '.png' : 'event-' . uniqid('', true) . '.png';
            $path = $dir . \DIRECTORY_SEPARATOR . $filename;
            file_put_contents($path, $content);
            return ['path' => 'uploads/evenements/' . $filename, 'content' => $content];
        } catch (\Throwable $t) {
            $this->lastApiError = ['status' => 0, 'message' => 'La génération n\'a pas abouti. Réessayez dans un instant.'];
            return null;
        }
    }
}
