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
