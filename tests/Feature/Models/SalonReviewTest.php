<?php

use App\Models\SalonReview;

it('has published scope', function () {
    SalonReview::factory()->create(['is_published' => true]);
    SalonReview::factory()->create(['is_published' => false]);

    expect(SalonReview::published()->count())->toBe(1);
});

it('has ordered scope', function () {
    SalonReview::factory()->create(['sort_order' => 2]);
    SalonReview::factory()->create(['sort_order' => 1]);

    $reviews = SalonReview::ordered()->get();
    expect($reviews->first()->sort_order)->toBe(1);
});

it('combines published and ordered scopes', function () {
    SalonReview::factory()->create(['is_published' => true,  'sort_order' => 2]);
    SalonReview::factory()->create(['is_published' => true,  'sort_order' => 1]);
    SalonReview::factory()->create(['is_published' => false, 'sort_order' => 0]);

    $reviews = SalonReview::published()->ordered()->get();
    expect($reviews)->toHaveCount(2)
        ->and($reviews->first()->sort_order)->toBe(1);
});
