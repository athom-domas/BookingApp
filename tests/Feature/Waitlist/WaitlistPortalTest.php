<?php

use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('shows waitlist entries on appointments page for authenticated customer', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->get(route('portal.appointments.index'))
        ->assertOk()
        ->assertViewIs('portal.appointments.index');
});

it('creates a waitlist entry', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $service = Service::factory()->create();

    $date1 = today()->addDay()->toDateString();
    $date2 = today()->addDays(5)->toDateString();

    $this->actingAs($user)
        ->post(route('portal.waitlist.store'), [
            'service_ids'         => [$service->id],
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '18:00',
            'preferred_days'      => [$date1, $date2],
        ])
        ->assertRedirect(route('portal.appointments.index'));

    $entry = WaitlistEntry::where('user_id', $user->id)->first();
    expect($entry)->not->toBeNull()
        ->and($entry->preferred_days)->toContain($date1)
        ->and($entry->preferred_days)->toContain($date2);
});

it('validates required fields on store', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->post(route('portal.waitlist.store'), [])
        ->assertSessionHasErrors(['service_ids', 'preferred_time_from', 'preferred_time_to', 'preferred_days']);
});

it('cancels a waitlist entry owned by the user', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $entry = WaitlistEntry::factory()->create(['user_id' => $user->id, 'status' => 'waiting']);

    $this->actingAs($user)
        ->delete(route('portal.waitlist.destroy', $entry->id))
        ->assertRedirect(route('portal.appointments.index'));

    expect($entry->fresh()->status)->toBe('cancelled');
});

it('prevents cancelling another user\'s entry', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $other   = User::factory()->create();
    $other->assignRole('customer');
    $entry   = WaitlistEntry::factory()->create(['user_id' => $other->id, 'status' => 'waiting']);

    $this->actingAs($user)
        ->delete(route('portal.waitlist.destroy', $entry->id))
        ->assertStatus(403);
});

it('redirects guests to login on appointments page', function () {
    $this->get(route('portal.appointments.index'))->assertRedirect('/login');
});

it('shows create page to guests without redirect', function () {
    $this->get(route('portal.waitlist.create'))->assertOk()->assertViewIs('portal.waitlist.create');
});

it('redirects guests to login on store', function () {
    $this->post(route('portal.waitlist.store'), [])->assertRedirect('/login');
});

it('redirects guests to login on destroy', function () {
    $entry = WaitlistEntry::factory()->create(['status' => 'waiting']);
    $this->delete(route('portal.waitlist.destroy', $entry->id))->assertRedirect('/login');
});

it('pre-fills services on create page from query params', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $service = Service::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('portal.waitlist.create') . '?service_ids[]=' . $service->id);

    $response->assertOk()
        ->assertViewIs('portal.waitlist.create')
        ->assertViewHas('prefilledServiceIds', [$service->id]);
});
