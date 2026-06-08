<?php

use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\PaymentService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);

    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_points_per_euro'   => 1,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
    ]);

    $this->customer = User::factory()->create()->assignRole('customer');

    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 150]);

    $this->appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => 100,
    ]);
});

it('applyDiscount: redirect con errore se i punti sono insufficienti', function () {
    LoyaltyAccount::where('user_id', $this->customer->id)->update(['points' => 50]);

    $payment = Payment::factory()->create([
        'appointment_id'        => $this->appointment->id,
        'user_id'               => $this->customer->id,
        'status'                => 'pending',
        'payment_method'        => 'stripe',
        'stripe_transaction_id' => 'pi_test_insufficient',
        'amount'                => 100,
    ]);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.discount', $this->appointment))
        ->assertRedirect()
        ->assertSessionHasErrors('discount');
});

it('applyDiscount: non applica il secondo sconto se già applicato', function () {
    $payment = Payment::factory()->create([
        'appointment_id'              => $this->appointment->id,
        'user_id'                     => $this->customer->id,
        'status'                      => 'pending',
        'payment_method'              => 'stripe',
        'stripe_transaction_id'       => 'pi_test_already_discounted',
        'amount'                      => 90,
        'loyalty_discount_percentage' => 10,
        'loyalty_original_amount'     => 100,
    ]);

    // Il mock garantisce che applyLoyaltyDiscount non venga mai chiamato
    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldNotReceive('applyLoyaltyDiscount');
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.discount', $this->appointment))
        ->assertRedirect(route('portal.appointments.payment', $this->appointment));

    expect($payment->fresh()->loyalty_discount_percentage)->toBe(10);
});

it('applyDiscount: redirect senza errori se loyalty è disattivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);

    Payment::factory()->create([
        'appointment_id'        => $this->appointment->id,
        'user_id'               => $this->customer->id,
        'status'                => 'pending',
        'payment_method'        => 'stripe',
        'stripe_transaction_id' => 'pi_test_disabled',
        'amount'                => 100,
    ]);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.discount', $this->appointment))
        ->assertRedirect(route('portal.appointments.payment', $this->appointment))
        ->assertSessionHasNoErrors();
});

it('applyDiscount: applica lo sconto chiamando PaymentService', function () {
    $payment = Payment::factory()->create([
        'appointment_id'        => $this->appointment->id,
        'user_id'               => $this->customer->id,
        'status'                => 'pending',
        'payment_method'        => 'stripe',
        'stripe_transaction_id' => 'pi_test_apply',
        'amount'                => 100,
    ]);

    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldReceive('applyLoyaltyDiscount')
        ->once()
        ->withArgs(function ($p, $pct, $original) use ($payment) {
            return $p->id === $payment->id && $pct === 10 && (float) $original === 100.0;
        });
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.discount', $this->appointment))
        ->assertRedirect(route('portal.appointments.payment', $this->appointment))
        ->assertSessionHasNoErrors();
});

it('removeDiscount: ripristina l importo chiamando PaymentService', function () {
    $payment = Payment::factory()->create([
        'appointment_id'              => $this->appointment->id,
        'user_id'                     => $this->customer->id,
        'status'                      => 'pending',
        'payment_method'              => 'stripe',
        'stripe_transaction_id'       => 'pi_test_remove',
        'amount'                      => 90,
        'loyalty_discount_percentage' => 10,
        'loyalty_original_amount'     => 100,
    ]);

    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldReceive('removeLoyaltyDiscount')
        ->once()
        ->withArgs(fn ($p) => $p->id === $payment->id);
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->delete(route('portal.appointments.payment.discount.remove', $this->appointment))
        ->assertRedirect(route('portal.appointments.payment', $this->appointment))
        ->assertSessionHasNoErrors();
});

it('removeDiscount: no-op se lo sconto non è applicato', function () {
    Payment::factory()->create([
        'appointment_id'              => $this->appointment->id,
        'user_id'                     => $this->customer->id,
        'status'                      => 'pending',
        'payment_method'              => 'stripe',
        'stripe_transaction_id'       => 'pi_test_noop',
        'amount'                      => 100,
        'loyalty_discount_percentage' => null,
    ]);

    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldNotReceive('removeLoyaltyDiscount');
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->delete(route('portal.appointments.payment.discount.remove', $this->appointment))
        ->assertRedirect(route('portal.appointments.payment', $this->appointment));
});

