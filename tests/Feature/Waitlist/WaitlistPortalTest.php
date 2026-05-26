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

it('shows waitlist index for authenticated customer', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->get(route('portal.waitlist.index'))
        ->assertOk()
        ->assertViewIs('portal.waitlist.index');
});

it('creates a waitlist entry', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $service = Service::factory()->create();

    $this->actingAs($user)
        ->post(route('portal.waitlist.store'), [
            'service_ids'         => [$service->id],
            'preferred_date_from' => today()->addDay()->toDateString(),
            'preferred_date_to'   => today()->addDays(30)->toDateString(),
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '18:00',
            'preferred_days'      => ['monday', 'wednesday', 'friday'],
        ])
        ->assertRedirect(route('portal.waitlist.index'));

    expect(WaitlistEntry::where('user_id', $user->id)->exists())->toBeTrue();
});

it('validates required fields on store', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $this->actingAs($user)
        ->post(route('portal.waitlist.store'), [])
        ->assertSessionHasErrors(['service_ids', 'preferred_date_from', 'preferred_date_to', 'preferred_time_from', 'preferred_time_to', 'preferred_days']);
});

it('cancels a waitlist entry owned by the user', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $entry = WaitlistEntry::factory()->create(['user_id' => $user->id, 'status' => 'waiting']);

    $this->actingAs($user)
        ->delete(route('portal.waitlist.destroy', $entry->id))
        ->assertRedirect(route('portal.waitlist.index'));

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

it('redirects guests to login', function () {
    $this->get(route('portal.waitlist.index'))->assertRedirect('/login');
});

it('redirects guests to login on create page', function () {
    $this->get(route('portal.waitlist.create'))->assertRedirect('/login');
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
