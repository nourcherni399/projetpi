<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Géocodage gratuit via Nominatim (OpenStreetMap). Aucune clé API requise.
 * Respecte la politique d'utilisation : 1 requête/seconde, User-Agent identifiant l'app.
 */
final class NominatimGeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $userAgent = 'AutiCare/1.0 (https://auticare.local)',
    ) {
    }

    /**
     * Retourne ['lat' => float, 'lng' => float] ou ['error' => string].
     *
     * @return array{lat: float, lng: float}|array{error: string}
     */
    public function geocode(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            return ['error' => 'Adresse vide.'];
        }

        try {
            $response = $this->httpClient->request('GET', self::NOMINATIM_URL, [
                'query' => [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ],
                'headers' => [
                    'User-Agent' => $this->userAgent,
                ],
                'timeout' => 15,
            ]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            return ['error' => 'Impossible de contacter le service de géocodage. ' . $detail];
        }

        if (!is_array($data) || count($data) === 0) {
            return ['error' => 'Adresse introuvable.'];
        }

        $first = $data[0];
        $lat = $first['lat'] ?? null;
        $lon = $first['lon'] ?? null;
        if ($lat === null || $lon === null) {
            return ['error' => 'Réponse invalide.'];
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lon,
        ];
    }
}
