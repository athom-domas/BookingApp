<?php

use App\Filament\Pages\SalonProfilePage;
use App\Models\SalonProfile;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('admin can view the salon profile page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->assertSuccessful();
});

it('form is pre-filled with current profile', function () {
    SalonProfile::current()->update([
        'name'  => 'Salone Test',
        'phone' => '+39 02 111111',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->assertSet('data.name', 'Salone Test')
        ->assertSet('data.phone', '+39 02 111111');
});

it('admin can update the salon profile', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->set('data.name', 'Nuovo Nome')
        ->set('data.phone', '+39 02 999999')
        ->set('data.address', 'Via Test 1')
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = SalonProfile::current();
    expect($profile->name)->toBe('Nuovo Nome');
    expect($profile->phone)->toBe('+39 02 999999');
    expect($profile->address)->toBe('Via Test 1');
});

it('non-admin cannot access the salon profile page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(SalonProfilePage::canAccess())->toBeFalse();
});
