<?php

use App\Enums\BusinessStatus;
use App\Models\Business;
use Filament\Panel;

it('throws RuntimeException when no business context is bound', function () {
    unset(app()['current_business_id']);
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

it('User::getTenants() returns collection with its business', function () {
    $business = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => $business->id]);

    $tenants = $user->getTenants(Mockery::mock(Panel::class));

    expect($tenants)->toHaveCount(1);
    expect($tenants->first()->id)->toBe($business->id);
});

it('User::getTenants() returns empty collection for super_admin', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create(['business_id' => null]);
    $user->assignRole('super_admin');

    expect($user->getTenants(Mockery::mock(Panel::class)))->toHaveCount(0);
});

it('User::canAccessTenant() returns false for super_admin', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $business = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => null]);
    $user->assignRole('super_admin');

    expect($user->canAccessTenant($business))->toBeFalse();
});

it('User::canAccessTenant() returns true for matching business', function () {
    $business = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => $business->id]);

    expect($user->canAccessTenant($business))->toBeTrue();
});

it('User::canAccessTenant() returns false for wrong business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => $b1->id]);

    expect($user->canAccessTenant($b2))->toBeFalse();
});

it('SystemSetting::current() creates a record for current business', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $setting = \App\Models\SystemSetting::current();

    expect($setting->business_id)->toBe($business->id);
    expect($setting->slot_granularity_minutes)->toBe(15);
});

it('SystemSetting::current() returns same record on subsequent calls', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $first  = \App\Models\SystemSetting::current();
    $second = \App\Models\SystemSetting::current();

    expect($first->id)->toBe($second->id);
});

it('SystemSetting::current() creates separate records per business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    app()->instance('current_business_id', $b1->id);
    $s1 = \App\Models\SystemSetting::current();

    app()->instance('current_business_id', $b2->id);
    $s2 = \App\Models\SystemSetting::current();

    expect($s1->id)->not->toBe($s2->id);
    expect($s1->business_id)->toBe($b1->id);
    expect($s2->business_id)->toBe($b2->id);
});
