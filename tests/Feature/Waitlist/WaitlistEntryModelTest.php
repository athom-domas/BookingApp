<?php

use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('casts service_ids and preferred_days to array', function () {
    $s1 = Service::factory()->create();
    $s2 = Service::factory()->create();
    $entry = WaitlistEntry::factory()->create([
        'service_ids'    => [$s1->id, $s2->id],
        'preferred_days' => ['monday', 'friday'],
    ]);

    expect($entry->service_ids)->toBeArray()->toEqual([$s1->id, $s2->id])
        ->and($entry->preferred_days)->toBeArray()->toEqual(['monday', 'friday']);
});

it('casts preferred_date_from and preferred_date_to to Carbon', function () {
    $entry = WaitlistEntry::factory()->create([
        'preferred_date_from' => '2026-06-01',
        'preferred_date_to'   => '2026-06-30',
    ]);

    expect($entry->preferred_date_from)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($entry->preferred_date_to)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('belongs to a user', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $entry = WaitlistEntry::factory()->create(['user_id' => $user->id]);

    expect($entry->user->id)->toBe($user->id);
});

it('belongs to preferred staff when set', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $entry = WaitlistEntry::factory()->create(['preferred_staff_id' => $staff->id]);

    expect($entry->preferredStaff->id)->toBe($staff->id);
});

it('waiting scope returns only waiting entries', function () {
    WaitlistEntry::factory()->create(['status' => 'waiting']);
    WaitlistEntry::factory()->create(['status' => 'notified']);
    WaitlistEntry::factory()->create(['status' => 'booked']);

    expect(WaitlistEntry::waiting()->count())->toBe(1);
});
