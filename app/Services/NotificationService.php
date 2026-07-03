<?php

namespace App\Services;

use Twilio\Rest\Api\V2010\Account\MessageList;

class NotificationService
{
    public function __construct(private readonly ?MessageList $messages) {}

    public function sendSms(string $to, string $message): void
    {
        $this->messages?->create($to, [
            'from' => \App\Models\IntegrationSetting::getTwilioFrom() ?? config('services.twilio.from'),
            'body' => $message,
        ]);
    }

    public function sendWhatsApp(string $to, string $message): void
    {
        $this->messages?->create('whatsapp:' . $to, [
            'from' => 'whatsapp:' . (\App\Models\IntegrationSetting::getTwilioFrom() ?? config('services.twilio.from')),
            'body' => $message,
        ]);
    }
}
