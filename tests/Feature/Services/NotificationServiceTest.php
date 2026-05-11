<?php

use App\Services\NotificationService;
use Twilio\Rest\Api\V2010\Account\MessageList;

it('sendSms sends a message via Twilio', function () {
    $mockMessages = Mockery::mock(MessageList::class);
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('+39123456789', Mockery::on(fn ($opts) =>
            $opts['from'] === config('services.twilio.from') &&
            str_contains($opts['body'], 'test message')
        ));

    $service = new NotificationService($mockMessages);
    $service->sendSms('+39123456789', 'test message');
});

it('sendWhatsApp sends a whatsapp message via Twilio', function () {
    $mockMessages = Mockery::mock(MessageList::class);
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('whatsapp:+39123456789', Mockery::on(fn ($opts) =>
            str_starts_with($opts['from'], 'whatsapp:') &&
            str_contains($opts['body'], 'test whatsapp')
        ));

    $service = new NotificationService($mockMessages);
    $service->sendWhatsApp('+39123456789', 'test whatsapp');
});
