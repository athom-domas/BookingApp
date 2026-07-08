<?php

use App\Jobs\ProcessWhatsAppMessageJob;
use App\Models\Business;
use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Queue;

function makeWhatsAppWebhookPayload(string $phoneNumberId, string $from, string $text): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry'  => [[
            'changes' => [[
                'value' => [
                    'metadata'  => ['phone_number_id' => $phoneNumberId],
                    'messages'  => [[
                        'id'   => 'wamid_' . uniqid(),
                        'from' => ltrim($from, '+'),
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                    'contacts' => [[
                        'wa_id'   => ltrim($from, '+'),
                        'profile' => ['name' => 'Test User'],
                    ]],
                ],
            ]],
        ]],
    ];
}

beforeEach(function () {
    Queue::fake();
    config(['services.whatsapp.app_secret' => null]);
});

it('does not dispatch AI job for base-plan business', function () {
    $business = Business::factory()->create(['trial_ends_at' => null]);
    IntegrationSetting::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $business->id],
        [
            'meta_whatsapp_phone_id'         => 'phone_test_base',
            'whatsapp_ai_enabled'            => true,
            'whatsapp_notifications_enabled' => false,
        ]
    );

    $this->postJson('/whatsapp/webhook', makeWhatsAppWebhookPayload('phone_test_base', '+39123456789', 'Ciao'))
         ->assertStatus(200);

    Queue::assertNotPushed(ProcessWhatsAppMessageJob::class);
});

it('dispatches AI job for trial business', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);
    IntegrationSetting::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $business->id],
        [
            'meta_whatsapp_phone_id' => 'phone_test_trial',
            'whatsapp_ai_enabled'    => true,
        ]
    );

    $this->postJson('/whatsapp/webhook', makeWhatsAppWebhookPayload('phone_test_trial', '+39123456789', 'Ciao'))
         ->assertStatus(200);

    Queue::assertPushed(ProcessWhatsAppMessageJob::class);
});

it('does not dispatch AI job when ai_enabled is false even for plus plan', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);
    IntegrationSetting::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $business->id],
        [
            'meta_whatsapp_phone_id' => 'phone_test_disabled',
            'whatsapp_ai_enabled'    => false,
        ]
    );

    $this->postJson('/whatsapp/webhook', makeWhatsAppWebhookPayload('phone_test_disabled', '+39123456789', 'Ciao'))
         ->assertStatus(200);

    Queue::assertNotPushed(ProcessWhatsAppMessageJob::class);
});
