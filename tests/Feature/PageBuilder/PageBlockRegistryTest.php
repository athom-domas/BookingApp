<?php

use App\PageBlocks\PageBlockRegistry;

it('registry returns all registered block types', function () {
    $all = PageBlockRegistry::all();
    expect($all)->toHaveKeys([
        'hero', 'about', 'services', 'staff', 'gallery',
        'reviews', 'contact_info', 'map', 'cta', 'faq',
    ]);
});

it('find returns block class for known type', function () {
    $class = PageBlockRegistry::find('hero');
    expect($class)->not->toBeNull();
    expect(class_exists($class))->toBeTrue();
});

it('find returns null for unknown type', function () {
    expect(PageBlockRegistry::find('nonexistent'))->toBeNull();
});

it('isValidVariant returns true for known variant', function () {
    expect(PageBlockRegistry::isValidVariant('hero', 'classic'))->toBeTrue();
});

it('isValidVariant returns false for unknown variant', function () {
    expect(PageBlockRegistry::isValidVariant('hero', 'nonexistent_variant'))->toBeFalse();
});

it('defaultVariant returns first variant key', function () {
    $default = PageBlockRegistry::defaultVariant('hero');
    $variants = PageBlockRegistry::find('hero')::variants();
    expect($default)->toBe(array_key_first($variants));
});

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\Service;
use App\Models\User;
use App\Models\SalonReview;
use App\PageBlocks\ServicesBlock;
use App\PageBlocks\StaffBlock;
use App\PageBlocks\ReviewsBlock;
use Spatie\Permission\Models\Role;

it('ServicesBlock resolveData returns active services for business', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    Service::factory()->create(['business_id' => $business->id, 'active' => true]);
    Service::factory()->create(['business_id' => $business->id, 'active' => false]);
    $block = BusinessPageBlock::factory()->make([
        'business_id' => $business->id,
        'block_type'  => 'services',
        'settings'    => ['featured_only' => false],
    ]);

    $data = ServicesBlock::resolveData($business, $block);

    expect($data['services'])->toHaveCount(1);
});

it('ReviewsBlock resolveData returns published reviews', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    SalonReview::factory()->create(['business_id' => $business->id, 'is_published' => true]);
    SalonReview::factory()->create(['business_id' => $business->id, 'is_published' => false]);
    $block = BusinessPageBlock::factory()->make(['business_id' => $business->id, 'block_type' => 'reviews']);

    $data = ReviewsBlock::resolveData($business, $block);

    expect($data['reviews'])->toHaveCount(1);
});

it('StaffBlock resolveData returns staff users for business', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    $staff = User::factory()->create(['business_id' => $business->id]);
    $staff->assignRole('staff');
    $block = BusinessPageBlock::factory()->make(['business_id' => $business->id, 'block_type' => 'staff']);

    $data = StaffBlock::resolveData($business, $block);

    expect($data['staff'])->toHaveCount(1);
});
