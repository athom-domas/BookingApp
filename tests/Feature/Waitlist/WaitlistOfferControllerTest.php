<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('books the appointment and marks entry as booked on valid offer', function () {
    $staff   = User::factory()->create();
    $staff->assignRole('staff');
    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 30, 'price' => 20]);

    $staff->services()->attach($service->id);

    $monday = now()->next('Monday');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => (int) $monday->dayOfWeek,
        'start_time'   => '08:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $slotInfo = [
        'date'        => $monday->toDateString(),
        'time'        => '10:00',
        'staff_id'    => $staff->id,
        'service_ids' => [$service->id],
    ];

    $entry = WaitlistEntry::factory()->create([
        'user_id'          => $customer->id,
        'service_ids'      => [$service->id],
        'status'           => 'notified',
        'offered_slot'     => $slotInfo,
        'offer_expires_at' => now()->addHours(3),
    ]);

    $url = URL::temporarySignedRoute('waitlist.offer.accept', now()->addHours(3), ['entry' => $entry->id]);

    $response = $this->get($url);

    $entry->refresh();
    expect($entry->status)->toBe('booked');
    expect(Appointment::where('user_id', $customer->id)->exists())->toBeTrue();
    $response->assertViewIs('portal.waitlist.offer-accepted');
});

it('shows expired view when offer has timed out', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $entry = WaitlistEntry::factory()->create([
        'user_id'          => $customer->id,
        'status'           => 'notified',
        'offered_slot'     => ['date' => today()->addDay()->toDateString(), 'time' => '10:00', 'staff_id' => 1, 'service_ids' => [1]],
        'offer_expires_at' => now()->subMinute(),
    ]);

    $url = URL::temporarySignedRoute('waitlist.offer.accept', now()->addHour(), ['entry' => $entry->id]);

    $response = $this->get($url);

    $entry->refresh();
    expect($entry->status)->toBe('expired');
    $response->assertViewIs('portal.waitlist.offer-expired');
});

it('rejects request with invalid signature', function () {
    $entry = WaitlistEntry::factory()->create(['status' => 'notified', 'offer_expires_at' => now()->addHour()]);

    $response = $this->get('/r/waitlist/' . $entry->id . '/accetta?invalid=true');

    $response->assertStatus(403);
});
