<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('booking page passes empty categories when none exist', function () {
    Service::factory()->create(['active' => true]);

    $response = $this->get('/prenota');

    $response->assertSuccessful();
    $response->assertViewHas('categories', fn ($cats) => $cats->isEmpty());
});

it('booking page passes only active categories with active services', function () {
    $activeCategory   = ServiceCategory::factory()->create(['is_active' => true]);
    $inactiveCategory = ServiceCategory::factory()->create(['is_active' => false]);

    Service::factory()->create(['active' => true, 'service_category_id' => $activeCategory->id]);
    Service::factory()->create(['active' => true, 'service_category_id' => $inactiveCategory->id]);

    $response = $this->get('/prenota');

    $response->assertSuccessful();
    $response->assertViewHas('categories', function ($cats) use ($activeCategory, $inactiveCategory) {
        return $cats->contains('id', $activeCategory->id)
            && ! $cats->contains('id', $inactiveCategory->id);
    });
});

it('booking page excludes categories with no active services', function () {
    $categoryWithActive   = ServiceCategory::factory()->create(['is_active' => true]);
    $categoryWithInactive = ServiceCategory::factory()->create(['is_active' => true]);

    Service::factory()->create(['active' => true,  'service_category_id' => $categoryWithActive->id]);
    Service::factory()->create(['active' => false, 'service_category_id' => $categoryWithInactive->id]);

    $response = $this->get('/prenota');

    $response->assertSuccessful();
    $response->assertViewHas('categories', function ($cats) use ($categoryWithActive, $categoryWithInactive) {
        return $cats->contains('id', $categoryWithActive->id)
            && ! $cats->contains('id', $categoryWithInactive->id);
    });
});
