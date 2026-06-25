<?php

use App\Models\StripeConnectAccount;
use App\Models\StripeWebhookEvent;
use App\Services\StripeConnectService;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

beforeEach(function () {
    Config::set('services.stripe.connect_webhook_secret', null);
});

function makeConnectWebhookPayload(string $eventId, string $accountId, array $accountData = []): array
{
    return [
        'id'      => $eventId,
        'type'    => 'account.updated',
        'account' => $accountId,
        'data'    => [
            'object' => array_merge([
                'id'               => $accountId,
                'object'           => 'account',
                'charges_enabled'  => true,
                'payouts_enabled'  => true,
                'details_submitted' => true,
                'requirements'     => ['currently_due' => [], 'past_due' => [], 'disabled_reason' => null],
                'capabilities'     => [],
                'default_currency' => 'eur',
                'country'          => 'IT',
            ], $accountData),
        ],
    ];
}

it('elabora account.updated e aggiorna StripeConnectAccount', function () {
    $account = StripeConnectAccount::factory()->pending()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_webhook_test',
    ]);

    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->once();
    });

    $payload = makeConnectWebhookPayload('evt_001', 'acct_webhook_test');

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload, [
            'Stripe-Signature' => 'bypass',
        ]);

    $response->assertOk();
    expect(StripeWebhookEvent::where('event_id', 'evt_001')->exists())->toBeTrue();
});

it('risponde 200 senza rielaborare un evento già processato (idempotenza)', function () {
    StripeWebhookEvent::create([
        'event_id'     => 'evt_duplicate',
        'type'         => 'account.updated',
        'payload'      => [],
        'processed_at' => now(),
    ]);

    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->never();
    });

    $payload = makeConnectWebhookPayload('evt_duplicate', 'acct_any');

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload);

    $response->assertOk();
});

it('ignora silenziosamente eventi di account non trovati nel DB', function () {
    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->never();
    });

    $payload = makeConnectWebhookPayload('evt_unknown', 'acct_nonexistent');
    $response = $this->withoutMiddleware()->postJson('/stripe/connect/webhook', $payload);

    $response->assertOk();
});
