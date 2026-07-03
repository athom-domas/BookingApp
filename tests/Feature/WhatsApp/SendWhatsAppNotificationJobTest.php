<?php

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app()->forgetInstance('current_business_id');
});

function makeQueuedNotification(Business $business, array $overrides = []): WhatsAppMessage
{
    return WhatsAppMessage::create(array_merge([
        'business_id'      => $business->id,
        'phone'            => '+393331234567',
        'phone_normalized' => '+393331234567',
        'direction'        => 'outbound',
        'type'             => 'template',
        'template_name'    => 'appointment_confirmed',
        'payload'          => ['parameters' => ['Mario']],
        'status'           => 'queued',
    ], $overrides));
}

function makeNotifJobSettings(Business $business): IntegrationSetting
{
    return IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        [
            'whatsapp_notifications_enabled' => true,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => '1234567890',
            'whatsapp_monthly_sent'          => 5,
        ],
    );
}

it('marks message as sent, stores wamid and increments counter on success', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ok1']]], 200)]);

    $business = Business::factory()->create();
    $settings = makeNotifJobSettings($business);
    $message  = makeQueuedNotification($business);

    (new SendWhatsAppNotificationJob($message->id))->handle(app(\App\Services\WhatsAppService::class));

    $message->refresh();
    expect($message->status)->toBe('sent');
    expect($message->wamid)->toBe('wamid.ok1');
    expect($message->sent_at)->not->toBeNull();
    expect($settings->fresh()->whatsapp_monthly_sent)->toBe(6);
});

it('marks message as failed on api error without incrementing counter', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);

    $business = Business::factory()->create();
    $settings = makeNotifJobSettings($business);
    $message  = makeQueuedNotification($business);

    (new SendWhatsAppNotificationJob($message->id))->handle(app(\App\Services\WhatsAppService::class));

    $message->refresh();
    expect($message->status)->toBe('failed');
    expect($message->failed_at)->not->toBeNull();
    expect($message->error_message)->not->toBeNull();
    expect($settings->fresh()->whatsapp_monthly_sent)->toBe(5);
});

it('skips message that is not queued', function () {
    Http::fake();

    $business = Business::factory()->create();
    makeNotifJobSettings($business);
    $message = makeQueuedNotification($business, ['status' => 'sent']);

    (new SendWhatsAppNotificationJob($message->id))->handle(app(\App\Services\WhatsAppService::class));

    Http::assertNothingSent();
});

it('failed hook marks message as failed', function () {
    $business = Business::factory()->create();
    $message  = makeQueuedNotification($business);

    (new SendWhatsAppNotificationJob($message->id))->failed(new \Exception('boom'));

    $message->refresh();
    expect($message->status)->toBe('failed');
    expect($message->error_message)->toBe('boom');
});
