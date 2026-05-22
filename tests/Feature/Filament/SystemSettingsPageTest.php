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

it('form is pre-filled with current settings', function () {
    SystemSetting::current()->update([
        'slot_granularity_minutes' => 15,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->assertSet('data.slot_granularity_minutes', 15);
});

it('admin can update system settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_granularity_minutes', 20)
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = SystemSetting::current();
    expect($setting->slot_granularity_minutes)->toBe(20);
});

it('non-admin cannot access the system settings page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(SystemSettings::canAccess())->toBeFalse();
});
