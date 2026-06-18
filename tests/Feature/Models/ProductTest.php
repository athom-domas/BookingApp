<?php

use App\Models\Product;

it('has active scope', function () {
    $active   = Product::factory()->create(['active' => true]);
    $inactive = Product::factory()->create(['active' => false]);
    $ids = [$active->id, $inactive->id];

    expect(Product::whereIn('id', $ids)->active()->count())->toBe(1);
});

it('has inSale scope returning only active and in_sale products', function () {
    $a = Product::factory()->create(['in_sale' => true,  'active' => true]);
    $b = Product::factory()->create(['in_sale' => true,  'active' => false]);
    $c = Product::factory()->create(['in_sale' => false, 'active' => true]);
    $ids = [$a->id, $b->id, $c->id];

    expect(Product::whereIn('id', $ids)->inSale()->count())->toBe(1);
});

it('has belowThreshold scope', function () {
    Product::factory()->create(['stock' => 5, 'low_stock_threshold' => 10]);
    Product::factory()->create(['stock' => 15, 'low_stock_threshold' => 10]);
    Product::factory()->create(['stock' => 5, 'low_stock_threshold' => null]);

    expect(Product::belowThreshold()->count())->toBe(1);
});

it('isAvailable returns true only when active, in_sale and stock > 0', function () {
    $available = Product::factory()->make(['active' => true, 'in_sale' => true, 'stock' => 1]);
    $noStock   = Product::factory()->make(['active' => true, 'in_sale' => true, 'stock' => 0]);
    $inactive  = Product::factory()->make(['active' => false, 'in_sale' => true, 'stock' => 5]);

    expect($available->isAvailable())->toBeTrue();
    expect($noStock->isAvailable())->toBeFalse();
    expect($inactive->isAvailable())->toBeFalse();
});

it('isBelowThreshold returns true when stock is at or below threshold', function () {
    $below     = Product::factory()->make(['stock' => 3, 'low_stock_threshold' => 5]);
    $atLimit   = Product::factory()->make(['stock' => 5, 'low_stock_threshold' => 5]);
    $above     = Product::factory()->make(['stock' => 6, 'low_stock_threshold' => 5]);
    $noThresh  = Product::factory()->make(['stock' => 1, 'low_stock_threshold' => null]);

    expect($below->isBelowThreshold())->toBeTrue();
    expect($atLimit->isBelowThreshold())->toBeTrue();
    expect($above->isBelowThreshold())->toBeFalse();
    expect($noThresh->isBelowThreshold())->toBeFalse();
});
