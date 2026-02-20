<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Message de bienvenue selon le pays du visiteur (géolocalisation par IP).
 * API gratuite ip-api.com (sans clé). Personnalisé AutiCare.
 */
final class WelcomeMessageService
{
    /** API gratuite, pas de clé. Limite ~45 req/min. */
    private const IP_API_URL = 'http://ip-api.com/json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Récupère le pays (nom en français si possible) depuis l'IP.
     * Retourne null en cas d'erreur ou IP locale.
     */
    public function getCountryFromIp(string $ip): ?string
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }
        $url = self::IP_API_URL . '/' . $ip . '?fields=country,countryCode&lang=fr';
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 3]);
            $data = $response->toArray();
            if (isset($data['country']) && $data['country'] !== '') {
                return (string) $data['country'];
            }
            if (isset($data['countryCode']) && $data['countryCode'] !== '') {
                return (string) $data['countryCode'];
            }
        } catch (\Throwable) {
            return null;
        }
        return null;
    }

    /**
     * Message de bienvenue AutiCare pour un pays donné.
     */
    public function getWelcomeMessage(string $country): string
    {
        $country = trim($country);
        if ($country === '') {
            return 'Bienvenue aux familles AutiCare.';
        }
        return sprintf('Bienvenue aux familles AutiCare — %s.', $country);
    }
}
