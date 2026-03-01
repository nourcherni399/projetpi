<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoi d'e-mails via l'API Brevo (ex-Sendinblue).
 * Configurez BREVO_API_KEY et BREVO_FROM_EMAIL dans .env.
 */
final class EmailApiService
{
    private const BREVO_URL = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
        private readonly ?string $fromEmail,
        private readonly string $fromName = 'AutiCare',
    ) {
    }

    /**
     * Envoie un e-mail via l'API Brevo.
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ExceptionInterface
     */
    public function send(
        string $toEmail,
        string $subject,
        string $htmlContent,
        ?string $fromEmail = null,
        ?string $fromName = null,
    ): void {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('EmailApiService (Brevo) n\'est pas configuré : définissez BREVO_API_KEY et BREVO_FROM_EMAIL.');
        }
        $senderEmail = $fromEmail ?? $this->fromEmail;
        $senderName = $fromName ?? $this->fromName;

        $response = $this->httpClient->request('POST', self::BREVO_URL, [
            'headers' => [
                'accept' => 'application/json',
                'api-key' => $this->apiKey,
                'content-type' => 'application/json',
            ],
            'json' => [
                'sender' => [
                    'name' => $senderName,
                    'email' => $senderEmail,
                ],
                'to' => [
                    ['email' => $toEmail],
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $body = $response->toArray(false);
            throw new \RuntimeException(
                sprintf('Brevo API error %d: %s', $status, $body['message'] ?? $response->getContent(false))
            );
        }
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '' && $this->fromEmail !== null && $this->fromEmail !== '';
    }
}