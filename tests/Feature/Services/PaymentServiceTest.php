<?php

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Stripe\Refund;

function makePaymentService(MockInterface $mockStripe): PaymentService
{
    return new PaymentService($mockStripe);
}

it('initiateStripePayment creates a pending payment record', function () {
    $appointment = Appointment::factory()->create();

    $fakeIntent = PaymentIntent::constructFrom([
        'id'       => 'pi_test_123',
        'object'   => 'payment_intent',
        'amount'   => 5000,
        'currency' => 'eur',
        'status'   => 'requires_payment_method',
    ]);

    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')->once()->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $payment = makePaymentService($mockStripe)->initiateStripePayment($appointment->id, 5000);

    expect($payment->status)->toBe('pending');
    expect($payment->stripe_transaction_id)->toBe('pi_test_123');
    expect((float) $payment->amount)->toBe(50.00);
    expect($payment->appointment_id)->toBe($appointment->id);
});

it('handleStripeWebhook marks payment as completed on succeeded event', function () {
    $payment = Payment::factory()->create(['stripe_transaction_id' => 'pi_test_456', 'status' => 'pending']);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    makePaymentService($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test_456']],
    ]);

    expect($payment->fresh()->status)->toBe('completed');
});

it('handleStripeWebhook marks payment as failed on failed event', function () {
    $payment = Payment::factory()->create(['stripe_transaction_id' => 'pi_test_789', 'status' => 'pending']);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    makePaymentService($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => ['id' => 'pi_test_789']],
    ]);

    expect($payment->fresh()->status)->toBe('failed');
});

it('handleStripeWebhook marks payment as cancelled on canceled event', function () {
    $appointment = Appointment::factory()->create();
    $payment = Payment::factory()->create([
        'appointment_id'         => $appointment->id,
        'stripe_transaction_id'  => 'pi_test_canceled',
        'status'                 => 'pending',
        'amount'                 => 50.00,
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    makePaymentService($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.canceled',
        'data' => ['object' => ['id' => 'pi_test_canceled']],
    ]);

    expect($payment->fresh()->status)->toBe('cancelled');
});

it('handleStripeWebhook ignores unknown transaction IDs without error', function () {
    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);

    expect(fn () => makePaymentService($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_unknown']],
    ]))->not->toThrow(\Throwable::class);
});

it('refundPayment updates status to refunded', function () {
    $payment = Payment::factory()->create([
        'status'                => 'completed',
        'stripe_transaction_id' => 'pi_test_refund',
    ]);

    $fakeRefund = Refund::constructFrom([
        'id'             => 're_test_123',
        'payment_intent' => 'pi_test_refund',
        'status'         => 'succeeded',
    ]);

    $mockRefunds = Mockery::mock();
    $mockRefunds->shouldReceive('create')->once()->andReturn($fakeRefund);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('refunds')->andReturn($mockRefunds);

    $result = makePaymentService($mockStripe)->refundPayment($payment->id);

    expect($result->status)->toBe('refunded');
});

it('refundPayment throws BookingException if payment is not completed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);

    expect(fn () => makePaymentService($mockStripe)->refundPayment($payment->id))
        ->toThrow(BookingException::class);
});
