<?php

declare(strict_types=1);

namespace App\Service;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    public function __construct(
        private readonly ?string $secretKey = null,
    ) {
        if ($this->secretKey !== null && $this->secretKey !== '') {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== null
            && $this->secretKey !== ''
            && !str_contains($this->secretKey, 'VOTRE');
    }

    /**
     * Crée et confirme un PaymentIntent pour le paiement par carte.
     *
     * @param int $amountCents Montant en centimes (ex: 5000 = 50.00 TND)
     * @param string $paymentMethodId ID du PaymentMethod Stripe (créé côté client)
     * @return array{success: bool, paymentIntentId?: string, error?: string}
     */
    public function createAndCapturePaymentIntent(int $amountCents, string $paymentMethodId): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Stripe non configuré.'];
        }

        try {
            $intent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => 'eur', // TND non supporté par Stripe; utilisez EUR ou configurez la conversion
                'payment_method' => $paymentMethodId,
                'confirm' => true,
                'automatic_payment_methods' => ['enabled' => true],
                'return_url' => '', // Non utilisé pour confirm: true
            ]);

            if ($intent->status === 'succeeded' || $intent->status === 'requires_capture') {
                return ['success' => true, 'paymentIntentId' => $intent->id];
            }

            return ['success' => false, 'error' => 'Paiement non abouti (statut : ' . $intent->status . ')'];
        } catch (ApiErrorException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
