<?php

use App\Models\PlanFeature;

it('seeds all six features in the migration', function () {
    $keys = PlanFeature::pluck('key')->sort()->values()->all();
    expect($keys)->toBe([
        'google_calendar',
        'loyalty_program',
        'online_payments',
        'waitlist',
        'whatsapp_ai',
        'whatsapp_notifications',
    ]);
});

it('whatsapp_ai is seeded as plus', function () {
    expect(PlanFeature::where('key', 'whatsapp_ai')->value('min_plan'))->toBe('plus');
});

it('whatsapp_notifications is seeded as plus', function () {
    expect(PlanFeature::where('key', 'whatsapp_notifications')->value('min_plan'))->toBe('plus');
});

it('google_calendar is seeded as base', function () {
    expect(PlanFeature::where('key', 'google_calendar')->value('min_plan'))->toBe('base');
});

it('can update min_plan', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => 'plus']);
    expect(PlanFeature::where('key', 'waitlist')->value('min_plan'))->toBe('plus');
});

it('factory creates a valid PlanFeature', function () {
    $feature = PlanFeature::factory()->create(['key' => 'test_feature', 'min_plan' => 'base']);
    expect($feature->key)->toBe('test_feature');
    expect($feature->min_plan)->toBe('base');
});
