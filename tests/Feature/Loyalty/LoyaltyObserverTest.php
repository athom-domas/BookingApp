<?php

use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;

beforeEach(function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->customer = User::factory()->create();
});

it('accredita quando un appuntamento viene creato come confirmed', function () {
    Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 80,
    ]);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->value('points'))->toBe(80);
});

it('non accredita quando un appuntamento viene creato come pending', function () {
    Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => 80,
    ]);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('non accredita quando final_price è null', function () {
    Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => null,
    ]);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('non accredita quando final_price è zero', function () {
    Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 0,
    ]);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('accredita quando lo status passa da pending a confirmed', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => 60,
    ]);

    $appointment->update(['status' => 'confirmed']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->value('points'))->toBe(60);
});

it('storna i punti quando lo status passa a cancelled', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 60,
    ]);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(60);

    $appointment->update(['status' => 'cancelled']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0);
});

it('non accredita quando lo status cambia senza toccare confirmed (es. confirmed→completed)', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 50,
    ]);

    // Svuota account per isolare l'effetto dell'update successivo
    LoyaltyAccount::where('user_id', $this->customer->id)->update(['points' => 0]);
    LoyaltyTransaction::where('loyalty_account_id',
        LoyaltyAccount::where('user_id', $this->customer->id)->value('id')
    )->delete();

    $appointment->update(['status' => 'completed']);

    // Nessun nuovo accredito: l'observer non reagisce a completed
    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0);
});

it('cancella il payment pending quando l appuntamento viene cancellato', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 60,
    ]);

    $payment = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'user_id'               => $this->customer->id,
        'status'                => 'pending',
        'payment_method'        => 'cash',
        'stripe_transaction_id' => null,
    ]);

    $appointment->update(['status' => 'cancelled']);

    expect($payment->fresh()->status)->toBe('cancelled');
});

it('non cancella il payment se non è pending', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 60,
    ]);

    $payment = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'user_id'               => $this->customer->id,
        'status'                => 'completed',
        'payment_method'        => 'cash',
        'stripe_transaction_id' => null,
    ]);

    $appointment->update(['status' => 'cancelled']);

    expect($payment->fresh()->status)->toBe('completed');
});

it('non lancia eccezioni se non ci sono punti da stornare', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => null,
    ]);

    expect(fn () => $appointment->update(['status' => 'cancelled']))->not->toThrow(\Throwable::class);
});

it('non accredita se il programma fedeltà è disattivo alla creazione', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);

    Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 80,
    ]);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});
