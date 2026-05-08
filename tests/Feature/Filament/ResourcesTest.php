<?php

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\AvailabilityRuleResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\TimeSlotResource;
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

it('service list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(ServiceResource::getUrl('index'))
        ->assertSuccessful();
});

it('availability rule list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(AvailabilityRuleResource::getUrl('index'))
        ->assertSuccessful();
});

it('time slot list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(TimeSlotResource::getUrl('index'))
        ->assertSuccessful();
});

it('payment list page renders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(PaymentResource::getUrl('index'))
        ->assertSuccessful();
});
