<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Géocodage gratuit via Nominatim (OpenStreetMap) avec fallback Photon (Komoot).
 * Aucune clé API requise. Cache obligatoire. Fallback Photon en cas de rate-limit (429).
 */
final class NominatimGeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const PHOTON_URL = 'https://photon.komoot.io/api/';
    private const CACHE_TTL = 86400; // 24 h

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $userAgent = 'AutiCare/1.0 (contact@auticare.fr)',
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

        $cacheKey = 'nominatim_' . md5(mb_strtolower($address));

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($address) {
                $item->expiresAfter(self::CACHE_TTL);
                return $this->doGeocode($address);
            });
        } catch (\RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{lat: float, lng: float}|array{error: string}
     */
    private function doGeocode(string $address): array
    {
        try {
            return $this->fetchNominatim($address);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isRateLimit = str_contains($msg, '429') || str_contains($msg, 'Too Many Requests');
            if ($isRateLimit) {
                sleep(1);
                try {
                    return $this->fetchNominatim($address);
                } catch (\Throwable $retryEx) {
                    $fallback = $this->fetchPhoton($address);
                    if (!isset($fallback['error'])) {
                        return $fallback;
                    }
                    throw new \RuntimeException('Trop de requêtes. Patientez quelques secondes et réessayez.');
                }
            }
            $fallback = $this->fetchPhoton($address);
            if (!isset($fallback['error'])) {
                return $fallback;
            }
            throw new \RuntimeException('Impossible de contacter le service de géocodage. Veuillez réessayer plus tard.');
        }
    }

    /**
     * @return array{lat: float, lng: float}|array{error: string}
     */
    private function fetchNominatim(string $address): array
    {
        $response = $this->httpClient->request('GET', self::NOMINATIM_URL, [
            'query' => [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
            ],
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 429) {
            throw new \RuntimeException('HTTP/2 429 Too Many Requests');
        }

        $data = $response->toArray();

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

    /**
     * Fallback via Photon (Komoot) - service alternatif, pas de limite stricte 1req/s.
     *
     * @return array{lat: float, lng: float}|array{error: string}
     */
    private function fetchPhoton(string $address): array
    {
        try {
            $response = $this->httpClient->request('GET', self::PHOTON_URL, [
                'query' => [
                    'q' => $address,
                    'limit' => 1,
                ],
                'headers' => [
                    'User-Agent' => $this->userAgent,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();
            $features = $data['features'] ?? [];
            if (!is_array($features) || count($features) === 0) {
                return ['error' => 'Adresse introuvable.'];
            }

            $coords = $features[0]['geometry']['coordinates'] ?? null;
            if (!$coords || !is_array($coords) || count($coords) < 2) {
                return ['error' => 'Réponse invalide.'];
            }

            return [
                'lng' => (float) $coords[0],
                'lat' => (float) $coords[1],
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}