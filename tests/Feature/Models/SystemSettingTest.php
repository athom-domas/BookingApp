<?php

use App\Models\SystemSetting;

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
