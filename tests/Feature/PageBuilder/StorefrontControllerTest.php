<?php

use App\Http\Middleware\CheckStorefrontAccess;
use App\Http\Middleware\SubdomainMiddleware;
use App\Models\Business;
use App\Models\BusinessPageBlock;

beforeEach(function () {
    $this->withoutMiddleware([SubdomainMiddleware::class, CheckStorefrontAccess::class]);
});

it('renders welcome view when business has enabled blocks', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    BusinessPageBlock::factory()->create([
        'business_id' => $business->id,
        'block_type'  => 'hero',
        'variant'     => 'classic',
        'is_enabled'  => true,
        'sort_order'  => 1,
    ]);

    $this->get('/')->assertOk()->assertViewIs('welcome');
});

it('falls back to welcome-legacy when business has no blocks', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $this->get('/')->assertOk()->assertViewIs('welcome-legacy');
});

it('renders welcome with empty blocks when all blocks disabled', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    BusinessPageBlock::factory()->create([
        'business_id' => $business->id,
        'block_type'  => 'hero',
        'variant'     => 'classic',
        'is_enabled'  => false,
        'sort_order'  => 1,
    ]);

    $response = $this->get('/');

    $response->assertOk()->assertViewIs('welcome');
    expect($response->viewData('blocks'))->toHaveCount(0);
});
