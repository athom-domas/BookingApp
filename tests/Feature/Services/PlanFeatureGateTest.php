<?php

use App\Models\Business;
use App\Models\PlanFeature;
use App\Services\PlanFeatureGate;
use Illuminate\Support\Facades\Cache;

it('allows base feature for base-plan business', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'base']);
    $business = Business::factory()->create(['trial_ends_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'google_calendar'))->toBeTrue();
});

it('allows base feature for plus-plan business', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'base']);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'google_calendar'))->toBeTrue();
});

it('denies plus feature for base-plan business', function () {
    PlanFeature::where('key', 'whatsapp_ai')->update(['min_plan' => 'plus']);
    $business = Business::factory()->create(['trial_ends_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'whatsapp_ai'))->toBeFalse();
});

it('allows plus feature for plus-plan business', function () {
    PlanFeature::where('key', 'whatsapp_ai')->update(['min_plan' => 'plus']);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'whatsapp_ai'))->toBeTrue();
});

it('allows plus feature during trial', function () {
    PlanFeature::where('key', 'whatsapp_ai')->update(['min_plan' => 'plus']);
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);

    expect(app(PlanFeatureGate::class)->allows($business, 'whatsapp_ai'))->toBeTrue();
});

it('denies disabled feature (null min_plan)', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => null]);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'waitlist'))->toBeFalse();
});

it('denies unknown feature', function () {
    $business = Business::factory()->create();
    expect(app(PlanFeatureGate::class)->allows($business, 'nonexistent'))->toBeFalse();
});

it('denies feature with invalid min_plan value in DB', function () {
    PlanFeature::factory()->create(['key' => 'bad_feature', 'min_plan' => 'premium']);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'bad_feature'))->toBeFalse();
});

it('caches the result and does not re-query on second call', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'base']);
    $business = Business::factory()->create(['trial_ends_at' => null]);
    $gate     = app(PlanFeatureGate::class);

    $gate->allows($business, 'google_calendar'); // warm cache
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'plus']); // change in DB

    // still reads cached 'base' → returns true for base-plan business
    expect($gate->allows($business, 'google_calendar'))->toBeTrue();
});

it('reflects DB change after model save flushes cache automatically', function () {
    $feature  = PlanFeature::where('key', 'google_calendar')->first();
    $business = Business::factory()->create(['trial_ends_at' => null]);
    $gate     = app(PlanFeatureGate::class);

    $gate->allows($business, 'google_calendar'); // warm cache with 'base'

    $feature->update(['min_plan' => 'plus']); // model saved() hook flushes cache

    expect($gate->allows($business, 'google_calendar'))->toBeFalse();
});

it('caches disabled/null features and does not re-query', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => null]);
    $business = Business::factory()->create();
    $gate     = app(PlanFeatureGate::class);

    $gate->allows($business, 'waitlist'); // caches as __disabled__
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => 'base']); // change in DB

    // cache still returns false (not re-queried)
    expect($gate->allows($business, 'waitlist'))->toBeFalse();
});
