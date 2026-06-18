<?php

use App\Models\SalonReview;

it('has published scope', function () {
    $pub   = SalonReview::factory()->create(['is_published' => true]);
    $unpub = SalonReview::factory()->create(['is_published' => false]);
    $ids = [$pub->id, $unpub->id];

    expect(SalonReview::whereIn('id', $ids)->published()->count())->toBe(1);
});

it('has ordered scope', function () {
    SalonReview::factory()->create(['sort_order' => 2]);
    SalonReview::factory()->create(['sort_order' => 1]);

    $reviews = SalonReview::ordered()->get();
    expect($reviews->first()->sort_order)->toBe(1);
});

it('combines published and ordered scopes', function () {
    $a = SalonReview::factory()->create(['is_published' => true,  'sort_order' => 2]);
    $b = SalonReview::factory()->create(['is_published' => true,  'sort_order' => 1]);
    $c = SalonReview::factory()->create(['is_published' => false, 'sort_order' => 0]);
    $ids = [$a->id, $b->id, $c->id];

    $reviews = SalonReview::whereIn('id', $ids)->published()->ordered()->get();
    expect($reviews)->toHaveCount(2)
        ->and($reviews->first()->sort_order)->toBe(1);
});
