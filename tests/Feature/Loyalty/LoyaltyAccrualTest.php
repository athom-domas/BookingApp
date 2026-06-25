<?php

use App\Events\PaymentRefunded;
use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PaymentService;

beforeEach(function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->customer = User::factory()->create();
    $this->appointment = Appointment::factory()->create(['user_id' => $this->customer->id, 'status' => 'confirmed']);
});

it('accredita i punti quando un pagamento in salone viene completato', function () {
    app(PaymentService::class)->recordInPersonPayment($this->appointment->id, 'cash', 80.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80);
});

it('non raddoppia i punti se il completamento viene rieseguito', function () {
    $service = app(PaymentService::class);
    $service->recordInPersonPayment($this->appointment->id, 'cash', 80.0);

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();
    \App\Events\PaymentCompleted::dispatch($payment);

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

it('accredita i punti quando un appuntamento pending passa a confirmed', function () {
    $pending = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => 75,
    ]);

    $pending->update(['status' => 'confirmed']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(75)
        ->and(LoyaltyTransaction::where('appointment_id', $pending->id)->where('type', 'earn')->count())->toBe(1);
});

it('non accredita una seconda volta alla conferma del pagamento se già accreditato alla conferma', function () {
    $pending = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'pending',
        'final_price' => 80,
    ]);
    $pending->update(['status' => 'confirmed']);

    // Simula PaymentCompleted (stesso importo)
    $payment = Payment::factory()->create([
        'appointment_id' => $pending->id,
        'user_id'        => $this->customer->id,
        'amount'         => 80,
        'status'         => 'completed',
    ]);
    \App\Events\PaymentCompleted::dispatch($payment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80)
        ->and(LoyaltyTransaction::where('appointment_id', $pending->id)->where('type', 'earn')->count())->toBe(1);
});

it('storna i punti quando un appuntamento viene cancellato', function () {
    $appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'status'      => 'confirmed',
        'final_price' => 80,
    ]);

    $appointment->update(['status' => 'cancelled']);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0)
        ->and(LoyaltyTransaction::where('appointment_id', $appointment->id)->where('type', 'reverse')->count())->toBe(1);
});

it('storna i punti quando un pagamento completato viene rimborsato', function () {
    app(PaymentService::class)->recordInPersonPayment($this->appointment->id, 'cash', 80.0);
    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    PaymentRefunded::dispatch($payment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0);
});
