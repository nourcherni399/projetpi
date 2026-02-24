<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Indique si l'envoi de SMS (Twilio) est activé dans l'application.
 * Utile pour afficher un indicateur dans l'admin ou conditionner l'affichage des options de rappel SMS.
 */
final class SmsStatusChecker
{
    public function isSmsEnabled(): bool
    {
        $dsn = $_ENV['TWILIO_DSN'] ?? getenv('TWILIO_DSN') ?: '';

        return $dsn !== '' && str_starts_with($dsn, 'twilio://');
    }
}
