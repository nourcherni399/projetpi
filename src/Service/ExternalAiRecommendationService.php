<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\Medcin;
use App\Entity\Produit;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExternalAiRecommendationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $enabled,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
    ) {
    }

    public function isEnabled(): bool
    {
        return in_array(strtolower(trim($this->enabled)), ['1', 'true', 'yes', 'on'], true)
            && trim($this->apiKey) !== '';
    }

    /**
     * @param array<string,mixed> $profile
     * @param list<Produit> $products
     * @param list<Evenement> $events
     * @param list<Medcin> $doctors
     * @return array{product_ids:list<int>,event_ids:list<int>,doctor_ids:list<int>,reasons:list<string>}|null
     */
    public function rank(array $profile, array $products, array $events, array $doctors): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $payload = [
            'profile' => $profile,
            'products' => array_map(static fn (Produit $p) => [
                'id' => $p->getId(),
                'name' => $p->getNom(),
                'category' => $p->getCategorie()?->value,
            ], $products),
            'events' => array_map(static fn (Evenement $e) => [
                'id' => $e->getId(),
                'title' => $e->getTitle(),
                'theme' => $e->getThematique()?->getNomThematique(),
            ], $events),
            'doctors' => array_map(static fn (Medcin $d) => [
                'id' => $d->getId(),
                'name' => trim((string) $d->getNom() . ' ' . (string) $d->getPrenom()),
                'speciality' => $d->getSpecialite(),
            ], $doctors),
        ];

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are the only recommendation brain. Use behavior frequency, recency, purchased product categories, and event registration themes to infer strongest interests, especially for ROLE_PATIENT and ROLE_PARENT. Return strict JSON only.',
            ],
            [
                'role' => 'user',
                'content' => "Analyse le profil, les top signaux, l'historique brut, les categories de produits deja achetes, les themes d'evenements deja suivis, et les focus_signals. Priorite forte: proposer d'abord ce qui correspond aux focus_signals (interets dominants de ce client), puis diversifier legerement seulement si necessaire. Exemple attendu: si achats anti-stress, prioriser produits similaires anti-stress; si inscriptions a des evenements de socialisation, proposer des evenements du meme style. Respecte aussi l'exclusion des elements deja achetes/deja inscrits presentes dans les donnees. Retourne seulement du JSON valide avec les clés exactes: product_ids, event_ids, doctor_ids, reasons. Contraintes: 0 a 3 ids par liste, ids existants uniquement dans les candidats fournis, reasons = 2 a 4 raisons courtes en français, orientees vers les besoins du client.\nDonnées:\n"
                    . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.2,
                    'messages' => $messages,
                ],
                'timeout' => 20,
            ])->toArray(false);

            $content = (string) ($response['choices'][0]['message']['content'] ?? '');
            if ($content === '') {
                return null;
            }
            $decoded = json_decode($content, true);
            if (!is_array($decoded) && preg_match('/\{.*\}/s', $content, $m) === 1) {
                $decoded = json_decode($m[0], true);
            }
            if (!is_array($decoded)) {
                return null;
            }

            return [
                'product_ids' => array_values(array_map('intval', (array) ($decoded['product_ids'] ?? []))),
                'event_ids' => array_values(array_map('intval', (array) ($decoded['event_ids'] ?? []))),
                'doctor_ids' => array_values(array_map('intval', (array) ($decoded['doctor_ids'] ?? []))),
                'reasons' => array_values(array_filter(array_map(static fn ($x) => trim((string) $x), (array) ($decoded['reasons'] ?? [])))),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}

