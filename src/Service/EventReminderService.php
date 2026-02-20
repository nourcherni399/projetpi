<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use App\Repository\InscritEventsRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Envoie les rappels (e-mail + SMS) aux inscrits d'un événement.
 * Utilisé par le cron et par le bouton "Envoyer les rappels" en admin.
 */
final class EventReminderService
{
    private const CACHE_PREFIX = 'event_reminder_';
    private const CACHE_TTL = 86400;
    private const FROM_EMAIL = 'amarahedil8@gmail.com';
    private const FROM_NAME = 'AutiCare';

    public function __construct(
        private readonly InscritEventsRepository $inscritEventsRepository,
        private readonly MailerInterface $mailer,
        private readonly ReminderSmsSender $reminderSmsSender,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Envoie e-mail + SMS (si gateway configuré) à tous les inscrits à rappeler.
     *
     * @param bool $useCache si false (bouton admin), on envoie même si déjà envoyé récemment
     * @return array{sentEmail: int, sentSms: int, errors: list<string>}
     */
    public function sendRemindersForEvent(Evenement $event, bool $useCache = true): array
    {
        $inscrits = $this->inscritEventsRepository->findInscritsToRemindForEvent($event);
        $sentEmail = 0;
        $sentSms = 0;
        $errors = [];

        foreach ($inscrits as $inscrit) {
            $user = $inscrit->getUser();
            if ($user === null) {
                continue;
            }
            $email = trim((string) $user->getEmail());
            if ($email === '') {
                continue;
            }
            $cacheKey = self::CACHE_PREFIX . $event->getId() . '_' . $user->getId();
            if ($useCache && $this->cache->has($cacheKey)) {
                continue;
            }
            try {
                $this->sendReminderEmail($event, $user->getPrenom() ?? 'Participant', $email);
                $sentEmail++;
                $phone = $this->normalizePhone($user->getTelephone());
                if ($phone !== '' && $this->reminderSmsSender->hasGatewayFor($phone)) {
                    try {
                        $this->sendReminderSms($event, $phone);
                        $sentSms++;
                    } catch (\Throwable $eSms) {
                        $this->logger->warning('Envoi rappel SMS échoué', [
                            'event_id' => $event->getId(),
                            'user_id' => $user->getId(),
                            'exception' => $eSms->getMessage(),
                        ]);
                        $errors[] = sprintf('SMS user %d: %s', $user->getId(), $eSms->getMessage());
                    }
                }
                if ($useCache) {
                    $this->cache->set($cacheKey, '1', self::CACHE_TTL);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Envoi rappel événement échoué', [
                    'event_id' => $event->getId(),
                    'user_id' => $user->getId(),
                    'exception' => $e->getMessage(),
                ]);
                $errors[] = sprintf('User %d: %s', $user->getId(), $e->getMessage());
            }
        }

        return ['sentEmail' => $sentEmail, 'sentSms' => $sentSms, 'errors' => $errors];
    }

    private function normalizePhone(mixed $telephone): string
    {
        if ($telephone === null) {
            return '';
        }
        $digits = preg_replace('/\D/', '', (string) $telephone);
        if ($digits === '' || strlen($digits) < 8) {
            return '';
        }
        if (strlen($digits) === 8 && $digits[0] >= '2' && $digits[0] <= '9') {
            return '+216' . $digits;
        }
        if (strlen($digits) >= 9 && substr($digits, 0, 3) === '216') {
            return '+' . $digits;
        }
        if (strlen($digits) >= 9) {
            return '+' . $digits;
        }
        return '+216' . $digits;
    }

    private function sendReminderSms(Evenement $event, string $phone): void
    {
        $title = $event->getTitle() ?? 'Événement';
        $dateStr = $event->getDateEvent() ? $event->getDateEvent()->format('d/m/Y') : '';
        $heureDebut = $event->getHeureDebut() ? $event->getHeureDebut()->format('H:i') : '';
        $lieu = $event->getLieu() ?? '';
        $message = sprintf(
            "AutiCare - Rappel : %s. Date : %s %s. Lieu : %s. À bientôt !",
            $title,
            $dateStr,
            $heureDebut,
            $lieu !== '' ? $lieu : 'voir le site'
        );
        $this->reminderSmsSender->send($phone, $message);
    }

