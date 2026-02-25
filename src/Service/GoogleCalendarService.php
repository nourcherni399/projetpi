<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Medcin;
use App\Entity\RendezVous;
use Psr\Log\LoggerInterface;

/**
 * Intégration Google Calendar API : crée un événement dans un calendrier
 * lorsque un rendez-vous est confirmé (Service Account).
 */
final class GoogleCalendarService
{
    private string $credentialsPath;
    private string $calendarId;

    public function __construct(
        ?string $credentialsPath,
        ?string $calendarId,
        private readonly LoggerInterface $logger,
    ) {
        $this->credentialsPath = $credentialsPath ?? '';
        $this->calendarId = $calendarId ?? '';
    }

    /**
     * Crée un événement dans Google Calendar à partir d'un RendezVous confirmé.
     * Retourne l'ID de l'événement créé ou null en cas d'erreur / config désactivée.
     */
    public function createEventFromRendezVous(RendezVous $rdv): ?string
    {
        if ($this->credentialsPath === '') {
            return null;
        }
        $medecin = $rdv->getMedecin();
        $calendarId = $medecin instanceof Medcin && $medecin->getGoogleCalendarId() !== null && $medecin->getGoogleCalendarId() !== ''
            ? $medecin->getGoogleCalendarId()
            : $this->calendarId;
        if ($calendarId === '') {
            return null;
        }

        $path = $this->resolveCredentialsPath($this->credentialsPath);
        if ($path === null || !is_file($path)) {
            $this->logger->warning('Google Calendar: fichier de credentials introuvable.', ['path' => $this->credentialsPath]);
            return null;
        }

        try {
            $client = $this->createClient($path);
            $service = new \Google\Service\Calendar($client);

            $dateRdv = $rdv->getDateRdv();
            $dispo = $rdv->getDisponibilite();
            $medecin = $rdv->getMedecin();

            if ($dateRdv === null || $dispo === null) {
                return null;
            }

            $heureDebut = $dispo->getHeureDebut();
            $heureFin = $dispo->getHeureFin();
            if ($heureDebut === null || $heureFin === null) {
                return null;
            }

            $startDt = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $dateRdv->format('Y-m-d') . ' ' . $heureDebut->format('H:i:s'),
                new \DateTimeZone('Europe/Paris')
            );
            $endDt = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $dateRdv->format('Y-m-d') . ' ' . $heureFin->format('H:i:s'),
                new \DateTimeZone('Europe/Paris')
            );
            if ($startDt === false || $endDt === false) {
                return null;
            }

            $medecinNom = $medecin ? trim(($medecin->getPrenom() ?? '') . ' ' . ($medecin->getNom() ?? '')) : 'Praticien';
            $patientNom = trim(($rdv->getPrenom() ?? '') . ' ' . ($rdv->getNom() ?? ''));
            $summary = 'RDV AutiCare : ' . $patientNom . ' avec Dr ' . $medecinNom;
            $location = $medecin && $medecin->getAdresseCabinet() ? $medecin->getAdresseCabinet() : '';
            $description = "Rendez-vous confirmé via AutiCare.\nPatient : {$patientNom}\nPraticien : Dr {$medecinNom}";

            $event = new \Google\Service\Calendar\Event([
                'summary' => $summary,
                'description' => $description,
                'location' => $location,
                'start' => [
                    'dateTime' => $startDt->format(\DateTimeInterface::RFC3339),
                    'timeZone' => 'Europe/Paris',
                ],
                'end' => [
                    'dateTime' => $endDt->format(\DateTimeInterface::RFC3339),
                    'timeZone' => 'Europe/Paris',
                ],
            ]);

            $created = $service->events->insert($calendarId, $event);
            return $created->getId();
        } catch (\Throwable $e) {
            $this->logger->error('Google Calendar: erreur création événement.', [
                'rdv_id' => $rdv->getId(),
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Génère une URL "Ajouter à Google Calendar" (sans appel API).
     */
    public function getAddToCalendarUrl(RendezVous $rdv): string
    {
        $dateRdv = $rdv->getDateRdv();
        $dispo = $rdv->getDisponibilite();
        $medecin = $rdv->getMedecin();
        if ($dateRdv === null || $dispo === null) {
            return '';
        }
        $heureDebut = $dispo->getHeureDebut();
        $heureFin = $dispo->getHeureFin();
        if ($heureDebut === null || $heureFin === null) {
            return '';
        }

        $start = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $dateRdv->format('Y-m-d') . ' ' . $heureDebut->format('H:i:s'),
            new \DateTimeZone('Europe/Paris')
        );
        $end = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $dateRdv->format('Y-m-d') . ' ' . $heureFin->format('H:i:s'),
            new \DateTimeZone('Europe/Paris')
        );
        if ($start === false || $end === false) {
            return '';
        }

        $medecinNom = $medecin ? trim(($medecin->getPrenom() ?? '') . ' ' . ($medecin->getNom() ?? '')) : 'Praticien';
        $patientNom = trim(($rdv->getPrenom() ?? '') . ' ' . ($rdv->getNom() ?? ''));
        $summary = 'RDV AutiCare : ' . $patientNom . ' avec Dr ' . $medecinNom;
        $location = $medecin && $medecin->getAdresseCabinet() ? $medecin->getAdresseCabinet() : '';
        $description = "Rendez-vous AutiCare - Patient : {$patientNom}, Praticien : Dr {$medecinNom}";

        $params = [
            'action' => 'TEMPLATE',
            'text' => $summary,
            'dates' => $start->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z') . '/' . $end->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'details' => $description,
            'location' => $location,
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    private function createClient(string $credentialsPath): \Google\Client
    {
        $client = new \Google\Client();
        $client->setApplicationName('AutiCare');
        $client->setAuthConfig($credentialsPath);
        $client->setScopes(['https://www.googleapis.com/auth/calendar.events']);
        $client->setAccessType('offline');
        return $client;
    }

    private function resolveCredentialsPath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, '/') || (strlen($path) >= 2 && $path[1] === ':')) {
            return $path;
        }
        $projectDir = dirname(__DIR__, 2);
        return $projectDir . '/' . $path;
    }
}
