<?php

use App\Models\Business;
use App\Models\BusinessPageBlock;

it('BusinessPageBlock stores and retrieves content as array', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->create([
        'business_id' => $business->id,
        'block_type'  => 'hero',
        'variant'     => 'classic',
        'is_enabled'  => true,
        'sort_order'  => 1,
        'content'     => ['title' => 'Test Salon'],
    ]);

    $fresh = $block->fresh();
    expect($fresh->block_type)->toBe('hero');
    expect($fresh->content)->toBeArray();
    expect($fresh->content['title'])->toBe('Test Salon');
    expect($fresh->is_enabled)->toBeTrue();
});

it('required block flag persists', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->create([
        'business_id' => $business->id,
        'is_required' => true,
        'is_enabled'  => true,
    ]);
    expect($block->fresh()->is_required)->toBeTrue();
});

it('BusinessPageBlock global scope filters by current_business_id', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    app()->instance('current_business_id', $b1->id);
    BusinessPageBlock::factory()->create(['business_id' => $b1->id]);
    BusinessPageBlock::factory()->create(['business_id' => $b2->id]);

    $blocks = BusinessPageBlock::all();
    expect($blocks)->toHaveCount(1);
    expect($blocks->first()->business_id)->toBe($b1->id);
});

it('blocks ordered by sort_order', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    BusinessPageBlock::factory()->create(['business_id' => $business->id, 'sort_order' => 30]);
    BusinessPageBlock::factory()->create(['business_id' => $business->id, 'sort_order' => 10]);
    BusinessPageBlock::factory()->create(['business_id' => $business->id, 'sort_order' => 20]);

    $blocks = BusinessPageBlock::orderBy('sort_order')->get();
    expect($blocks->pluck('sort_order')->toArray())->toBe([10, 20, 30]);
});
