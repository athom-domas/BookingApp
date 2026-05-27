<?php

use App\Models\SalonProfile;
use Illuminate\Support\Facades\Storage;

it('creates a default row when none exists', function () {
    expect(SalonProfile::count())->toBe(0);

    $profile = SalonProfile::current();

    expect(SalonProfile::count())->toBe(1);
    expect($profile->id)->toBe(1);
    expect($profile->name)->toBe('Il mio salone');
    expect($profile->primary_color)->toBe('#1d1d1d');
    expect($profile->logo_path)->toBeNull();
});

it('returns the existing row without creating a new one on repeated calls', function () {
    SalonProfile::current();
    SalonProfile::current();

    expect(SalonProfile::count())->toBe(1);
});

it('logoUrl returns null when logo_path is null', function () {
    $profile = SalonProfile::current();

    expect($profile->logoUrl())->toBeNull();
});

it('logoUrl returns public storage url when logo_path is set', function () {
    Storage::fake('public');
    $profile = SalonProfile::current();
    $profile->update(['logo_path' => 'salon-logo/test.png']);

    expect($profile->fresh()->logoUrl())->toContain('salon-logo/test.png');
});
