<?php

use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
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
    foreach (['appointments.edit', 'appointments.view_all', 'appointments.payments'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->business = Business::withoutGlobalScopes()->orderBy('id')->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

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
        'user_id'    => $this->customer->id,
        'staff_id'   => $this->admin->id,
        'status'     => 'confirmed',
        'final_price' => 100,
    ]);
});

/*
 * NOTE: callTableAction(data:) silently drops form data in Filament 4.11.2 — the fillForm()
 * call inside callAction() does not persist values through the Livewire snapshot cycle for
 * table actions. mountTableAction + set + callMountedTableAction is the workaround.
 */

it('applica lo sconto fedeltà dalla quick-action registra pagamento', function () {
    livewire(ListAppointments::class)
        ->mountTableAction('register_payment', $this->appointment)
        ->set('mountedActions.0.data.method', 'cash')
        ->set('mountedActions.0.data.amount', 100)
        ->set('mountedActions.0.data.loyalty_tier_index', 0)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    // 120 punti base → redeem −100 = 20 rimanenti. Prezzo: 100 − 10% = 90.
    expect((float) $payment->amount)->toBe(90.0)
        ->and(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(20)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-100)
        ->and((float) $this->appointment->fresh()->loyalty_discounted_price)->toBe(90.0);
});

it('non applica sconto dalla quick-action se non si sceglie un livello', function () {
    livewire(ListAppointments::class)
        ->mountTableAction('register_payment', $this->appointment)
        ->set('mountedActions.0.data.method', 'cash')
        ->set('mountedActions.0.data.amount', 100)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    expect((float) $payment->amount)->toBe(100.0)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->exists())->toBeFalse();
});
