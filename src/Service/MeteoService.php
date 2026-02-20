<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Météo pour les événements (API Open-Meteo, gratuite, sans clé).
 * Retourne la prévision du jour de l'événement, personnalisée AutiCare.
 */
final class MeteoService
{
    private const API_URL = 'https://api.open-meteo.com/v1/forecast';
    private const BIGDATACLOUD_GEO_URL = 'https://api.bigdatacloud.net/data/reverse-geocode-client';

    /** Codes WMO → libellés français (résumé). */
    private const WEATHER_LABELS = [
        0 => 'Ciel dégagé',
        1 => 'Plutôt dégagé',
        2 => 'Partiellement nuageux',
        3 => 'Nuageux',
        45 => 'Brouillard',
        48 => 'Brouillard givrant',
        51 => 'Bruine légère',
        53 => 'Bruine',
        55 => 'Bruine dense',
        56 => 'Bruine verglaçante légère',
        57 => 'Bruine verglaçante dense',
        61 => 'Pluie légère',
        63 => 'Pluie modérée',
        65 => 'Pluie forte',
        66 => 'Pluie verglaçante légère',
        67 => 'Pluie verglaçante forte',
        71 => 'Neige légère',
        73 => 'Neige modérée',
        75 => 'Neige forte',
        77 => 'Grains de neige',
        80 => 'Averses de pluie légères',
        81 => 'Averses de pluie modérées',
        82 => 'Averses de pluie violentes',
        85 => 'Averses de neige légères',
        86 => 'Averses de neige fortes',
        95 => 'Orage',
        96 => 'Orage avec grêle légère',
        99 => 'Orage avec grêle forte',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Prévision météo pour le jour de l'événement (lieu = lat/lng).
     * Uniquement si l'événement a des coordonnées et est présentiel ou hybride.
     *
     * @return array{description_fr: string, temp_min: float, temp_max: float, weathercode: int, message_auticare: string, hourly: list<array{hour: int, label: string, temp: int|null, weathercode: int}>}|null
     */
    public function getWeatherForEvent(Evenement $event): ?array
    {
        $coords = $event->getCoordinates();
        if ($coords === null) {
            return null;
        }
        $mode = $event->getMode();
        if ($mode !== 'presentiel' && $mode !== 'hybride') {
            return null;
        }
        $dateEvent = $event->getDateEvent();
        if ($dateEvent === null) {
            return null;
        }
        $dateStr = $dateEvent->format('Y-m-d');
        [$lat, $lng] = $coords;
        $timezoneIana = $this->getTimezoneForCoordinates($lat, $lng);
        if (@timezone_open($timezoneIana) === false) {
            $timezoneIana = 'Europe/Paris';
        }

        $url = self::API_URL . '?' . http_build_query([
            'latitude' => $lat,
            'longitude' => $lng,
            'daily' => 'temperature_2m_max,temperature_2m_min,weathercode',
            'hourly' => 'temperature_2m,weathercode',
            'timezone' => $timezoneIana,
            'start_date' => $dateStr,
            'end_date' => $dateStr,
        ]);

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);
            $data = $response->toArray();
        } catch (\Throwable) {
            return null;
        }

        $daily = $data['daily'] ?? null;
        $dailyTimes = $daily['time'] ?? [];
        if (!$daily || !is_array($dailyTimes)) {
            return null;
        }
        $idx = null;
        foreach ($dailyTimes as $i => $t) {
            if ($t === $dateStr || (is_string($t) && str_starts_with($t, $dateStr))) {
                $idx = (int) $i;
                break;
            }
        }
        if ($idx === null) {
            $idx = 0;
        }
        $tempMax = $daily['temperature_2m_max'][$idx] ?? null;
        $tempMin = $daily['temperature_2m_min'][$idx] ?? null;
        $code = (int) ($daily['weathercode'][$idx] ?? 0);
        $desc = self::WEATHER_LABELS[$code] ?? 'Conditions variables';

        $hourly = [];
        $hourlyRaw = $data['hourly'] ?? null;
        if ($hourlyRaw && !empty($hourlyRaw['time'])) {
            $prefix = $dateStr . 'T';
            foreach ($hourlyRaw['time'] as $i => $isoTime) {
                if (!is_string($isoTime) || strpos($isoTime, $prefix) !== 0) {
                    continue;
                }
                $hour = (int) substr($isoTime, 11, 2);
                $temp = isset($hourlyRaw['temperature_2m'][$i]) ? round((float) $hourlyRaw['temperature_2m'][$i]) : null;
                $wcode = isset($hourlyRaw['weathercode'][$i]) ? (int) $hourlyRaw['weathercode'][$i] : 0;
                $hourly[] = [
                    'hour' => $hour,
                    'label' => $hour . 'h',
                    'temp' => $temp,
                    'weathercode' => $wcode,
                ];
            }
            usort($hourly, fn (array $a, array $b): int => $a['hour'] <=> $b['hour']);
        }

        return [
            'description_fr' => $desc,
            'temp_min' => $tempMin !== null ? (float) $tempMin : 0.0,
            'temp_max' => $tempMax !== null ? (float) $tempMax : 0.0,
            'weathercode' => $code,
            'message_auticare' => sprintf(
                'Météo prévue pour votre journée AutiCare le %s : %s. Température entre %s et %s °C.',
                $dateEvent->format('d/m/Y'),
                $desc,
                $tempMin !== null ? (string) round((float) $tempMin) : '—',
                $tempMax !== null ? (string) round((float) $tempMax) : '—'
            ),
            'hourly' => $hourly,
        ];
    }

    /**
     * Récupère le fuseau IANA (ex. Africa/Tunis) pour des coordonnées via BigDataCloud (gratuit, sans clé).
     * En cas d'échec, retourne Europe/Paris.
     */
    private function getTimezoneForCoordinates(float $lat, float $lng): string
    {
        $url = self::BIGDATACLOUD_GEO_URL . '?' . http_build_query([
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 3]);
            $data = $response->toArray();
        } catch (\Throwable) {
            return 'Europe/Paris';
        }
        $informative = $data['localityInfo']['informative'] ?? [];
        if (!is_array($informative)) {
            return 'Europe/Paris';
        }
        $timeZoneDescriptions = ['time zone', 'fuseau horaire', 'timezone'];
        foreach ($informative as $item) {
            if (!is_array($item)) {
                continue;
            }
            $desc = strtolower(trim((string) ($item['description'] ?? '')));
            if (!\in_array($desc, $timeZoneDescriptions, true)) {
                continue;
            }
            $name = $item['name'] ?? '';
            if (is_string($name) && str_contains($name, '/') && strlen($name) <= 40) {
                return $name;
            }
        }
        return 'Europe/Paris';
    }
}