it('confirmPayment: rimuove sconto e mostra errore se le impostazioni sono cambiate dopo applyDiscount', function () {
    // Soglia alzata a 200: il cliente ha 150 punti, quindi redeem() ritornerà 0
    SystemSetting::current()->update(['loyalty_reward_threshold' => 200]);

    $payment = Payment::factory()->create([
        'appointment_id'              => $this->appointment->id,
        'user_id'                     => $this->customer->id,
        'status'                      => 'pending',
        'payment_method'              => 'stripe',
        'stripe_transaction_id'       => 'pi_test_threshold_changed',
        'amount'                      => 90,
        'loyalty_discount_percentage' => 10,
        'loyalty_original_amount'     => 100,
    ]);

    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldReceive('removeLoyaltyDiscount')->once();
    $mock->shouldNotReceive('confirmPayment');
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.confirm', $this->appointment))
        ->assertRedirect()
        ->assertSessionHasErrors('payment');

    // Nessuna transazione di riscatto deve essere stata creata
    expect(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->exists())->toBeFalse();
});

it('confirmPayment: riscatta i punti e conferma il pagamento quando lo sconto è valido', function () {
    $payment = Payment::factory()->create([
        'appointment_id'              => $this->appointment->id,
        'user_id'                     => $this->customer->id,
        'status'                      => 'pending',
        'payment_method'              => 'stripe',
        'stripe_transaction_id'       => 'pi_test_valid_redeem',
        'amount'                      => 90,
        'loyalty_discount_percentage' => 10,
        'loyalty_original_amount'     => 100,
    ]);

    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldNotReceive('removeLoyaltyDiscount');
    $mock->shouldReceive('confirmPayment')
        ->once()
        ->with($this->appointment->id)
        ->andReturn($payment->fresh());
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.confirm', $this->appointment))
        ->assertRedirect(route('portal.appointments.show', $this->appointment));

    $account = LoyaltyAccount::where('user_id', $this->customer->id)->first();
    expect($account->points)->toBe(50)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->count())->toBe(1);
});

it('confirmPayment: non chiama redeem se lo sconto non è applicato', function () {
    $payment = Payment::factory()->create([
        'appointment_id'              => $this->appointment->id,
        'user_id'                     => $this->customer->id,
        'status'                      => 'pending',
        'payment_method'              => 'stripe',
        'stripe_transaction_id'       => 'pi_test_no_discount',
        'amount'                      => 100,
        'loyalty_discount_percentage' => null,
    ]);

    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldNotReceive('removeLoyaltyDiscount');
    $mock->shouldReceive('confirmPayment')
        ->once()
        ->with($this->appointment->id)
        ->andReturn($payment->fresh());
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.confirm', $this->appointment))
        ->assertRedirect(route('portal.appointments.show', $this->appointment));

    expect(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->exists())->toBeFalse();
});

it('confirmPayment: redirect con errore se confirmPayment lancia BookingException', function () {
    $payment = Payment::factory()->create([
        'appointment_id'        => $this->appointment->id,
        'user_id'               => $this->customer->id,
        'status'                => 'pending',
        'payment_method'        => 'stripe',
        'stripe_transaction_id' => 'pi_test_fail',
        'amount'                => 100,
    ]);

    $mock = Mockery::mock(PaymentService::class);
    $mock->shouldReceive('confirmPayment')
        ->once()
        ->andThrow(new \App\Exceptions\BookingException('Il pagamento non è andato a buon fine.'));
    $this->app->instance(PaymentService::class, $mock);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.confirm', $this->appointment))
        ->assertRedirect()
        ->assertSessionHasErrors('payment');
});

it('403 se l utente tenta di applicare sconto su un appuntamento altrui', function () {
    $other = User::factory()->create()->assignRole('customer');

    $otherAppointment = Appointment::factory()->create([
        'user_id'     => $other->id,
        'status'      => 'pending',
        'final_price' => 100,
    ]);

    Payment::factory()->create([
        'appointment_id'        => $otherAppointment->id,
        'user_id'               => $other->id,
        'status'                => 'pending',
        'payment_method'        => 'stripe',
        'stripe_transaction_id' => 'pi_test_other',
        'amount'                => 100,
    ]);

    $this->actingAs($this->customer)
        ->post(route('portal.appointments.payment.discount', $otherAppointment))
        ->assertForbidden();
});
