<?php

use App\Models\Service;
use App\Models\ServiceCategory;

it('has active scope', function () {
    $active   = ServiceCategory::factory()->create(['is_active' => true]);
    $inactive = ServiceCategory::factory()->create(['is_active' => false]);
    $ids = [$active->id, $inactive->id];

    expect(ServiceCategory::whereIn('id', $ids)->active()->count())->toBe(1);
});

it('has many services', function () {
    $category = ServiceCategory::factory()->create();
    $service  = Service::factory()->create(['service_category_id' => $category->id]);

    expect($category->services)->toHaveCount(1);
    expect($category->services->first()->id)->toBe($service->id);
});

it('sets service_category_id to null when category is deleted', function () {
    $category = ServiceCategory::factory()->create();
    $service  = Service::factory()->create(['service_category_id' => $category->id]);

    $category->delete();

    expect($service->fresh()->service_category_id)->toBeNull();
});
