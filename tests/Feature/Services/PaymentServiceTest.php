<?php

use App\Exceptions\BookingException;
use App\Jobs\SendAppointmentConfirmation;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Queue::fake();
    $this->makePaymentService = function (MockInterface $mockStripe): PaymentService {
        return new PaymentService($mockStripe);
    };
});

it('initiateStripePayment creates a pending payment record', function () {
    $appointment = Appointment::factory()->create();

    $fakeIntent = PaymentIntent::constructFrom([
        'id' => 'pi_test_123',
        'object' => 'payment_intent',
        'amount' => 5000,
        'currency' => 'eur',
        'status' => 'requires_payment_method',
        'client_secret' => 'pi_test_123_secret_test',
    ]);

    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')->once()->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $payment = ($this->makePaymentService)($mockStripe)->initiateStripePayment($appointment->id, 5000);

    expect($payment->status)->toBe('pending');
    expect($payment->stripe_transaction_id)->toBe('pi_test_123');
    expect((float) $payment->amount)->toBe(50.00);
    expect($payment->appointment_id)->toBe($appointment->id);
    expect($payment->stripe_response['client_secret'])->toBe('pi_test_123_secret_test');
    expect($payment->payment_method)->toBe('stripe');
});

it('handleStripeWebhook marks payment as completed on succeeded event', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'stripe_transaction_id' => 'pi_test_456',
        'status' => 'pending',
    ]);

    $mockStripe = Mockery::mock(StripeClient::class);
    ($this->makePaymentService)($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test_456']],
    ]);

    expect($payment->fresh()->status)->toBe('completed');
    expect($appointment->fresh()->status)->toBe('confirmed');
    Queue::assertPushed(SendAppointmentConfirmation::class, fn ($job) => $job->appointment->id === $appointment->id);
});

it('handleStripeWebhook marks payment as failed on failed event', function () {
    $payment = Payment::factory()->create(['stripe_transaction_id' => 'pi_test_789', 'status' => 'pending']);

    $mockStripe = Mockery::mock(StripeClient::class);
    ($this->makePaymentService)($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => ['id' => 'pi_test_789']],
    ]);

    expect($payment->fresh()->status)->toBe('failed');
});

it('handleStripeWebhook marks payment as cancelled on canceled event', function () {
    $appointment = Appointment::factory()->create();
    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'stripe_transaction_id' => 'pi_test_canceled',
        'status' => 'pending',
        'amount' => 50.00,
    ]);

    $mockStripe = Mockery::mock(StripeClient::class);
    ($this->makePaymentService)($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.canceled',
        'data' => ['object' => ['id' => 'pi_test_canceled']],
    ]);

    expect($payment->fresh()->status)->toBe('cancelled');
});

it('handleStripeWebhook ignores unknown transaction IDs without error', function () {
    $mockStripe = Mockery::mock(StripeClient::class);

    expect(fn () => ($this->makePaymentService)($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_unknown']],
    ]))->not->toThrow(Throwable::class);
});

it('confirmPayment marks payment and appointment as completed when Stripe succeeded', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'stripe_transaction_id' => 'pi_test_confirm',
        'status' => 'pending',
    ]);

    $fakeIntent = PaymentIntent::constructFrom([
        'id' => 'pi_test_confirm',
        'object' => 'payment_intent',
        'status' => 'succeeded',
    ]);

    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('retrieve')->once()->with('pi_test_confirm')->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $result = ($this->makePaymentService)($mockStripe)->confirmPayment($appointment->id);

    expect($result->status)->toBe('completed');
    expect($appointment->fresh()->status)->toBe('confirmed');
    Queue::assertPushed(SendAppointmentConfirmation::class, fn ($job) => $job->appointment->id === $appointment->id);
});

it('refundPayment updates status to refunded', function () {
    $payment = Payment::factory()->create([
        'status' => 'completed',
        'stripe_transaction_id' => 'pi_test_refund',
        'stripe_charge_id'      => 'ch_test_refund',
    ]);

    $mockRefundService = Mockery::mock(\App\Services\RefundService::class);
    $mockRefundService->shouldReceive('refund')->once()->withArgs(function ($p) use ($payment) {
        return $p->id === $payment->id;
    })->andReturnUsing(function ($p) {
        $p->update(['status' => 'refunded']);
        return new \App\Models\StripeRefund();
    });
    $this->app->instance(\App\Services\RefundService::class, $mockRefundService);

    $mockStripe = Mockery::mock(StripeClient::class);
    $result = ($this->makePaymentService)($mockStripe)->refundPayment($payment->id);

    expect($result->status)->toBe('refunded');
});

