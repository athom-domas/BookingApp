<?php

use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BusinessProvisioningService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff', 'customer', 'super_admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

it('provisions admin user with correct business and role', function () {
    $business = Business::factory()->create();

    $admin = (new BusinessProvisioningService())->provision($business, 'owner@example.com');

    expect($admin->email)->toBe('owner@example.com');
    expect($admin->business_id)->toBe($business->id);
    expect($admin->hasRole('admin'))->toBeTrue();
    expect($admin->must_change_password)->toBeTrue();
    expect(isset($admin->plainPassword))->toBeTrue();
});

it('provisions default SystemSetting for the business', function () {
    $business = Business::factory()->create();

    (new BusinessProvisioningService())->provision($business, 'owner@example.com');

    $setting = SystemSetting::withoutGlobalScopes()
        ->where('business_id', $business->id)->first();

    expect($setting)->not->toBeNull();
    expect($setting->slot_granularity_minutes)->toBe(15);
    expect($setting->payment_mode)->toBe('both');
});

it('provisions SalonProfile, IntegrationSetting, and 3 sample services', function () {
    $business = Business::factory()->create(['name' => 'Salone Test']);

    (new BusinessProvisioningService())->provision($business, 'owner@example.com');

    $profile = SalonProfile::withoutGlobalScopes()->where('business_id', $business->id)->first();
    expect($profile->name)->toBe('Salone Test');

    $integration = IntegrationSetting::withoutGlobalScopes()->where('business_id', $business->id)->first();
    expect($integration)->not->toBeNull();

    $count = Service::withoutGlobalScopes()->where('business_id', $business->id)->count();
    expect($count)->toBe(3);
});

it('rolls back completely on failure', function () {
    // Create a user with the same email to trigger a unique constraint violation
    User::factory()->create(['email' => 'conflict@example.com']);

    $business = Business::factory()->create();

    expect(fn() => (new BusinessProvisioningService())->provision($business, 'conflict@example.com'))
        ->toThrow(\Exception::class);

    expect(SystemSetting::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(0);
    expect(SalonProfile::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(0);
});
