<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('redirects to booking form and seeds session when offer is valid', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $slotInfo = [
        'date'        => today()->addDay()->toDateString(),
        'time'        => '10:00',
        'staff_id'    => 99,
        'service_ids' => [1],
    ];

    $entry = WaitlistEntry::factory()->create([
        'user_id'      => $customer->id,
        'service_ids'  => [1],
        'status'       => 'notified',
        'offered_slot' => $slotInfo,
    ]);

    $url = URL::temporarySignedRoute('waitlist.offer.accept', now()->addDays(7), ['entry' => $entry->id]);

    $response = $this->get($url);

    $response->assertRedirect(route('booking.create'));
    $response->assertSessionHas('bookingWizardPrefill');

    $prefill = $response->getSession()->get('bookingWizardPrefill');
    expect($prefill['date'])->toBe($slotInfo['date'])
        ->and($prefill['slot'])->toBe($slotInfo['time'])
        ->and($prefill['staffId'])->toBe($slotInfo['staff_id'])
        ->and($prefill['selectedServiceIds'])->toBe([1])
        ->and($prefill['step'])->toBe(4)
        ->and($prefill['completed'])->toBe([1, 2, 3])
        ->and($prefill['waitlistEntryId'])->toBe($entry->id);
});

it('shows expired view when entry is not in notified status', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $entry = WaitlistEntry::factory()->create([
        'user_id' => $customer->id,
        'status'  => 'waiting',
    ]);

    $url = URL::temporarySignedRoute('waitlist.offer.accept', now()->addDays(7), ['entry' => $entry->id]);

    $response = $this->get($url);

    $response->assertViewIs('portal.waitlist.offer-expired');
});

it('rejects request with invalid signature', function () {
    $entry = WaitlistEntry::factory()->create(['status' => 'notified']);

    $response = $this->get('/r/waitlist/' . $entry->id . '/accetta?invalid=true');

    $response->assertStatus(403);
});

it('marks waitlist entry as booked after booking from waitlist offer', function () {
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
        'user_id'      => $customer->id,
        'service_ids'  => [$service->id],
        'status'       => 'notified',
        'offered_slot' => $slotInfo,
    ]);

    $response = $this->actingAs($customer)
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
        ->post(route('portal.bookings.store'), [
            'service_ids'       => [$service->id],
            'staff_id'          => $staff->id,
            'scheduled_date'    => $monday->setTime(10, 0)->toDateTimeString(),
            'payment_method'    => 'in_salon',
            'waitlist_entry_id' => $entry->id,
        ]);

    $response->assertRedirectContains('/portale/appuntamenti/');
    expect(Appointment::where('user_id', $customer->id)->exists())->toBeTrue();
    expect($entry->fresh()->status)->toBe('booked');
});

it('resets other notified entries for same slot after booking from waitlist offer', function () {
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

    $slotInfo = ['date' => $monday->toDateString(), 'time' => '10:00', 'staff_id' => $staff->id, 'service_ids' => [$service->id]];

    $customer1 = User::factory()->create();
    $customer1->assignRole('customer');
    $customer2 = User::factory()->create();
    $customer2->assignRole('customer');

    $entry1 = WaitlistEntry::factory()->create([
        'user_id' => $customer1->id, 'service_ids' => [$service->id],
        'status' => 'notified', 'offered_slot' => $slotInfo,
    ]);
    $entry2 = WaitlistEntry::factory()->create([
        'user_id' => $customer2->id, 'service_ids' => [$service->id],
        'status' => 'notified', 'offered_slot' => $slotInfo,
    ]);

    $this->actingAs($customer1)
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
        ->post(route('portal.bookings.store'), [
            'service_ids'       => [$service->id],
            'staff_id'          => $staff->id,
            'scheduled_date'    => $monday->setTime(10, 0)->toDateTimeString(),
            'payment_method'    => 'in_salon',
            'waitlist_entry_id' => $entry1->id,
        ]);

    expect($entry1->fresh()->status)->toBe('booked');
    expect($entry2->fresh()->status)->toBe('waiting');
    expect($entry2->fresh()->offered_slot)->toBeNull();
});
