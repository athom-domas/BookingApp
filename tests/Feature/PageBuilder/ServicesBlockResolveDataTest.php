<?php

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\PageBlocks\ServicesBlock;

function makeServicesBlock(Business $business): BusinessPageBlock
{
    return BusinessPageBlock::factory()->make([
        'block_type' => 'services',
        'variant'    => 'grid_cards',
        'business_id' => $business->id,
    ]);
}

it('returns single null-category group when no categories exist', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Service::factory()->create(['business_id' => $business->id, 'active' => true]);

    $data = ServicesBlock::resolveData($business, makeServicesBlock($business));

    expect($data)->toHaveKeys(['services', 'grouped_services'])
        ->and($data['grouped_services'])->toHaveCount(1)
        ->and($data['grouped_services'][0]['category'])->toBeNull()
        ->and($data['grouped_services'][0]['services'])->toHaveCount(1);
});

it('groups services by active category', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $cat = ServiceCategory::factory()->create(['business_id' => $business->id, 'is_active' => true]);
    Service::factory()->create(['business_id' => $business->id, 'active' => true, 'service_category_id' => $cat->id]);
    Service::factory()->create(['business_id' => $business->id, 'active' => true, 'service_category_id' => $cat->id]);

    $data = ServicesBlock::resolveData($business, makeServicesBlock($business));

    expect($data['grouped_services'])->toHaveCount(1)
        ->and($data['grouped_services'][0]['category']->id)->toBe($cat->id)
        ->and($data['grouped_services'][0]['services'])->toHaveCount(2);
});

it('places uncategorized services in Altri group when categories also exist', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $cat = ServiceCategory::factory()->create(['business_id' => $business->id, 'is_active' => true]);
    Service::factory()->create(['business_id' => $business->id, 'active' => true, 'service_category_id' => $cat->id]);
    Service::factory()->create(['business_id' => $business->id, 'active' => true, 'service_category_id' => null]);

    $data = ServicesBlock::resolveData($business, makeServicesBlock($business));

    expect($data['grouped_services'])->toHaveCount(2);
    $last = end($data['grouped_services']);
    expect($last['category'])->toBeNull()
        ->and($last['services'])->toHaveCount(1);
});

it('excludes inactive categories', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $inactive = ServiceCategory::factory()->create(['business_id' => $business->id, 'is_active' => false]);
    Service::factory()->create(['business_id' => $business->id, 'active' => true, 'service_category_id' => $inactive->id]);

    $data = ServicesBlock::resolveData($business, makeServicesBlock($business));

    expect($data['grouped_services'])->toHaveCount(1)
        ->and($data['grouped_services'][0]['category'])->toBeNull();
});

it('excludes categories with no active services', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $cat = ServiceCategory::factory()->create(['business_id' => $business->id, 'is_active' => true]);
    Service::factory()->create(['business_id' => $business->id, 'active' => false, 'service_category_id' => $cat->id]);

    $data = ServicesBlock::resolveData($business, makeServicesBlock($business));

    expect($data['grouped_services'])->toHaveCount(1)
        ->and($data['grouped_services'][0]['category'])->toBeNull()
        ->and($data['grouped_services'][0]['services'])->toHaveCount(0);
});
