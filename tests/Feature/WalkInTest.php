<?php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Services\WalkInService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $this->business = Business::factory()->create();
    app()->instance('current_business_id', $this->business->id);
});

it('generates a placeholder email when none provided', function () {
    $svc  = new WalkInService();
    $user = $svc->createInlineCustomer('Mario Rossi', null, $this->business->id);

    expect($user->email)->toMatch('/^walkin_[0-9A-Z]+@noreply\.local$/')
        ->and($user->name)->toBe('Mario Rossi')
        ->and($user->business_id)->toBe($this->business->id);
});

it('uses provided email when creating inline customer', function () {
    $svc  = new WalkInService();
    $user = $svc->createInlineCustomer('Mario Rossi', 'mario@example.com', $this->business->id);

    expect($user->email)->toBe('mario@example.com');
});

it('assigns customer role to inline customer', function () {
    $svc  = new WalkInService();
    $user = $svc->createInlineCustomer('Test Cliente', null, $this->business->id);

    expect($user->hasRole('customer'))->toBeTrue();
});

it('generated placeholder emails are unique across multiple calls', function () {
    $svc    = new WalkInService();
    $emails = collect(range(1, 5))
        ->map(fn () => $svc->createInlineCustomer('Test', null, $this->business->id)->email);

    expect($emails->unique()->count())->toBe(5);
});

it('creates walk-in appointment with is_walk_in true and confirmed status', function () {
    $bid      = $this->business->id;
    $staff    = User::factory()->create(['business_id' => $bid]);
    $staff->assignRole('staff');
    $customer = User::factory()->create(['business_id' => $bid]);
    $customer->assignRole('customer');
    $service  = Service::factory()->create(['business_id' => $bid, 'active' => true]);

    $appt = Appointment::create([
        'business_id'    => $bid,
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->addHour(),
        'status'         => 'confirmed',
        'is_walk_in'     => true,
    ]);

    expect($appt->is_walk_in)->toBeTrue()
        ->and($appt->status)->toBe('confirmed');
});
