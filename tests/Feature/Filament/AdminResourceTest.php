<?php

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\AdminResource\Pages\EditAdmin;
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

it('admin list shows only admin users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create(['email' => 'staff@test.com']);
    $staff->assignRole('staff');

    $customer = User::factory()->create(['email' => 'customer@test.com']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    $this->get(AdminResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($admin->email)
        ->assertDontSee('staff@test.com')
        ->assertDontSee('customer@test.com');
});

it('non-admin cannot access admin resource', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff);

    $this->get(AdminResource::getUrl('index'))
        ->assertForbidden();
});
