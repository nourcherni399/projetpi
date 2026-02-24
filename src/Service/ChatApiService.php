<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Service chatbot AutiCare - Utilise Groq API avec recherche de prix
 */
final class ChatApiService
{
    private const SYSTEM_PROMPT_BASE = <<<'PROMPT'
Tu es un assistant intelligent et sympa pour AutiCare (boutique produits autisme).

RÈGLE 1 - COMPRENDRE TOUT :
Tu comprends le français familier, l'argot, le SMS, les abréviations :
- "slt/cc/bjr" = salut → "Salut !"
- "cv/cava/sa va" = ça va → "Ça va bien, et toi ?"
- "pc" = ordinateur/PC
- "nn/non" = non
- "oui/ui/ok/ouais" = oui
- "jveux/je veux" = je veux
- "pk/prq" = pourquoi
- "jsp" = je sais pas
- "mrc/merci" = merci → "De rien !"

RÈGLE 2 - RÉPONSES COURTES :
Réponds en 1-2 phrases max. Sois naturel et amical.

RÈGLE 3 - CRÉATION PRODUIT :
Si l'utilisateur veut créer/ajouter un produit :
1. Demande le NOM
2. Puis la DESCRIPTION (à quoi ça sert)
3. Puis la CATÉGORIE (détente, jeux, éducation, sensoriel...)
4. IMPORTANT POUR LE PRIX : {PRICE_INSTRUCTION}

Pose UNE seule question à la fois. Quand tu as les 4 infos :
"Récap : [nom], [description], catégorie [cat], [prix] DT. C'est bon ?"

RÈGLE 4 - CONFIRMATION :
- "oui/ok/c'est bon/yes/ouais" → ready:true
- "non/nn/pas ça/change" → demande quoi modifier, ready:false

RÈGLE 5 - JSON OBLIGATOIRE :
Termine TOUJOURS par ce bloc (remplis les champs collectés) :
[PRODUIT]{"nom":null,"description":null,"categorie":null,"prix":0,"image_keywords":null,"ready":false,"price_source":null}[/PRODUIT]

image_keywords = 2-3 mots ANGLAIS pour l'image (ex: "blue plush toy")
price_source = la source du prix si trouvé en ligne (ex: "Amazon", "Jumia", null si prix donné par l'utilisateur)
PROMPT;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ProductPriceSearchService $priceSearchService,
        private readonly ?string $groqApiKey = null,
    ) {
    }

    /**
     * @return array{reply: string|null, product_data: array, ready: bool, price_search: ?array}
     */
    public function sendMessage(string $userMessage, array $history = [], ?array $priceContext = null): array
    {
        $apiKey = $this->groqApiKey ?? '';
        if ($apiKey === '') {
            $this->logger->warning('CHAT_API_KEY non configurée');
            return ['reply' => 'Le chat IA n\'est pas configuré. Ajoutez CHAT_API_KEY dans .env.local.', 'product_data' => [], 'ready' => false, 'price_search' => $priceContext];
        }
        $model = 'llama-3.3-70b-versatile';
        $endpoint = 'https://api.groq.com/openai/v1/chat/completions';

        // Construire l'instruction de prix basée sur le contexte
        $priceInstruction = $this->buildPriceInstruction($priceContext);
        $systemPrompt = str_replace('{PRICE_INSTRUCTION}', $priceInstruction, self::SYSTEM_PROMPT_BASE);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($history as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 600,
            'temperature' => 0.7,
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            $this->logger->error('Erreur Groq API', ['error' => $error, 'httpCode' => $httpCode, 'response' => $response]);
            return ['reply' => null, 'product_data' => [], 'ready' => false, 'price_search' => null];
        }

        $data = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            $this->logger->error('Réponse Groq vide', ['data' => $data]);
            return ['reply' => null, 'product_data' => [], 'ready' => false, 'price_search' => null];
        }

