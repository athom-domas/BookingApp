<?php

use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppConversationService;
use App\Services\WhatsAppConversationState;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);

    IntegrationSetting::current()->update([
        'meta_whatsapp_token'    => 'fake-token',
        'meta_whatsapp_phone_id' => '1234',
        'whatsapp_ai_enabled'    => true,
    ]);

    config(['services.anthropic.key' => 'fake-key']);
});

function makeInboundMessage(int $businessId, string $text = 'Voglio prenotare'): WhatsAppMessage
{
    return WhatsAppMessage::create([
        'business_id'      => $businessId,
        'wamid'            => 'wamid.' . uniqid(),
        'phone'            => '+393401234567',
        'phone_normalized' => '+393401234567',
        'wa_id'            => '393401234567',
        'direction'        => 'inbound',
        'type'             => 'text',
        'payload'          => ['text' => ['body' => $text], 'timestamp' => (string) now()->timestamp],
    ]);
}

it('processes a simple text reply from Claude', function () {
    $businessId = app('current_business_id');
    $message    = makeInboundMessage($businessId);

    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200),
        'https://api.anthropic.com/*'  => Http::response([
            'content'     => [['type' => 'text', 'text' => 'Ciao! Come posso aiutarti?']],
            'stop_reason' => 'end_turn',
        ], 200),
    ]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $message->refresh();
    expect($message->processed_at)->not->toBeNull();

    Http::assertSentCount(2); // one to Anthropic, one to Meta
});

it('marks message as failed on Claude API error', function () {
    $businessId = app('current_business_id');
    $message    = makeInboundMessage($businessId);

    Http::fake([
        'https://api.anthropic.com/*' => Http::response(['error' => 'Internal'], 500),
    ]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    $message->refresh();
    expect($message->failed_at)->not->toBeNull();
    expect($message->error_code)->toBe('CLAUDE_ERROR');
});

it('sends acknowledgement when escalated', function () {
    $businessId = app('current_business_id');
    $message    = makeInboundMessage($businessId, 'ancora non ho capito');

    $stateService = app(WhatsAppConversationState::class);
    $state = $stateService->get($businessId, '+393401234567');
    $state['escalated'] = true;
    $stateService->set($businessId, '+393401234567', $state);

    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out']]], 200)]);

    app(WhatsAppConversationService::class)->handle($message->id, $businessId);

    Http::assertSentCount(1); // one request to Meta for the acknowledgement
});