    private function sendReminderEmail(Evenement $event, string $prenom, string $toEmail): void
    {
        $eventUrl = $this->urlGenerator->generate('user_event_show', ['id' => $event->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $dateStr = $event->getDateEvent() ? $event->getDateEvent()->format('d/m/Y') : '—';
        $heureDebut = $event->getHeureDebut() ? $event->getHeureDebut()->format('H:i') : '—';
        $heureFin = $event->getHeureFin() ? $event->getHeureFin()->format('H:i') : '';
        $lieu = $event->getLieu() ?? '—';
        $title = $event->getTitle() ?? 'Événement';

        $html = $this->buildReminderHtml($prenom, $title, $dateStr, $heureDebut, $heureFin, $lieu, $eventUrl);

        $email = (new Email())
            ->from(new Address(self::FROM_EMAIL, self::FROM_NAME))
            ->to($toEmail)
            ->subject('Rappel : ' . $title . ' a lieu bientôt - AutiCare')
            ->html($html);
        $this->mailer->send($email);
    }

    private function buildReminderHtml(string $prenom, string $title, string $dateStr, string $heureDebut, string $heureFin, string $lieu, string $eventUrl): string
    {
        $prenom = htmlspecialchars($prenom, \ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($title, \ENT_QUOTES, 'UTF-8');
        $dateStr = htmlspecialchars($dateStr, \ENT_QUOTES, 'UTF-8');
        $heureDebut = htmlspecialchars($heureDebut, \ENT_QUOTES, 'UTF-8');
        $heureFin = htmlspecialchars($heureFin, \ENT_QUOTES, 'UTF-8');
        $lieu = htmlspecialchars($lieu, \ENT_QUOTES, 'UTF-8');
        $eventUrl = htmlspecialchars($eventUrl, \ENT_QUOTES, 'UTF-8');

        $heureLine = $heureFin !== '' ? "{$heureDebut} – {$heureFin}" : $heureDebut;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:system-ui,-apple-system,sans-serif;background:#f5f1eb;padding:24px;color:#374151;margin:0;} .box{max-width:480px;margin:0 auto;background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,0.06);} .title-email{font-size:1.125rem;color:#1f2937;font-weight:600;margin:0 0 20px;} p{line-height:1.65;margin:0 0 14px;color:#4b5563;} .info{background:#f8f6f2;border-radius:12px;padding:18px 20px;margin:22px 0;border-left:4px solid #A7C7E7;} .info p{margin:0;} .info strong{color:#374151;} .btn{display:inline-block;margin:20px 0 8px;padding:14px 28px;background:#5b8fd6;color:#fff!important;text-decoration:none;border-radius:10px;font-weight:600;font-size:0.9375rem;} .btn:hover{background:#4a7bc4;} .footer{font-size:13px;color:#6b7280;margin-top:28px;padding-top:20px;border-top:1px solid #e5e7eb;} .intro{color:#6b7280;font-size:0.9375rem;}</style></head>
        <body>
        <div class="box">
        <p class="title-email">Bonjour {$prenom},</p>
        <p>Votre événement <strong>{$title}</strong> a lieu bientôt. Nous vous envoyons ce rappel pour que vous ayez toutes les informations sous la main.</p>
        <p class="intro">Détails de l'événement :</p>
        <div class="info">
        <p><strong>Date :</strong> {$dateStr}<br><strong>Heure :</strong> {$heureLine}<br><strong>Lieu :</strong> {$lieu}</p>
        </div>
        <p><a href="{$eventUrl}" class="btn">Voir l'événement</a></p>
        <p class="footer">À bientôt,<br><strong>L'équipe AutiCare</strong></p>
        </div>
        </body>
        </html>
        HTML;
    }
}
