<?php

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Database\Seeders\PageBuilderSeeder;

beforeEach(function () {
    $this->seed(PageBuilderSeeder::class);
});

it('page-builder:init creates blocks for uninitialized businesses', function () {
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBeGreaterThan(0);
});

it('page-builder:init skips already initialized businesses', function () {
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);
    BusinessPageBlock::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(1);
});

it('page-builder:init --business targets a single business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $b1->id]);
    SalonProfile::factory()->create(['business_id' => $b2->id]);

    $this->artisan("page-builder:init --business={$b1->id}")->assertSuccessful();

    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $b1->id)->count())->toBeGreaterThan(0);
    expect(BusinessPageBlock::withoutGlobalScopes()->where('business_id', $b2->id)->count())->toBe(0);
});

it('page-builder:init creates hero and contact_info as required', function () {
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    $required = BusinessPageBlock::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->where('is_required', true)
        ->pluck('block_type')
        ->toArray();

    expect($required)->toContain('hero');
    expect($required)->toContain('contact_info');
});

it('page-builder:init creates all expected block types', function () {
    $business = Business::factory()->create();
    SalonProfile::factory()->create(['business_id' => $business->id]);

    $this->artisan('page-builder:init')->assertSuccessful();

    $types = BusinessPageBlock::withoutGlobalScopes()
        ->where('business_id', $business->id)
        ->pluck('block_type')
        ->toArray();

    foreach (['hero', 'services', 'about', 'contact_info'] as $type) {
        expect($types)->toContain($type);
    }
});
