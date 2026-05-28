<?php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WaitlistEntry;
use Tests\Concerns\WithBusinessContext;

uses(WithBusinessContext::class);

beforeEach(function () {
    foreach (['admin', 'staff', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

// --- Appointment ---

it('scopes appointments to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    Appointment::factory()->create(['business_id' => $b1->id]);
    Appointment::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(Appointment::count())->toBe(1);

    $this->setBusinessContext($b2);
    expect(Appointment::count())->toBe(1);
});

it('cannot find appointment from another business by id', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    $apt = Appointment::factory()->create(['business_id' => $b1->id]);

    $this->setBusinessContext($b2);
    expect(Appointment::find($apt->id))->toBeNull();
});

// --- Service ---

it('scopes services to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    Service::factory()->create(['business_id' => $b1->id]);
    Service::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(Service::count())->toBe(1);
});

// --- Payment ---

it('scopes payments to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    Payment::factory()->create(['business_id' => $b1->id]);
    Payment::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(Payment::count())->toBe(1);
});

// --- SystemSetting ---

it('SystemSetting::current() isolates per business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    $this->setBusinessContext($b1);
    $s1 = SystemSetting::current();

    $this->setBusinessContext($b2);
    $s2 = SystemSetting::current();

    expect($s1->id)->not->toBe($s2->id);
    expect($s1->business_id)->toBe($b1->id);
    expect($s2->business_id)->toBe($b2->id);
});

// --- Auto-fill ---

it('auto-fills business_id on new records', function () {
    $business = Business::factory()->create();
    $this->setBusinessContext($business);

    $service = Service::create([
        'name'             => 'Auto Test',
        'duration_minutes' => 30,
        'price'            => 10.00,
        'active'           => true,
        'featured'         => false,
    ]);

    expect($service->business_id)->toBe($business->id);
});

// --- Cross-tenant API ---

it('prevents cross-tenant API access with valid Sanctum token', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $user = User::factory()->create(['business_id' => $b1->id]);
    $user->assignRole('customer');

    app()->instance('current_business_id', $b2->id);

    $this->actingAs($user)
        ->getJson('/api/appointments')
        ->assertForbidden();
});

// --- WaitlistEntry ---

it('scopes waitlist entries to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    WaitlistEntry::factory()->create(['business_id' => $b1->id]);
    WaitlistEntry::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(WaitlistEntry::count())->toBe(1);
});