        return $this->parseResponse($content, $priceContext);
    }

    /**
     * Recherche le prix d'un produit sur les sites e-commerce
     */
    public function searchProductPrice(string $productName): array
    {
        return $this->priceSearchService->searchProduct($productName);
    }

    /**
     * Construit l'instruction de prix pour le prompt système
     */
    private function buildPriceInstruction(?array $priceContext): string
    {
        if ($priceContext === null || !$priceContext['found']) {
            return "Demande le prix en dinars tunisiens (DT).";
        }

        $suggested = (float) $priceContext['suggested_price_tnd'];
        $minPrice = max(0, $suggested - 20);
        $maxPrice = $suggested + 20;

        $instruction = "J'ai trouvé ce produit en ligne ! Voici les prix :\n";
        
        $shown = 0;
        foreach ($priceContext['results'] as $result) {
            if ($shown >= 3) break;
            $source = $result['source'];
            $price = number_format($result['price_tnd'], 2);
            $instruction .= "- {$source} : {$price} DT\n";
            $shown++;
        }

        $suggestedFormatted = number_format($suggested, 2);
        $minFormatted = number_format($minPrice, 2);
        $maxFormatted = number_format($maxPrice, 2);
        
        $instruction .= "\nPRIX SUGGÉRÉ : {$suggestedFormatted} DT (source: {$priceContext['best_price']['source']})";
        $instruction .= "\nRÈGLE STRICTE : Le prix doit être entre {$minFormatted} DT et {$maxFormatted} DT (±20 DT du prix suggéré).";
        $instruction .= "\nSi l'utilisateur donne un prix hors de cette fourchette, refuse poliment et rappelle la fourchette autorisée.";
        $instruction .= "\nPropose le prix suggéré et demande s'il veut l'utiliser ou en mettre un autre (entre {$minFormatted} et {$maxFormatted} DT).";

        return $instruction;
    }

    private function parseResponse(string $content, ?array $priceContext = null): array
    {
        $reply = $content;
        $productData = [];
        $ready = false;

        if (preg_match('/\[PRODUIT\](.*?)\[\/PRODUIT\]/s', $content, $m)) {
            $reply = trim(preg_replace('/\[PRODUIT\].*?\[\/PRODUIT\]/s', '', $content));
            $json = trim($m[1]);
            $data = json_decode($json, true);
            if (is_array($data)) {
                if (!empty($data['nom'])) {
                    $productData['nom'] = (string) $data['nom'];
                }
                if (!empty($data['description'])) {
                    $productData['description'] = (string) $data['description'];
                }
                if (!empty($data['categorie'])) {
                    $productData['categorie'] = $this->normalizeCategorie((string) $data['categorie']);
                }
                if (isset($data['prix']) && $data['prix'] > 0) {
                    $productData['prix'] = (float) $data['prix'];
                }
                if (!empty($data['image_keywords'])) {
                    $productData['image_keywords'] = $this->sanitizeImageKeywords((string) $data['image_keywords']);
                }
                if (!empty($data['price_source'])) {
                    $productData['price_source'] = (string) $data['price_source'];
                }
                $ready = (bool) ($data['ready'] ?? false);
            }
        }

        return [
            'reply' => $reply,
            'product_data' => $productData,
            'ready' => $ready,
            'price_search' => $priceContext,
        ];
    }

    private function normalizeCategorie(string $value): string
    {
        $value = mb_strtolower(trim($value));
        
        $mapping = [
            'sensoriels' => 'sensoriels',
            'sensoriel' => 'sensoriels',
            'bruit' => 'bruit_et_environnement',
            'bruit_et_environnement' => 'bruit_et_environnement',
            'education' => 'education_apprentissage',
            'éducation' => 'education_apprentissage',
            'education_apprentissage' => 'education_apprentissage',
            'communication' => 'communication_langage',
            'communication_langage' => 'communication_langage',
            'jeux' => 'jeux_therapeutiques_developpement',
            'jeux_therapeutiques_developpement' => 'jeux_therapeutiques_developpement',
            'bien_etre' => 'bien_etre_relaxation',
            'bien_etre_relaxation' => 'bien_etre_relaxation',
            'relaxation' => 'bien_etre_relaxation',
            'detente' => 'bien_etre_relaxation',
            'détente' => 'bien_etre_relaxation',
            'vie_quotidienne' => 'vie_quotidienne',
            'quotidien' => 'vie_quotidienne',
        ];

        return $mapping[$value] ?? 'vie_quotidienne';
    }

    private function sanitizeImageKeywords(string $value): string
    {
        $keywords = strtolower(trim($value));
        $keywords = preg_replace('/[^a-z0-9\s]/', '', $keywords);
        $keywords = preg_replace('/\s+/', ' ', $keywords);
        $words = explode(' ', $keywords);
        $words = array_slice($words, 0, 5);
        return implode(' ', $words);
    }
}
