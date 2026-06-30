<?php

use App\Events\PaymentRefunded;
use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LoyaltyService;

beforeEach(function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->customer   = User::factory()->create();
    $this->appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 80,
    ]);
});

it('accredita i punti quando un appuntamento viene completato', function () {
    $this->appointment->update(['status' => 'completed']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80);
});

it('non accredita una seconda volta se l observer viene rieseguito', function () {
    $this->appointment->update(['status' => 'completed']);

    app(LoyaltyService::class)->accrue($this->appointment->fresh(), 80.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->count())->toBe(1);
});

it('non accredita i punti per appuntamenti in stato pending', function () {
    Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => 75,
    ]);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('accredita i punti quando un appuntamento pending passa a completed', function () {
    $pending = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => 75,
    ]);

    $pending->update(['status' => 'completed']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(75)
        ->and(LoyaltyTransaction::where('appointment_id', $pending->id)->where('type', 'earn')->count())->toBe(1);
});

it('non accredita quando la loyalty è disabilitata al momento del completamento', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);

    $this->appointment->update(['status' => 'completed']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('storna i punti quando un appuntamento viene cancellato', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 80,
    ]);

    $appointment->update(['status' => 'completed']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80);

    $appointment->update(['status' => 'cancelled']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0)
        ->and(LoyaltyTransaction::where('appointment_id', $appointment->id)->where('type', 'reverse')->count())->toBe(1);
});

it('non storna i punti quando un pagamento viene rimborsato', function () {
    $this->appointment->update(['status' => 'completed']);

    $payment = Payment::factory()->create([
        'appointment_id' => $this->appointment->id,
        'user_id'        => $this->customer->id,
        'amount'         => 80,
        'status'         => 'completed',
    ]);

    PaymentRefunded::dispatch($payment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'reverse')->exists())->toBeFalse();
});
