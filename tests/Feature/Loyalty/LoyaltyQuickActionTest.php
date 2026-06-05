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
        ->set('mountedActions.0.data.apply_loyalty_discount', true)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    expect((float) $payment->amount)->toBe(90.0)
        ->and(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(110)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-100);
});

it('non applica sconto dalla quick-action se il toggle è spento', function () {
    livewire(ListAppointments::class)
        ->mountTableAction('register_payment', $this->appointment)
        ->set('mountedActions.0.data.method', 'cash')
        ->set('mountedActions.0.data.amount', 100)
        ->set('mountedActions.0.data.apply_loyalty_discount', false)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    expect((float) $payment->amount)->toBe(100.0)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->exists())->toBeFalse();
});