it('refundPayment throws BookingException if payment is not completed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    $mockRefundService = Mockery::mock(\App\Services\RefundService::class);
    $mockRefundService->shouldReceive('refund')->once()->andThrow(new \App\Exceptions\BookingException('Solo i pagamenti completati possono essere rimborsati.'));
    $this->app->instance(\App\Services\RefundService::class, $mockRefundService);

    $mockStripe = Mockery::mock(StripeClient::class);

    expect(fn () => ($this->makePaymentService)($mockStripe)->refundPayment($payment->id))
        ->toThrow(BookingException::class);
});

it('recordInPersonPayment creates a completed cash payment', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending', 'final_price' => 50.00]);
    $mockStripe = Mockery::mock(StripeClient::class);

    $payment = ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00);

    expect($payment->status)->toBe('completed');
    expect($payment->payment_method)->toBe('cash');
    expect((float) $payment->amount)->toBe(50.00);
    expect($payment->stripe_transaction_id)->toBeNull();
    expect($payment->appointment_id)->toBe($appointment->id);
});

it('recordInPersonPayment creates a completed pos payment', function () {
    $appointment = Appointment::factory()->create();
    $mockStripe = Mockery::mock(StripeClient::class);

    $payment = ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'pos', 30.00);

    expect($payment->status)->toBe('completed');
    expect($payment->payment_method)->toBe('pos');
});

it('recordInPersonPayment sets appointment status to confirmed', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $mockStripe = Mockery::mock(StripeClient::class);

    ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00);

    expect($appointment->fresh()->status)->toBe('confirmed');
});

it('recordInPersonPayment does not dispatch SendAppointmentConfirmation', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $mockStripe = Mockery::mock(StripeClient::class);

    ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00);

    Queue::assertNotPushed(SendAppointmentConfirmation::class);
});

it('recordInPersonPayment throws BookingException if a completed payment already exists', function () {
    $appointment = Appointment::factory()->create();
    Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'status' => 'completed',
    ]);
    $mockStripe = Mockery::mock(StripeClient::class);

    expect(fn () => ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00))
        ->toThrow(BookingException::class);
});

it('initiateStripePayment usa direct charge con stripe_account option se business ha account attivo', function () {
    $account = \App\Models\StripeConnectAccount::factory()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_direct_test',
    ]);

    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $this->business->update(['stripe_platform_fee_percent' => 5.0]);

    $fakeIntent = \Stripe\PaymentIntent::constructFrom([
        'id'            => 'pi_direct_test',
        'object'        => 'payment_intent',
        'amount'        => 10000,
        'currency'      => 'eur',
        'status'        => 'requires_payment_method',
        'client_secret' => 'pi_direct_test_secret',
    ]);

    $capturedParams = null;
    $capturedOpts   = null;

    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')
        ->once()
        ->withArgs(function ($params, $opts = []) use (&$capturedParams, &$capturedOpts) {
            $capturedParams = $params;
            $capturedOpts   = $opts;
            return true;
        })
        ->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $service = new \App\Services\PaymentService(
        $mockStripe,
        app(\App\Services\StripeConnectService::class)
    );
    $payment = $service->initiateStripePayment($appointment->id, 10000, $this->business);

    expect(array_key_exists('on_behalf_of', $capturedParams))->toBeFalse();
    expect(array_key_exists('transfer_data', $capturedParams))->toBeFalse();
    expect($capturedParams['application_fee_amount'])->toBe(500);
    expect($capturedOpts['stripe_account'])->toBe('acct_direct_test');
    expect($payment->stripe_account_id)->toBe('acct_direct_test');
    expect((float) $payment->platform_fee_amount)->toBe(5.0);
});

it('initiateStripePayment non aggiunge destination params se business non ha account attivo', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);

    $fakeIntent = PaymentIntent::constructFrom([
        'id'           => 'pi_no_connect',
        'object'       => 'payment_intent',
        'amount'       => 5000,
        'currency'     => 'eur',
        'status'       => 'requires_payment_method',
        'client_secret'=> 'secret',
    ]);

    $capturedParams = null;
    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')
        ->withArgs(function ($params) use (&$capturedParams) {
            $capturedParams = $params;
            return true;
        })
        ->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $service = new \App\Services\PaymentService(
        $mockStripe,
        app(\App\Services\StripeConnectService::class)
    );
    $payment = $service->initiateStripePayment($appointment->id, 5000, $this->business);

    expect(array_key_exists('on_behalf_of', $capturedParams))->toBeFalse();
    expect(array_key_exists('application_fee_amount', $capturedParams))->toBeFalse();
    expect((float) $payment->platform_fee_amount)->toBe(0.0);
});
