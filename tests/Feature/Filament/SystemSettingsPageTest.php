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
        'slot_granularity_minutes'     => 15,
        'hold_duration_minutes'        => 10,
        'hold_extension_minutes'       => 3,
        'min_service_duration_minutes' => 20,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->assertSet('data.slot_granularity_minutes', 15)
        ->assertSet('data.hold_duration_minutes', 10)
        ->assertSet('data.hold_extension_minutes', 3)
        ->assertSet('data.min_service_duration_minutes', 20);
});

it('admin can update system settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_granularity_minutes', 20)
        ->set('data.hold_duration_minutes', 8)
        ->set('data.hold_extension_minutes', 4)
        ->set('data.min_service_duration_minutes', 30)
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = SystemSetting::current();
    expect($setting->slot_granularity_minutes)->toBe(20);
    expect($setting->hold_duration_minutes)->toBe(8);
    expect($setting->hold_extension_minutes)->toBe(4);
    expect($setting->min_service_duration_minutes)->toBe(30);
});

it('non-admin cannot access the system settings page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(SystemSettings::canAccess())->toBeFalse();
});
