<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('business_user pivot table exists and accepts multiple rows per user', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);

    DB::table('business_user')->insert([
        ['business_id' => $b1->id, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => $b2->id, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(DB::table('business_user')->where('user_id', $admin->id)->count())->toBe(2);
});

it('backfill query inserts admin users with business_id into business_user pivot', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $business->id]);
    $admin->assignRole('admin');

    $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

    DB::table('users')
        ->whereNotNull('business_id')
        ->whereIn('id', function ($sub) {
            $sub->select('model_id')
                ->from('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('role_id', function ($q) {
                    $q->select('id')->from('roles')
                      ->where('name', 'admin')
                      ->where('guard_name', 'web');
                });
        })
        ->select('id', 'business_id')
        ->get()
        ->each(fn ($u) => DB::table('business_user')->insertOrIgnore([
            'business_id' => $u->business_id,
            'user_id'     => $u->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]));

    expect(DB::table('business_user')->where('user_id', $admin->id)->where('business_id', $business->id)->exists())->toBeTrue();
});

it('backfill query does not insert staff users into business_user pivot', function () {
    $business = Business::factory()->create();
    $staff = User::factory()->create(['business_id' => $business->id]);
    $staff->assignRole('staff');

    DB::table('users')
        ->whereNotNull('business_id')
        ->whereIn('id', function ($sub) {
            $sub->select('model_id')
                ->from('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('role_id', function ($q) {
                    $q->select('id')->from('roles')
                      ->where('name', 'admin')
                      ->where('guard_name', 'web');
                });
        })
        ->select('id', 'business_id')
        ->get()
        ->each(fn ($u) => DB::table('business_user')->insertOrIgnore([
            'business_id' => $u->business_id,
            'user_id'     => $u->id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]));

    expect(DB::table('business_user')->where('user_id', $staff->id)->exists())->toBeFalse();
});

it('User::businesses() returns all businesses from pivot', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);

    $admin->businesses()->attach([$b1->id, $b2->id]);

    $businesses = $admin->fresh()->businesses;
    expect($businesses)->toHaveCount(2);
    expect($businesses->pluck('id')->toArray())->toContain($b1->id, $b2->id);
});

it('Business::admins() returns users from pivot', function () {
    $b1    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);

    $admin->businesses()->attach($b1->id);

    expect($b1->fresh()->admins)->toHaveCount(1);
    expect($b1->fresh()->admins->first()->id)->toBe($admin->id);
});

it('canAccessTenant returns true for admin linked to business via pivot', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach([$b1->id, $b2->id]);

    expect($admin->canAccessTenant($b1))->toBeTrue();
    expect($admin->canAccessTenant($b2))->toBeTrue();
});

it('canAccessTenant returns false for admin not linked to business', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($b1->id);

    expect($admin->canAccessTenant($b2))->toBeFalse();
});

it('canAccessTenant uses business_id for staff (not pivot)', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $staff = User::factory()->create(['business_id' => $b1->id]);
    $staff->assignRole('staff');

    expect($staff->canAccessTenant($b1))->toBeTrue();
    expect($staff->canAccessTenant($b2))->toBeFalse();
});
