<?php

use App\Jobs\SendAppointmentConfirmation;
use App\Jobs\SendAppointmentReminder;
use App\Jobs\SendCancellationNotification;
use App\Mail\AppointmentCancellationMail;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Mail::fake();
});

// --- SendAppointmentReminder ---

it('SendAppointmentReminder sends email to customer', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'type'           => 'email',
        'status'         => 'pending',
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldNotReceive('sendSms');

    (new SendAppointmentReminder($reminder))->handle($mockNotification);

    Mail::assertSent(AppointmentReminderMail::class, fn ($mail) =>
        $mail->appointment->id === $appointment->id
    );
    expect($reminder->fresh()->status)->toBe('sent');
    expect($reminder->fresh()->sent_at)->not->toBeNull();
});

it('SendAppointmentReminder sends SMS when user has sms preference enabled', function () {
    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'               => $user->id,
        'receive_sms_reminders' => true,
        'phone_number'          => '+39123456789',
    ]);
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldReceive('sendSms')
        ->once()
        ->with('+39123456789', Mockery::type('string'));

    (new SendAppointmentReminder($reminder))->handle($mockNotification);
});

it('SendAppointmentReminder sends SMS exception propagates', function () {
    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'               => $user->id,
        'receive_sms_reminders' => true,
        'phone_number'          => '+39123456789',
    ]);
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldReceive('sendSms')
        ->andThrow(new \Exception('Twilio error'));

    expect(fn () => (new SendAppointmentReminder($reminder))->handle($mockNotification))
        ->toThrow(\Exception::class);
});

it('SendAppointmentReminder is a no-op when already sent', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'type'           => 'email',
        'status'         => 'sent',
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldNotReceive('sendSms');

    (new SendAppointmentReminder($reminder))->handle($mockNotification);

    Mail::assertNothingSent();
});

it('SendAppointmentReminder failed hook marks reminder as failed', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    $job = new SendAppointmentReminder($reminder);
    $job->failed(new \Exception('Twilio down'));

    expect($reminder->fresh()->status)->toBe('failed');
    expect($reminder->fresh()->error_message)->toBe('Twilio down');
});

// --- SendAppointmentConfirmation ---

it('SendAppointmentConfirmation sends confirmation email', function () {
    $appointment = Appointment::factory()->create();

    (new SendAppointmentConfirmation($appointment))->handle();

    Mail::assertSent(AppointmentConfirmationMail::class, fn ($mail) =>
        $mail->appointment->id === $appointment->id
    );
});

// --- SendCancellationNotification ---

it('SendCancellationNotification emails both customer and staff', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldNotReceive('sendSms');

    (new SendCancellationNotification($appointment))->handle($mockNotification);

    Mail::assertSent(AppointmentCancellationMail::class, 2);
});
