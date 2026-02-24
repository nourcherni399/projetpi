<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RappelSmsService
{
    private ?string $lastError = null;

    public function __construct(
        private readonly TexterInterface $texter,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $nomCabinet = 'AutiCare',
        private readonly string $defaultCountryCode = '216',
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Envoie un SMS de rappel pour un rendez-vous et marque le RDV comme rappelé.
     */
    public function envoyerRappel(RendezVous $rdv): bool
    {
        $this->lastError = null;
        $telephone = $rdv->getTelephone();
        if ($telephone === null || $telephone === '') {
            $this->lastError = 'Aucun numéro de téléphone.';
            return false;
        }

        $phoneE164 = $this->normalizePhoneToE164($telephone);
        if ($phoneE164 === '') {
            $this->lastError = sprintf('Numéro invalide ou impossible à formater : %s', $telephone);
            return false;
        }

        $this->ensureToken($rdv);
        $message = $this->construireMessage($rdv);
        if ($message === '') {
            $this->lastError = 'Impossible de construire le message (date/heure manquantes).';
            return false;
        }

        try {
            $this->texter->send(new SmsMessage($phoneE164, $message));
            $rdv->setRappelSmsEnvoyeAt(new \DateTimeImmutable('now'));
            $this->entityManager->flush();
            return true;
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            if ($e->getPrevious() !== null) {
                $detail .= ' (' . $e->getPrevious()->getMessage() . ')';
            }
            $this->lastError = $detail;
            return false;
        }
    }

    /**
     * Met le numéro au format E.164 (ex: +21695244861).
     */
    private function normalizePhoneToE164(string $telephone): string
    {
        $raw = preg_replace('/[\s\.\-\(\)]/', '', $telephone);
        if ($raw === null || $raw === '') {
            return '';
        }
        if (str_starts_with($raw, '+')) {
            return '+' . ltrim($raw, '+');
        }
        if (str_starts_with($raw, '00')) {
            return '+' . substr($raw, 2);
        }
        if (str_starts_with($raw, '0')) {
            $raw = substr($raw, 1);
        }
        return '+' . $this->defaultCountryCode . $raw;
    }

    private function ensureToken(RendezVous $rdv): void
    {
        if ($rdv->getTokenAnnulation() !== null && $rdv->getTokenAnnulation() !== '') {
            return;
        }
        $rdv->setTokenAnnulation(bin2hex(random_bytes(32)));
        $this->entityManager->flush();
    }

    private function construireMessage(RendezVous $rdv): string
    {
        $dateRdv = $rdv->getDateRdv();
        $dispo = $rdv->getDisponibilite();
        $heureDebut = $dispo?->getHeureDebut();

        $dateStr = $dateRdv !== null ? $dateRdv->format('d/m/Y') : '--/--/----';
        $heureStr = $heureDebut !== null ? $heureDebut->format('H:i') : '--:--';

        $nom = trim(($rdv->getPrenom() ?? '') . ' ' . ($rdv->getNom() ?? ''));
        $nomAffichage = $nom !== '' ? $nom : 'Patient';

        $base = sprintf(
            '%s - Rappel RDV : le %s à %s. %s. Merci de vous présenter à l\'heure.',
            $this->nomCabinet,
            $dateStr,
            $heureStr,
            $nomAffichage
        );

        $token = $rdv->getTokenAnnulation();
        if ($token !== null && $token !== '') {
            $url = $this->urlGenerator->generate('rdv_patient_page', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            $base .= ' Annuler/reporter : ' . $url;
        }

        return $base;
    }
}
