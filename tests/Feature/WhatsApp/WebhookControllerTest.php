<?php
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageStatus;
use App\Jobs\ProcessWhatsAppMessageJob;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    IntegrationSetting::current()->update([
        'meta_whatsapp_phone_id' => '111222333',
        'meta_whatsapp_token'    => 'test-token',
        'whatsapp_ai_enabled'    => true,
    ]);
    config(['services.whatsapp.webhook_verify_token' => 'my-verify-token']);
});

function makeWebhookPayload(string $phoneNumberId, string $wamid, string $from, string $text): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry'  => [[
            'changes' => [[
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['phone_number_id' => $phoneNumberId],
                    'contacts' => [['profile' => ['name' => 'Test User'], 'wa_id' => $from]],
                    'messages' => [[
                        'from' => $from,
                        'id'   => $wamid,
                        'type' => 'text',
                        'text' => ['body' => $text],
                        'timestamp' => '1750000000',
                    ]],
                ],
                'field' => 'messages',
            ]],
        ]],
    ];
}

it('responds to Meta GET challenge', function () {
    $response = $this->get('/whatsapp/webhook?' . http_build_query([
        'hub.mode'         => 'subscribe',
        'hub.verify_token' => 'my-verify-token',
        'hub.challenge'    => 'CHALLENGE_123',
    ]));

    $response->assertStatus(200);
    $response->assertSee('CHALLENGE_123');
});

it('rejects GET challenge with wrong token', function () {
    $response = $this->get('/whatsapp/webhook?' . http_build_query([
        'hub.mode'         => 'subscribe',
        'hub.verify_token' => 'wrong-token',
        'hub.challenge'    => 'CHALLENGE_123',
    ]));

    $response->assertStatus(403);
});

it('rejects POST with invalid signature', function () {
    $payload = makeWebhookPayload('111222333', 'wamid.abc', '393401234567', 'Test message');
    config(['services.whatsapp.app_secret' => 'test-app-secret']);

    $response = $this->postJson('/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => 'sha256=invalidsignature']);

    $response->assertStatus(403);
});

it('rejects POST with missing signature header', function () {
    $payload = makeWebhookPayload('111222333', 'wamid.abc', '393401234567', 'Test message');
    config(['services.whatsapp.app_secret' => 'test-app-secret']);

    $response = $this->postJson('/whatsapp/webhook', $payload);

    $response->assertStatus(403);
});

it('saves inbound message and dispatches job', function () {
    $payload = makeWebhookPayload('111222333', 'wamid.abc', '393401234567', 'Voglio prenotare');
    $body    = json_encode($payload);
    $secret  = 'test-app-secret';
    config(['services.whatsapp.app_secret' => $secret]);
    $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

    $response = $this->postJson('/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => $sig]);

    $response->assertStatus(200);
    expect(WhatsAppMessage::where('wamid', 'wamid.abc')->exists())->toBeTrue();
    Queue::assertPushed(ProcessWhatsAppMessageJob::class);
});

it('deduplicates: does not dispatch job for known wamid', function () {
    WhatsAppMessage::create([
        'business_id'     => app('current_business_id'),
        'wamid'           => 'wamid.dup',
        'phone'           => '+393401234567',
        'phone_normalized'=> '+393401234567',
        'direction'       => 'inbound',
        'type'            => 'text',
        'payload'         => [],
    ]);

    $payload = makeWebhookPayload('111222333', 'wamid.dup', '393401234567', 'Ciao');
    $body    = json_encode($payload);
    $secret  = 'test-app-secret';
    config(['services.whatsapp.app_secret' => $secret]);
    $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

    $response = $this->postJson('/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => $sig]);

    $response->assertStatus(200);
    Queue::assertNotPushed(ProcessWhatsAppMessageJob::class);
});

it('returns 200 and skips job when phone_number_id is unknown', function () {
    $payload = makeWebhookPayload('UNKNOWN_ID', 'wamid.xyz', '393401234567', 'Ciao');
    $body    = json_encode($payload);
    $secret  = 'test-app-secret';
    config(['services.whatsapp.app_secret' => $secret]);
    $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

    $response = $this->postJson('/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => $sig]);

    $response->assertStatus(200);
    Queue::assertNotPushed(ProcessWhatsAppMessageJob::class);
});

it('saves message but skips job when whatsapp_ai_enabled is false', function () {
    IntegrationSetting::current()->update(['whatsapp_ai_enabled' => false]);

    $payload = makeWebhookPayload('111222333', 'wamid.noai', '393401234567', 'Ciao');
    $body    = json_encode($payload);
    $secret  = 'test-app-secret';
    config(['services.whatsapp.app_secret' => $secret]);
    $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

    $response = $this->postJson('/whatsapp/webhook', $payload, ['X-Hub-Signature-256' => $sig]);

    $response->assertStatus(200);
    expect(WhatsAppMessage::where('wamid', 'wamid.noai')->exists())->toBeTrue();
    Queue::assertNotPushed(ProcessWhatsAppMessageJob::class);
});

it('status webhook links to parent message and flips sent notification to failed', function () {
    $parent = WhatsAppMessage::create([
        'business_id'      => app('current_business_id'),
        'wamid'            => 'wamid.notify1',
        'phone'            => '+393401234567',
        'phone_normalized' => '+393401234567',
        'direction'        => 'outbound',
        'type'             => 'template',
        'template_name'    => 'appointment_reminder',
        'status'           => 'sent',
        'payload'          => [],
    ]);

    $statusPayload = [
        'object' => 'whatsapp_business_account',
        'entry'  => [[
            'changes' => [[
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata'          => ['phone_number_id' => '111222333'],
                    'statuses'          => [[
                        'id'        => 'wamid.notify1',
                        'status'    => 'failed',
                        'timestamp' => (string) now()->timestamp,
                        'errors'    => [['title' => 'Message undeliverable']],
                    ]],
                ],
                'field' => 'messages',
            ]],
        ]],
    ];

    $body   = json_encode($statusPayload);
    $secret = 'test-app-secret';
    config(['services.whatsapp.app_secret' => $secret]);
    $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);

    $response = $this->postJson('/whatsapp/webhook', $statusPayload, ['X-Hub-Signature-256' => $sig]);

    $response->assertStatus(200);

    $status = WhatsAppMessageStatus::where('provider_message_id', 'wamid.notify1')->first();
    expect($status)->not->toBeNull();
    expect($status->whatsapp_message_id)->toBe($parent->id);

    $parent->refresh();
    expect($parent->status)->toBe('failed');
    expect($parent->failed_at)->not->toBeNull();
    expect($parent->error_message)->toBe('Message undeliverable');
});
