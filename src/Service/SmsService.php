<?php

namespace App\Service;

use Twilio\Rest\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SmsService
{
    private Client $twilioClient;
    private string $fromPhoneNumber;

    public function __construct(
        #[Autowire('%env(TWILIO_ACCOUNT_SID)%')] string $accountSid,
        #[Autowire('%env(TWILIO_AUTH_TOKEN)%')] string $authToken,
        #[Autowire('%env(TWILIO_PHONE_NUMBER)%')] string $fromPhoneNumber
    ) {
        $this->twilioClient = new Client($accountSid, $authToken);
        $this->fromPhoneNumber = $fromPhoneNumber;
    }

    public function sendSms(string $to, string $message): bool
    {
        try {
            error_log("Tentative d'envoi SMS vers: " . $to);
            error_log("Message: " . $message);
            
            $message = $this->twilioClient->messages->create(
                $to,
                [
                    'from' => $this->fromPhoneNumber,
                    'body' => $message
                ]
            );
            
            error_log("SMS envoyé avec succès. SID: " . $message->sid);
            error_log("Status: " . $message->status);
            
            return true;
        } catch (\Exception $e) {
            error_log('Erreur envoi SMS: ' . $e->getMessage());
            error_log('Code erreur: ' . $e->getCode());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    public function generateVerificationCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function getAccountInfo(): array
    {
        try {
            $account = $this->twilioClient->api->v2010->account->fetch();
            return [
                'sid' => $account->sid,
                'friendlyName' => $account->friendlyName,
                'status' => $account->status,
                'dateCreated' => $account->dateCreated->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            error_log('Erreur compte Twilio: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
