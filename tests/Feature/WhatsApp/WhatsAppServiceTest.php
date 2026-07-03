<?php

use App\Exceptions\WhatsAppWindowExpiredException;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    IntegrationSetting::current()->update([
        'meta_whatsapp_token'    => 'test-token',
        'meta_whatsapp_phone_id' => '1234567890',
    ]);
});

it('sends text within 24h window', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

    $result = app(WhatsAppService::class)->sendTextWithinWindow(
        '+393401234567',
        'Ciao!',
        Carbon::now()->subHours(2),
        app('current_business_id'),
    );

    expect($result)->toBeTrue();
    Http::assertSentCount(1);
});

it('throws WhatsAppWindowExpiredException when outside 24h window', function () {
    expect(fn () => app(WhatsAppService::class)->sendTextWithinWindow(
        '+393401234567',
        'Ciao!',
        Carbon::now()->subHours(25),
        app('current_business_id'),
    ))->toThrow(WhatsAppWindowExpiredException::class);
});

it('sends template and returns wamid', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.2']]], 200)]);

    $result = app(WhatsAppService::class)->sendTemplate(
        '+393401234567',
        'appointment_confirmation',
        'it',
        'UTILITY',
        ['Mario Rossi', 'domani', '15:00'],
        app('current_business_id'),
    );

    expect($result)->toBe('wamid.2');
});

it('sendTemplate returns null on api error', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);

    $result = app(WhatsAppService::class)->sendTemplate(
        '+393401234567',
        'appointment_confirmation',
        'it',
        'UTILITY',
        ['Mario Rossi'],
        app('current_business_id'),
    );

    expect($result)->toBeNull();
});
