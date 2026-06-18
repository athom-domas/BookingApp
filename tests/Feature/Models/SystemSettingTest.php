<?php

use App\Models\Business;
use App\Models\SystemSetting;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
});

it('creates a default row with slot_generation_weeks = 4 when none exists', function () {
    expect(SystemSetting::count())->toBe(0);

    $setting = SystemSetting::current();

    expect(SystemSetting::count())->toBe(1);
    expect($setting->slot_generation_weeks)->toBe(4);
});

it('returns the existing row without creating a new one on repeated calls', function () {
    SystemSetting::current();
    SystemSetting::current();

    expect(SystemSetting::count())->toBe(1);
});

it('always returns a row with a valid id', function () {
    $setting = SystemSetting::current();

    expect($setting->id)->toBeInt()->toBeGreaterThan(0);
});

it('casts slot_generation_weeks to integer', function () {
    $setting = SystemSetting::current();
    $setting->update(['slot_generation_weeks' => '8']);

    expect(SystemSetting::current()->slot_generation_weeks)->toBe(8);
});

it('isReviewsEnabled returns true by default', function () {
    expect(SystemSetting::isReviewsEnabled())->toBeTrue();
});

it('isReviewsEnabled returns false when disabled', function () {
    SystemSetting::current()->update(['reviews_enabled' => false]);

    expect(SystemSetting::isReviewsEnabled())->toBeFalse();
});

it('isReviewsEnabled returns true when re-enabled', function () {
    SystemSetting::current()->update(['reviews_enabled' => false]);
    SystemSetting::current()->update(['reviews_enabled' => true]);

    expect(SystemSetting::isReviewsEnabled())->toBeTrue();
});
