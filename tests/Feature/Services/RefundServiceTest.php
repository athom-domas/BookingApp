<?php

use App\Events\PaymentRefunded;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\StripeRefund;
use App\Services\RefundService;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Stripe\Refund;
use Stripe\StripeClient;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Event::fake();
    $this->makeService = function (MockInterface $mockStripe): RefundService {
        return new RefundService($mockStripe);
    };
});

it('rimborsa un pagamento completato e crea un StripeRefund record', function () {
    $appointment = Appointment::factory()->create(['status' => 'confirmed']);
    $payment = Payment::factory()->create([
        'appointment_id'    => $appointment->id,
        'status'            => 'completed',
        'payment_method'    => 'stripe',
        'stripe_charge_id'  => 'ch_test_refund',
        'stripe_account_id' => 'acct_test',
        'amount'            => 100.00,
    ]);

    $fakeRefund = Refund::constructFrom([
        'id'     => 're_test_001',
        'object' => 'refund',
        'amount' => 10000,
        'status' => 'succeeded',
        'charge' => 'ch_test_refund',
    ]);

    $mockRefunds = Mockery::mock();
    $mockRefunds->shouldReceive('create')
        ->with(Mockery::on(fn ($p) =>
            $p['charge'] === 'ch_test_refund'
            && $p['refund_application_fee'] === true
            && $p['reverse_transfer'] === true
        ))
        ->andReturn($fakeRefund);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('refunds')->andReturn($mockRefunds);

    $refundRecord = ($this->makeService)($mockStripe)->refund($payment);

    expect($refundRecord->stripe_refund_id)->toBe('re_test_001');
    expect($refundRecord->status)->toBe('succeeded');
    expect($refundRecord->refund_application_fee)->toBeTrue();
    expect($refundRecord->reverse_transfer)->toBeTrue();
    expect($payment->fresh()->status)->toBe('refunded');
    Event::assertDispatched(PaymentRefunded::class);
});

it('lancia eccezione se payment non è completed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);
    $mockStripe = Mockery::mock(StripeClient::class);

    expect(fn () => ($this->makeService)($mockStripe)->refund($payment))
        ->toThrow(\App\Exceptions\BookingException::class);
});

it('non aggiorna payment.status se Stripe restituisce status pending (saldo insufficiente)', function () {
    $payment = Payment::factory()->create([
        'status'            => 'completed',
        'stripe_charge_id'  => 'ch_insufficient',
        'stripe_account_id' => 'acct_broke',
        'amount'            => 50.00,
    ]);

    $fakeRefund = Refund::constructFrom([
        'id'     => 're_pending_001',
        'object' => 'refund',
        'amount' => 5000,
        'status' => 'pending',
        'charge' => 'ch_insufficient',
    ]);

    $mockRefunds = Mockery::mock();
    $mockRefunds->shouldReceive('create')->andReturn($fakeRefund);
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('refunds')->andReturn($mockRefunds);

    ($this->makeService)($mockStripe)->refund($payment);

    expect($payment->fresh()->status)->toBe('completed');
    expect(StripeRefund::where('stripe_refund_id', 're_pending_001')->first()->status)->toBe('pending');
});

it('handleExternalRefund sincronizza rimborso arrivato via webhook', function () {
    $payment = Payment::factory()->create([
        'status'           => 'completed',
        'stripe_charge_id' => 'ch_external_refund',
    ]);

    $chargePayload = [
        'id'      => 'ch_external_refund',
        'refunds' => [
            'data' => [[
                'id'     => 're_external_001',
                'amount' => (int) ($payment->amount * 100),
                'status' => 'succeeded',
                'charge' => 'ch_external_refund',
                'reason' => null,
            ]],
        ],
    ];

    $mockStripe = Mockery::mock(StripeClient::class);
    ($this->makeService)($mockStripe)->handleExternalRefund($chargePayload);

    expect($payment->fresh()->status)->toBe('refunded');
    expect(StripeRefund::where('stripe_refund_id', 're_external_001')->exists())->toBeTrue();
    Event::assertDispatched(PaymentRefunded::class);
});
