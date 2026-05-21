<?php

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\AppointmentResource\Pages\EditAppointment;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('staff can access edit page for their own pending appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'pending',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertSuccessful();
});

it('staff can access edit page for their own confirmed appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'confirmed',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertSuccessful();
});

it('staff cannot access edit page for another staff appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $otherStaff = User::factory()->create();
    $otherStaff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $otherStaff->id,
        'user_id'  => $customer->id,
        'status'   => 'pending',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertForbidden();
});

it('staff cannot access edit page for a completed appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'completed',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertForbidden();
});

it('staff cannot access edit page for a cancelled appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'cancelled',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertForbidden();
});

it('staff can change status on their own appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'pending',
    ]);

    $this->actingAs($staff);

    Livewire::test(EditAppointment::class, ['record' => $appointment->id])
        ->set('data.status', 'confirmed')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($appointment->refresh()->status)->toBe('confirmed');
});
