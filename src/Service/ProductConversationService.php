<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Chat conversationnel type ChatGPT pour remplir les champs produit.
 * Utilise OpenAI ou API compatible (DeepSeek, etc.).
 */
final class ProductConversationService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un assistant e-commerce AutiCare. Tu aides le client à créer une fiche produit. Parle naturellement, comme ChatGPT.

Extrais de la conversation : nom, description, categorie, prix (en dinars).

Catégories valides : sensoriels, bruit_et_environnement, education_apprentissage, communication_langage, jeux_therapeutiques_developpement, bien_etre_relaxation, vie_quotidienne.

À CHAQUE réponse, ajoute à la fin exactement ce bloc (sur une nouvelle ligne) :
[FICHE]{"nom":"valeur ou null","description":"valeur ou null","categorie":"valeur ou null","prix":nombre ou 0,"ready":true ou false}[/FICHE]

Mets ready:true quand tu as nom ET (description OU categorie) pour créer le produit. Sinon ready:false.
Exemple : [FICHE]{"nom":"Coussin sensoriel","description":"Pour apaiser","categorie":"sensoriels","prix":80,"ready":true}[/FICHE]
PROMPT;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gpt-4o',
        private readonly ?string $apiBaseUrl = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '';
    }

    /**
     * Envoie la conversation à l'API et récupère la réponse + champs produit extraits.
     *
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array{reply: string, product_data: array{nom?: string, description?: string, categorie?: string, prix?: float}, ready: bool}
     */
    public function chat(array $messages): array
    {
        if (!$this->isConfigured()) {
            return [
                'reply' => 'Le chat IA n\'est pas configuré. Configurez OPENAI_API_KEY dans .env.',
                'product_data' => [],
                'ready' => false,
            ];
        }

        try {
            $client = $this->createClient();
            $recent = array_slice($messages, -20);
            $apiMessages = [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ...$this->formatMessages($recent),
            ];

            $response = $client->chat()->create([
                'model' => $this->model,
                'messages' => $apiMessages,
                'temperature' => 0.7,
            ]);
            $content = trim($response->choices[0]->message->content ?? '');

            return $this->parseResponse($content);
        } catch (\Throwable $e) {
            $this->logger->error('ProductConversationService chat', ['error' => $e->getMessage()]);
            return [
                'reply' => 'Désolé, une erreur est survenue. Réessayez.',
                'product_data' => [],
                'ready' => false,
            ];
        }
    }

    private function createClient(): \OpenAI\Client
    {
        if ($this->apiBaseUrl !== null && $this->apiBaseUrl !== '') {
            return \OpenAI::factory()
                ->withApiKey($this->apiKey)
                ->withBaseUri(rtrim($this->apiBaseUrl, '/') . '/v1')
                ->make();
        }
        return \OpenAI::client($this->apiKey);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return list<array{role: string, content: string}>
     */
    private function formatMessages(array $messages): array
    {
        $result = [];
        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            if ($role === 'assistant') {
                $role = 'assistant';
            } elseif ($role === 'user') {
                $role = 'user';
            } else {
                $role = 'user';
            }
            $content = $m['content'] ?? '';
            $result[] = ['role' => $role, 'content' => $content];
        }
        return $result;
    }

    private function parseResponse(string $content): array
    {
        $reply = $content;
        $productData = [];
        $ready = false;

        if (preg_match('/\[FICHE\](.*?)\[\/FICHE\]/s', $content, $m)) {
            $json = trim($m[1]);
            $reply = trim(preg_replace('/\[FICHE\].*?\[\/FICHE\]/s', '', $content));
            $data = json_decode($json, true);
            if (is_array($data)) {
                $productData = array_filter([
                    'nom' => isset($data['nom']) && $data['nom'] ? (string) $data['nom'] : null,
                    'description' => isset($data['description']) && $data['description'] ? (string) $data['description'] : null,
                    'categorie' => isset($data['categorie']) && $data['categorie'] ? $this->normalizeCategorie((string) $data['categorie']) : null,
                    'prix' => isset($data['prix']) && (is_int($data['prix']) || is_float($data['prix'])) ? (float) $data['prix'] : (isset($data['prix']) && is_numeric($data['prix']) ? (float) $data['prix'] : null),
                ], fn ($v) => $v !== null && $v !== '');
                $ready = !empty($data['ready']);
            }
        }

        $reply = trim(preg_replace('/\n+/', ' ', $reply));
        if ($reply === '') {
            $reply = 'Comment puis-je vous aider ?';
        }

        return [
            'reply' => $reply,
            'product_data' => $productData,
            'ready' => $ready,
        ];
    }

    private function normalizeCategorie(string $v): string
    {
        $valid = [
            'sensoriels', 'bruit_et_environnement', 'education_apprentissage',
            'communication_langage', 'jeux_therapeutiques_developpement',
            'bien_etre_relaxation', 'vie_quotidienne',
        ];
        $v = strtolower(trim(preg_replace('/\s+/', '_', $v)));
        return in_array($v, $valid, true) ? $v : 'vie_quotidienne';
    }
}
