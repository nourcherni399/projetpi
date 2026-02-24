<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RendezVous;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class RendezVousConfirmationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $fromEmail = 'noreply@auticare.fr',
        private readonly string $fromName = 'AutiCare',
    ) {
    }

    private function ensureToken(RendezVous $rdv): void
    {
        if ($rdv->getTokenAnnulation() !== null && $rdv->getTokenAnnulation() !== '') {
            return;
        }
        $rdv->setTokenAnnulation(bin2hex(random_bytes(32)));
        $this->entityManager->flush();
    }

    /**
     * Envoie l'email "Demande de rendez-vous enregistrée" au patient.
     */
    public function sendDemandeEnregistree(RendezVous $rdv): bool
    {
        $to = $this->getPatientEmail($rdv);
        if ($to === null || $to === '') {
            $this->logger->warning('Email "demande enregistrée" non envoyé : aucune adresse email pour le RDV', ['rdv_id' => $rdv->getId()]);
            return false;
        }

        try {
            $this->ensureToken($rdv);
            $medecin = $rdv->getMedecin();
            $dispo = $rdv->getDisponibilite();
            $dateRdv = $rdv->getDateRdv();
            $medecinNom = $medecin ? trim(($medecin->getPrenom() ?? '') . ' ' . ($medecin->getNom() ?? '')) : 'Votre praticien';
            $dateLabel = $dateRdv ? $dateRdv->format('d/m/Y') : '—';
            $heureDebut = $dispo && $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H:i') : '—';
            $heureFin = $dispo && $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H:i') : '—';

            $token = $rdv->getTokenAnnulation();
            $pageRdvUrl = $token !== null ? $this->urlGenerator->generate('rdv_patient_page', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL) : '';

            $html = $this->twig->render('email/rendezvous_demande_enregistree.html.twig', [
                'patient_prenom' => $rdv->getPrenom() ?? '',
                'medecin_nom' => $medecinNom,
                'date_rdv' => $dateLabel,
                'heure_debut' => $heureDebut,
                'heure_fin' => $heureFin,
                'page_rdv_url' => $pageRdvUrl,
            ]);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject('Demande de rendez-vous enregistrée - AutiCare')
                ->html($html);
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur envoi email confirmation demande RDV: ' . $e->getMessage(), [
                'rdv_id' => $rdv->getId(),
                'to' => $to,
            ]);
            return false;
        }
    }

    /**
     * Envoie l'email "Votre rendez-vous est confirmé" au patient.
     */
    public function sendConfirme(RendezVous $rdv): bool
    {
        $to = $this->getPatientEmail($rdv);
        if ($to === null || $to === '') {
            $this->logger->warning('Email "rendez-vous confirmé" non envoyé : aucune adresse email pour le RDV', ['rdv_id' => $rdv->getId()]);
            return false;
        }

        try {
            $this->ensureToken($rdv);
            $medecin = $rdv->getMedecin();
            $dispo = $rdv->getDisponibilite();
            $dateRdv = $rdv->getDateRdv();
            $medecinNom = $medecin ? trim(($medecin->getPrenom() ?? '') . ' ' . ($medecin->getNom() ?? '')) : 'Votre praticien';
            $cabinet = $medecin ? ($medecin->getAdresseCabinet() ?? '') : '';
            $telephone = $medecin ? ($medecin->getTelephoneCabinet() ?? $medecin->getTelephone() ?? '') : '';
            $dateLabel = $dateRdv ? $dateRdv->format('d/m/Y') : '—';
            $heureDebut = $dispo && $dispo->getHeureDebut() ? $dispo->getHeureDebut()->format('H:i') : '—';
            $heureFin = $dispo && $dispo->getHeureFin() ? $dispo->getHeureFin()->format('H:i') : '—';

            $addToCalendarUrl = '';
            if ($this->googleCalendarService) {
                try {
                    $addToCalendarUrl = $this->googleCalendarService->getAddToCalendarUrl($rdv);
                } catch (\Throwable $e) {
                    $this->logger->warning('URL calendrier non générée pour email confirmé: ' . $e->getMessage());
                }
            }

            $token = $rdv->getTokenAnnulation();
            $pageRdvUrl = $token !== null ? $this->urlGenerator->generate('rdv_patient_page', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL) : '';

            $html = $this->twig->render('email/rendezvous_confirme.html.twig', [
                'patient_prenom' => $rdv->getPrenom() ?? '',
                'medecin_nom' => $medecinNom,
                'cabinet' => $cabinet,
                'telephone' => $telephone,
                'date_rdv' => $dateLabel,
                'heure_debut' => $heureDebut,
                'heure_fin' => $heureFin,
                'add_to_calendar_url' => $addToCalendarUrl,
                'page_rdv_url' => $pageRdvUrl,
            ]);

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject('Votre rendez-vous est confirmé - AutiCare')
                ->html($html);
            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur envoi email confirmation RDV accepté: ' . $e->getMessage(), [
                'rdv_id' => $rdv->getId(),
                'to' => $to,
            ]);
            return false;
        }
    }

    private function getPatientEmail(RendezVous $rdv): ?string
    {
        $patient = $rdv->getPatient();
        if ($patient !== null) {
            $email = $patient->getEmail();
            if ($email !== null && $email !== '') {
                return $email;
            }
        }
        $email = $rdv->getEmail();
        return $email !== null && $email !== '' ? $email : null;
    }
}
