<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoi des SMS de rappel via Email-to-SMS (e-mail vers gateway opérateur → SMS).
 * Gateways configurés par code pays dans REMINDER_SMS_GATEWAYS (JSON).
 */
final class ReminderSmsSender
{
    /** @var array<string, string> code pays (ex. 33, 216) => domaine gateway (ex. orange.fr) */
    private array $gateways = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $fromEmail,
        private readonly string $fromName,
        ?string $gatewaysJson = '{}',
    ) {
        $json = $gatewaysJson ?? '{}';
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (\is_array($decoded)) {
                $this->gateways = $decoded;
            }
        }
    }

    /**
     * Envoie le rappel en SMS via Email-to-SMS (numéro@gateway).
     *
     * @param string $phone E.164 (ex. +33612345678 ou +21691234567)
     */
    public function send(string $phone, string $message): void
    {
        $this->sendViaEmailToSms($phone, $message);
    }

    /**
     * Indique si un gateway est configuré pour le pays du numéro.
     */
    public function hasGatewayFor(string $phoneE164): bool
    {
        $digits = preg_replace('/\D/', '', $phoneE164);
        if ($digits === '') {
            return false;
        }
        $countryCode = $this->extractCountryCode($digits);
        return isset($this->gateways[$countryCode]) && $this->gateways[$countryCode] !== '';
    }

    private function sendViaEmailToSms(string $phoneE164, string $message): void
    {
        $digits = preg_replace('/\D/', '', $phoneE164);
        if ($digits === '') {
            throw new \InvalidArgumentException('Numéro de téléphone invalide.');
        }
        $countryCode = $this->extractCountryCode($digits);
        $gateway = $this->gateways[$countryCode] ?? null;
        if ($gateway === null || $gateway === '') {
            throw new \RuntimeException(sprintf('Aucun gateway Email-to-SMS configuré pour le pays %s.', $countryCode));
        }
        $localNumber = $this->toLocalNumber($digits, $countryCode);
        $toEmail = $localNumber . '@' . $gateway;

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($toEmail)
            ->subject('Rappel AutiCare')
            ->text($message);
        $this->mailer->send($email);
    }

    private function extractCountryCode(string $digits): string
    {
        if (str_starts_with($digits, '33') && \strlen($digits) >= 11) {
            return '33';
        }
        if (str_starts_with($digits, '216') && \strlen($digits) >= 11) {
            return '216';
        }
        if (str_starts_with($digits, '33')) {
            return '33';
        }
        if (str_starts_with($digits, '216')) {
            return '216';
        }
        return '';
    }

    private function toLocalNumber(string $digits, string $countryCode): string
    {
        if ($countryCode === '33') {
            $national = substr($digits, 2);
            return '0' . $national;
        }
        if ($countryCode === '216') {
            return substr($digits, 3);
        }
        return $digits;
    }
}
