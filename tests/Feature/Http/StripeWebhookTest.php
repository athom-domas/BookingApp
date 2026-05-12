<?php

use App\Services\PaymentService;

it('accepts valid signed Stripe webhook payloads', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $payload = json_encode([
        'id' => 'evt_test',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_webhook',
                'object' => 'payment_intent',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

    $this->mock(PaymentService::class)
        ->shouldReceive('handleStripeWebhook')
        ->once()
        ->with(Mockery::on(fn (array $event) => $event['type'] === 'payment_intent.succeeded'));

    $this->call('POST', '/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload)->assertOk()
        ->assertJson(['received' => true]);
});

it('rejects invalid Stripe webhook signatures', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);

    $this->mock(PaymentService::class)->shouldNotReceive('handleStripeWebhook');

    $this->call('POST', '/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalid',
    ], '{"type":"payment_intent.succeeded"}')->assertBadRequest();
});
