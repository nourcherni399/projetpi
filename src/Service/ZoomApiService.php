<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Intégration API Zoom (Server-to-Server OAuth) pour créer des réunions.
 * Créer une app "Server-to-Server OAuth" sur https://marketplace.zoom.us/
 * et renseigner ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, ZOOM_CLIENT_SECRET dans .env.
 */
final class ZoomApiService
{
    private const TOKEN_URL = 'https://zoom.us/oauth/token';
    private const API_BASE = 'https://api.zoom.us/v2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $accountId,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    /** True si les identifiants sont configurés. */
    public function isConfigured(): bool
    {
        return $this->accountId !== '' && $this->clientId !== '' && $this->clientSecret !== '';
    }

    /**
     * Crée une réunion Zoom et retourne l'URL de participation (join_url) ou une erreur.
     *
     * @return array{join_url: string, start_url: string}|array{error: string}
     */
    public function createMeeting(
        string $topic,
        \DateTimeInterface $startTime,
        int $durationMinutes = 60,
        string $timezone = 'Africa/Tunis',
    ): array {
        if (!$this->isConfigured()) {
            return ['error' => 'Zoom non configuré (ZOOM_ACCOUNT_ID, ZOOM_CLIENT_ID, ZOOM_CLIENT_SECRET dans .env).'];
        }

        $token = $this->getAccessToken();
        if ($token === null) {
            return ['error' => 'Impossible d\'obtenir le token Zoom. Vérifiez les identifiants.'];
        }

        try {
            $startTimeStr = $startTime->format('Y-m-d\TH:i:s');
            $response = $this->httpClient->request('POST', self::API_BASE . '/users/me/meetings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'topic' => $topic,
                    'type' => 2,
                    'start_time' => $startTimeStr,
                    'duration' => $durationMinutes,
                    'timezone' => $timezone,
                ],
                'timeout' => 15,
            ]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            return ['error' => 'Erreur Zoom : ' . $e->getMessage()];
        }

        $joinUrl = $data['join_url'] ?? null;
        $startUrl = $data['start_url'] ?? $joinUrl;
        if ($joinUrl === null || $joinUrl === '') {
            return ['error' => 'Réponse Zoom invalide (pas de join_url).'];
        }

        return [
            'join_url' => $joinUrl,
            'start_url' => $startUrl ?? $joinUrl,
        ];
    }

    private function getAccessToken(): ?string
    {
        $auth = base64_encode($this->clientId . ':' . $this->clientSecret);
        try {
            $response = $this->httpClient->request('POST', self::TOKEN_URL, [
                'headers' => [
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query([
                    'grant_type' => 'account_credentials',
                    'account_id' => $this->accountId,
                ]),
                'timeout' => 10,
            ]);
            $data = $response->toArray();
        } catch (\Throwable) {
            return null;
        }
        return $data['access_token'] ?? null;
    }
}
