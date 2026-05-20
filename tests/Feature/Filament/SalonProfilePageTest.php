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
        'name'          => 'Salone Test',
        'primary_color' => '#ff0000',
        'phone'         => '+39 02 111111',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->assertSet('data.name', 'Salone Test')
        ->assertSet('data.primary_color', '#ff0000')
        ->assertSet('data.phone', '+39 02 111111');
});

it('admin can update the salon profile', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->set('data.name', 'Nuovo Nome')
        ->set('data.primary_color', '#123456')
        ->set('data.phone', '+39 02 999999')
        ->set('data.address', 'Via Test 1')
        ->set('data.website', 'https://test.it')
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = SalonProfile::current();
    expect($profile->name)->toBe('Nuovo Nome');
    expect($profile->primary_color)->toBe('#123456');
    expect($profile->phone)->toBe('+39 02 999999');
    expect($profile->address)->toBe('Via Test 1');
    expect($profile->website)->toBe('https://test.it');
});

it('non-admin cannot access the salon profile page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(SalonProfilePage::canAccess())->toBeFalse();
});
