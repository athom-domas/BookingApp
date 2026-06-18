<?php

use App\Jobs\SendReviewRequestJob;
use App\Mail\ReviewRequestMail;
use App\Models\Appointment;
use App\Models\SalonReview;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('dispatches SendReviewRequestJob when appointment is completed and setting is enabled', function () {
    Queue::fake();

    SystemSetting::current()->update([
        'review_request_enabled'     => true,
        'review_request_delay_hours' => 1,
    ]);

    $appointment = Appointment::factory()->create(['status' => 'confirmed']);
    $appointment->update(['status' => 'completed']);

    Queue::assertPushed(SendReviewRequestJob::class, fn ($job) => $job->appointment->is($appointment));
});

it('does not dispatch job when setting is disabled', function () {
    Queue::fake();

    SystemSetting::current()->update(['review_request_enabled' => false]);

    $appointment = Appointment::factory()->create(['status' => 'confirmed']);
    $appointment->update(['status' => 'completed']);

    Queue::assertNotPushed(SendReviewRequestJob::class);
});

it('does not dispatch job when status changes to something other than completed', function () {
    Queue::fake();

    SystemSetting::current()->update(['review_request_enabled' => true]);

    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $appointment->update(['status' => 'confirmed']);

    Queue::assertNotPushed(SendReviewRequestJob::class);
});

it('job sends email to customer', function () {
    Mail::fake();

    SystemSetting::current()->update([
        'review_request_enabled'     => true,
        'review_request_delay_hours' => 1,
    ]);

    $customer = User::factory()->create(['email' => 'cliente@test.it']);
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'user_id' => $customer->id,
        'status'  => 'confirmed',
    ]);
    $appointment->update(['status' => 'completed']);

    $job = new SendReviewRequestJob($appointment->fresh());
    $job->handle();

    Mail::assertSent(ReviewRequestMail::class, fn ($mail) => $mail->hasTo('cliente@test.it'));
});

it('job skips email if customer already reviewed', function () {
    Mail::fake();

    SystemSetting::current()->update(['review_request_enabled' => true]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $customer->id, 'status' => 'completed']);

    SalonReview::factory()->create([
        'appointment_id' => $appointment->id,
        'user_id'        => $customer->id,
    ]);

    $job = new SendReviewRequestJob($appointment);
    $job->handle();

    Mail::assertNotSent(ReviewRequestMail::class);
});

it('job skips email if setting disabled at handle time', function () {
    Mail::fake();

    SystemSetting::current()->update(['review_request_enabled' => false]);

    $appointment = Appointment::factory()->create(['status' => 'completed']);

    $job = new SendReviewRequestJob($appointment);
    $job->handle();

    Mail::assertNotSent(ReviewRequestMail::class);
});
