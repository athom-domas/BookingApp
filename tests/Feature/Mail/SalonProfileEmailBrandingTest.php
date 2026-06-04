<?php

use App\Models\Appointment;
use App\Models\SalonProfile;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('confirmation email contains salon name', function () {
    SalonProfile::current()->update(['name' => 'Salone Branding Test']);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $html = view('emails.appointment-confirmation', ['appointment' => $appointment])->render();

    expect($html)->toContain('Salone Branding Test');
});

it('admin notification email contains salon name', function () {
    SalonProfile::current()->update(['name' => 'Salone Admin Test']);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $html = view('emails.admin-appointment-notification', ['appointment' => $appointment])->render();

    expect($html)->toContain('Salone Admin Test');
});

it('salon footer shows contact info in emails', function () {
    SalonProfile::current()->update([
        'name'    => 'Salone Footer Test',
        'phone'   => '+39 02 888888',
        'address' => 'Via Test 99',
    ]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $html = view('emails.appointment-confirmation', ['appointment' => $appointment])->render();

    expect($html)->toContain('+39 02 888888');
    expect($html)->toContain('Via Test 99');
});
