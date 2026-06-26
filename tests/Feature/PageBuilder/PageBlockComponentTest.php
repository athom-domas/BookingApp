<?php

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\PageBlocks\PageBlockRegistry;
use App\View\Components\PageBlock;

it('component resolves block class for known type', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->make(['block_type' => 'hero', 'variant' => 'classic']);

    $component = new PageBlock($business, $block);

    expect($component->blockClass)->toBe(PageBlockRegistry::find('hero'));
});

it('component returns null blockClass for unknown type', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->make(['block_type' => 'nonexistent', 'variant' => 'classic']);

    $component = new PageBlock($business, $block);

    expect($component->blockClass)->toBeNull();
});

it('component uses defaultVariant when variant is invalid', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->make(['block_type' => 'hero', 'variant' => 'bad_variant']);

    $component = new PageBlock($business, $block);

    expect($component->resolvedVariant)->toBe(PageBlockRegistry::find('hero')::defaultVariant());
});

it('component does not modify DB when variant is invalid', function () {
    $business = Business::factory()->create();
    $block = BusinessPageBlock::factory()->create(['block_type' => 'hero', 'variant' => 'bad_variant']);

    new PageBlock($business, $block);

    expect($block->fresh()->variant)->toBe('bad_variant');
});
