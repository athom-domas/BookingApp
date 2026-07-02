<?php

use App\Models\Appointment;
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

it('instrada payment_intent.succeeded al PaymentService con accountId', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'stripe_transaction_id' => 'pi_connect_evt',
        'stripe_account_id'     => 'acct_connect_evt',
        'status'                => 'pending',
    ]);

    $payload = [
        'id'      => 'evt_pi_connect',
        'type'    => 'payment_intent.succeeded',
        'account' => 'acct_connect_evt',
        'data'    => [
            'object' => [
                'id'              => 'pi_connect_evt',
                'latest_charge'   => 'ch_connect_001',
                'application_fee' => null,
            ],
        ],
    ];

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload);

    $response->assertStatus(200);
    expect($payment->fresh()->status)->toBe('completed');
    expect($payment->fresh()->stripe_charge_id)->toBe('ch_connect_001');
});

it('instrada charge.refunded al RefundService con accountId', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'    => $appointment->id,
        'stripe_charge_id'  => 'ch_refund_connect',
        'stripe_account_id' => 'acct_refund_connect',
        'status'            => 'completed',
        'amount'            => 50.0,
    ]);

    $payload = [
        'id'      => 'evt_charge_refunded',
        'type'    => 'charge.refunded',
        'account' => 'acct_refund_connect',
        'data'    => [
            'object' => [
                'id'      => 'ch_refund_connect',
                'refunds' => ['data' => [['id' => 're_connect_001', 'amount' => 5000, 'status' => 'succeeded']]],
            ],
        ],
    ];

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload);

    $response->assertStatus(200);
    expect($payment->fresh()->status)->toBe('refunded');
});
