<?php

use App\Enums\BusinessStatus;
use App\Models\Business;

it('throws RuntimeException when no business context is bound', function () {
    expect(fn() => Business::currentId())->toThrow(\RuntimeException::class, 'No current business context bound.');
});

it('returns the bound business id', function () {
    app()->instance('current_business_id', 42);
    expect(Business::currentId())->toBe(42);
});

it('has active status after creation', function () {
    $business = Business::factory()->create(['status' => BusinessStatus::Active]);
    expect($business->status)->toBe(BusinessStatus::Active);
    expect($business->status->value)->toBe('active');
});

it('BelongsToBusiness global scope filters records by current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    \App\Models\SalonReview::factory()->create(['business_id' => $b1->id]);
    \App\Models\SalonReview::factory()->create(['business_id' => $b2->id]);

    app()->instance('current_business_id', $b1->id);
    expect(\App\Models\SalonReview::count())->toBe(1);

    app()->instance('current_business_id', $b2->id);
    expect(\App\Models\SalonReview::count())->toBe(1);
})->skip('Requires business_id migration (Task 3) and BelongsToBusiness on SalonReview (Task 5)');

it('BelongsToBusiness auto-fills business_id on create', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $review = \App\Models\SalonReview::create([
        'author_name' => 'Test',
        'rating'      => 5,
        'body'        => 'Great!',
        'is_published' => true,
        'sort_order'  => 1,
    ]);

    expect($review->business_id)->toBe($business->id);
})->skip('Requires business_id migration (Task 3) and BelongsToBusiness on SalonReview (Task 5)');
