<?php

use App\Jobs\SendFollowUpReminder;
use App\Mail\FollowUpReminderMail;
use App\Models\Business;
use App\Models\FollowUpReminder;
use App\Models\SystemSetting;
use App\Models\UserPreference;
use App\Models\User;
use App\Models\Appointment;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

// SystemSetting helpers
it('isFollowUpRemindersEnabled returns false by default', function () {
    expect(SystemSetting::isFollowUpRemindersEnabled())->toBeFalse();
});

it('getFollowUpReminderDays returns 30 by default', function () {
    expect(SystemSetting::getFollowUpReminderDays())->toBe(30);
});

it('isFollowUpRemindersEnabled returns true when enabled', function () {
    SystemSetting::current()->update(['follow_up_reminders_enabled' => true]);
    expect(SystemSetting::isFollowUpRemindersEnabled())->toBeTrue();
});

// UserPreference
it('follow_up_reminders_enabled defaults to true on new preferences', function () {
    $pref = UserPreference::factory()->create();
    expect($pref->follow_up_reminders_enabled)->toBeTrue();
});

// ---- Observer trigger tests ----

function makeEnabledSettings(): void
{
    SystemSetting::current()->update([
        'follow_up_reminders_enabled' => true,
        'follow_up_reminder_days'     => 30,
    ]);
}

function makeCustomerWithPrefs(bool $followUpEnabled = true): User
{
    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'                     => $user->id,
        'follow_up_reminders_enabled' => $followUpEnabled,
    ]);
    return $user;
}

it('creates a follow-up reminder when appointment is completed and feature is enabled', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(1);
    $reminder = FollowUpReminder::where('user_id', $customer->id)->first();
    expect($reminder->type)->toBe('rebooking');
    expect($reminder->status)->toBe('pending');
    expect($reminder->delay_days)->toBe(30);
    expect($reminder->appointment_id)->toBe($appt->id);
});

it('does not create a reminder if feature is disabled', function () {
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create(['user_id' => $customer->id, 'status' => 'confirmed']);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(0);
});

it('does not create a reminder if user has follow_up_reminders_enabled = false', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs(followUpEnabled: false);
    $appt = Appointment::factory()->create(['user_id' => $customer->id, 'status' => 'confirmed']);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(0);
});

it('does not create a reminder if user has a future appointment', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDays(5),
    ]);
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(0);
});

it('does not create a duplicate reminder for the same appointment', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt->update(['status' => 'completed']);
    $appt->touch(); // trigger updated again
    $appt->update(['status' => 'completed']); // fire observer again

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(1);
});

it('does not create a duplicate pending reminder for same user and business', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt1 = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(10),
    ]);
    $appt2 = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt1->update(['status' => 'completed']);
    $appt2->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(1);
});

// ---- Job tests ----

it('job skips if admin disables feature after reminder creation', function () {
    Mail::fake();
    $customer = makeCustomerWithPrefs();
    $reminder = FollowUpReminder::factory()->create([
        'user_id'       => $customer->id,
        'delay_days'    => 30,
        'scheduled_for' => now()->subMinute(),
        'status'        => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('feature_disabled');
    Mail::assertNotSent(FollowUpReminderMail::class);
});

it('job skips if user disables follow-up after reminder creation', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs(followUpEnabled: false);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'       => $customer->id,
        'delay_days'    => 30,
        'scheduled_for' => now()->subMinute(),
        'status'        => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('user_disabled');
});

it('job skips if user books a future appointment before send time', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDays(5),
    ]);
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(35),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $appt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('user_has_future_appointment');
});

it('job skips if user completed a newer appointment more recently than delay_days', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $originalAppt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(40),
    ]);
    // Newer appointment completed only 5 days ago (within 30-day window)
    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(5),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $originalAppt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('recent_appointment_completed');
});

it('second job invocation on same reminder does not send (atomic claim)', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(35),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $appt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();
    (new SendFollowUpReminder($reminder->id))->handle(); // second invocation

    Mail::assertSentCount(1);
    expect($reminder->fresh()->status)->toBe('sent');
});
