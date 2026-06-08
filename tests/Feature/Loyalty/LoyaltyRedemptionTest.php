<?php

use App\Filament\Resources\AppointmentResource\Pages\EditAppointment;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    foreach (['appointments.edit', 'appointments.view_all', 'appointments.payments'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->business = Business::withoutGlobalScopes()->orderBy('id')->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');

    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_points_per_euro'   => 1,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
    ]);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']));
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 120]);

    $this->appointment = Appointment::factory()->create([
        'user_id'     => $this->customer->id,
        'staff_id'    => $this->staff->id,
        'status'      => 'confirmed',
        'final_price' => 100,
    ]);
});

it('applica lo sconto fedeltà e scala i punti al completamento', function () {
    livewire(EditAppointment::class, ['record' => $this->appointment->id])
        ->set('data.status', 'completed')
        ->set('data.payment_method', 'cash')
        ->set('data.payment_amount', 100)
        ->set('data.apply_loyalty_discount', true)
        ->call('save')
        ->assertHasNoFormErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    // Con accrual alla conferma: observer ha già accreditato 100 punti (final_price) alla creazione.
    // Saldo: 120 (base) + 100 (conferma) = 220 → redeem -100 = 120. earn tx = 100 (final_price).
    expect((float) $payment->amount)->toBe(90.0)
        ->and(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(120)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-100)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->first()->points)->toBe(100);
});

it('non va in errore quando il Select status è disabilitato e assente da getState()', function () {
    // Appuntamento già completed con pagamento non rimborsato: il Select status è ->disabled(),
    // quindi getState() lo omette. beforeSave deve leggere lo stato grezzo senza "Undefined array key status".
    $this->appointment->update(['status' => 'completed']);
    Payment::create([
        'appointment_id' => $this->appointment->id,
        'user_id'        => $this->customer->id,
        'amount'         => 100,
        'status'         => 'completed',
        'payment_method' => 'cash',
    ]);

    livewire(EditAppointment::class, ['record' => $this->appointment->id])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('non applica sconto se il toggle è spento', function () {
    livewire(EditAppointment::class, ['record' => $this->appointment->id])
        ->set('data.status', 'completed')
        ->set('data.payment_method', 'cash')
        ->set('data.payment_amount', 100)
        ->set('data.apply_loyalty_discount', false)
        ->call('save')
        ->assertHasNoFormErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    expect((float) $payment->amount)->toBe(100.0)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->exists())->toBeFalse();
});
