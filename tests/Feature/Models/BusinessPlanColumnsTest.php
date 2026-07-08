<?php

use App\Models\Business;
use Illuminate\Support\Facades\Schema;

it('businesses table has plan columns after migration', function () {
    expect(Schema::hasColumn('businesses', 'plan'))->toBeTrue();
    expect(Schema::hasColumn('businesses', 'plan_override'))->toBeTrue();
    expect(Schema::hasColumn('businesses', 'plan_override_expires_at'))->toBeTrue();
    expect(Schema::hasColumn('businesses', 'plan_override_reason'))->toBeTrue();
});

it('new business defaults to base plan', function () {
    $business = Business::factory()->create();

    expect($business->plan)->toBe('base');
    expect($business->plan_override)->toBeNull();
});
