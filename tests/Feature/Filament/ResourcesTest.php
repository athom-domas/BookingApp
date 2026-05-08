<?php

use App\Filament\Resources\AppointmentResource;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('appointment list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(AppointmentResource::getUrl('index'))
        ->assertSuccessful();
});
