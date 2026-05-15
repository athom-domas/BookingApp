<?php

use App\Filament\Resources\StaffResource\Pages\CreateStaff;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('admin can register staff from the admin panel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $service = Service::factory()->create(['active' => true]);

    $this->actingAs($admin);

    Livewire::test(CreateStaff::class)
        ->set('data.name', 'Nuovo Staff')
        ->set('data.email', 'nuovo.staff@test.com')
        ->set('data.password', 'password123')
        ->set('data.password_confirmation', 'password123')
        ->set('data.services', [$service->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::where('email', 'nuovo.staff@test.com')->first();

    expect($staff)->not->toBeNull();
    expect($staff->hasRole('staff'))->toBeTrue();
    expect(Hash::check('password123', $staff->password))->toBeTrue();
    expect($staff->services()->whereKey($service->id)->exists())->toBeTrue();
});

it('editing staff keeps the staff role and can update password', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $staff = User::factory()->create(['password' => 'password123']);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.name', 'Staff Aggiornato')
        ->set('data.email', $staff->email)
        ->set('data.password', 'new-password123')
        ->set('data.password_confirmation', 'new-password123')
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();

    expect($staff->name)->toBe('Staff Aggiornato');
    expect($staff->hasRole('staff'))->toBeTrue();
    expect(Hash::check('new-password123', $staff->password))->toBeTrue();
});

