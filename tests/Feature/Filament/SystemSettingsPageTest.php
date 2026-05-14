<?php

use App\Filament\Pages\SystemSettings;
use App\Models\SystemSetting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('admin can view the system settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->assertSuccessful();
});

it('form is pre-filled with current slot_generation_weeks', function () {
    SystemSetting::current()->update(['slot_generation_weeks' => 6]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->assertSet('data.slot_generation_weeks', 6);
});

it('admin can update slot_generation_weeks', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_generation_weeks', 8)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SystemSetting::current()->slot_generation_weeks)->toBe(8);
});

it('rejects slot_generation_weeks below 1', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_generation_weeks', 0)
        ->call('save')
        ->assertHasFormErrors(['slot_generation_weeks']);
});

it('rejects slot_generation_weeks above 52', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_generation_weeks', 53)
        ->call('save')
        ->assertHasFormErrors(['slot_generation_weeks']);
});

it('non-admin cannot access the system settings page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(SystemSettings::canAccess())->toBeFalse();
});
