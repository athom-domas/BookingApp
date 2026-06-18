<?php

use App\Models\Service;
use App\Models\User;

it('has active scope', function () {
    $active   = Service::factory()->create(['active' => true]);
    $inactive = Service::factory()->create(['active' => false]);
    $ids = [$active->id, $inactive->id];

    expect(Service::whereIn('id', $ids)->active()->count())->toBe(1);
});

it('belongs to many staff users via service_staff', function () {
    $service = Service::factory()->create();
    $user = User::factory()->create();

    $service->staff()->attach($user->id);

    expect($service->staff)->toHaveCount(1);
    expect($service->staff->first()->id)->toBe($user->id);
});
