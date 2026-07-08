<?php

use App\Models\Business;
use App\Services\PlanFeatureGate;
use Tests\TestCase;

uses(TestCase::class);

// --- effectivePlan() ---

it('returns plus during active trial', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->addDay()]);

    expect($business->effectivePlan())->toBe('plus');
});

it('returns base with expired trial and no subscription', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->subDay()]);

    expect($business->effectivePlan())->toBe('base');
});

it('returns base with no trial and no subscription', function () {
    $business = Business::factory()->make(['trial_ends_at' => null]);

    expect($business->effectivePlan())->toBe('base');
});

it('returns plus for active plus subscription', function () {
    $business = Mockery::mock(Business::class)->makePartial();
    $business->plan_override            = null;
    $business->trial_ends_at            = now()->subDay();
    $business->shouldReceive('onGenericTrial')->andReturn(false);
    $business->shouldReceive('subscribed')->with('default')->andReturn(true);
    $business->shouldReceive('hasIncompletePayment')->with('default')->andReturn(false);
    $business->shouldReceive('subscribedToPrice')
        ->with(config('plans.plus.price_id'), 'default')
        ->andReturn(true);

    expect($business->effectivePlan())->toBe('plus');
});

it('returns base for active base subscription', function () {
    $business = Mockery::mock(Business::class)->makePartial();
    $business->plan_override            = null;
    $business->trial_ends_at            = now()->subDay();
    $business->shouldReceive('onGenericTrial')->andReturn(false);
    $business->shouldReceive('subscribed')->with('default')->andReturn(true);
    $business->shouldReceive('hasIncompletePayment')->with('default')->andReturn(false);
    $business->shouldReceive('subscribedToPrice')
        ->with(config('plans.plus.price_id'), 'default')
        ->andReturn(false);

    expect($business->effectivePlan())->toBe('base');
});

it('returns base when subscription has incomplete payment', function () {
    $business = Mockery::mock(Business::class)->makePartial();
    $business->plan_override            = null;
    $business->trial_ends_at            = now()->subDay();
    $business->shouldReceive('onGenericTrial')->andReturn(false);
    $business->shouldReceive('subscribed')->with('default')->andReturn(true);
    $business->shouldReceive('hasIncompletePayment')->with('default')->andReturn(true);

    expect($business->effectivePlan())->toBe('base');
});

it('returns override plan when active plan override is set', function () {
    $business = Business::factory()->make([
        'trial_ends_at'           => null,
        'plan_override'           => 'plus',
        'plan_override_expires_at' => null,
    ]);

    expect($business->effectivePlan())->toBe('plus');
});

it('ignores expired plan override and falls through to subscription check', function () {
    $business = Business::factory()->make([
        'trial_ends_at'           => null,
        'plan_override'           => 'plus',
        'plan_override_expires_at' => now()->subDay(),
    ]);

    // No subscription → base
    expect($business->effectivePlan())->toBe('base');
});

// --- canUseFeature() ---

it('trial business can use whatsapp_ai', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->addDay()]);

    expect($business->canUseFeature('whatsapp_ai'))->toBeTrue();
});

it('base-plan business cannot use whatsapp_ai', function () {
    $business = Business::factory()->make(['trial_ends_at' => null]);

    expect($business->canUseFeature('whatsapp_ai'))->toBeFalse();
});

it('unknown feature is denied', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->addDay()]);

    expect($business->canUseFeature('nonexistent_feature'))->toBeFalse();
});

it('plus override business can use whatsapp_ai', function () {
    $business = Business::factory()->make([
        'trial_ends_at'           => null,
        'plan_override'           => 'plus',
        'plan_override_expires_at' => null,
    ]);

    expect($business->canUseFeature('whatsapp_ai'))->toBeTrue();
});
