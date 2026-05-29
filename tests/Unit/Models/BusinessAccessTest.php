<?php

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('hasAccess returns true when trial is active', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->addDays(5)]);
    expect($business->hasAccess())->toBeTrue();
});

test('hasAccess returns false when trial expired and no subscription', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->subDay()]);
    expect($business->hasAccess())->toBeFalse();
});

test('hasAccess returns false when trial_ends_at is null', function () {
    $business = Business::factory()->make(['trial_ends_at' => null]);
    expect($business->hasAccess())->toBeFalse();
});

test('subscriptionStatus returns trial when on generic trial', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->addDays(5)]);
    expect($business->subscriptionStatus())->toBe('trial');
});

test('subscriptionStatus returns expired when trial ended and no subscription', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->subDay()]);
    expect($business->subscriptionStatus())->toBe('expired');
});

test('factory sets trial_ends_at 14 days in future', function () {
    $business = Business::factory()->make();
    expect($business->trial_ends_at)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($business->trial_ends_at->isFuture())->toBeTrue();
    expect((int) round(abs($business->trial_ends_at->diffInDays(now()))))->toBe(14);
});
