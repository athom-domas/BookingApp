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
