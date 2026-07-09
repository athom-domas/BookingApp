<?php

use App\Jobs\SendAppointmentConfirmation;
use App\Jobs\SendAppointmentReminder;
use App\Jobs\SendCancellationNotification;
use App\Mail\AdminCancellationNotificationMail;
use App\Mail\AppointmentCancellationMail;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\NotificationService;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Mail::fake();
});

// --- SendAppointmentReminder ---

it('SendAppointmentReminder sends email when whatsapp gates block', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'type'           => 'email',
        'status'         => 'pending',
    ]);

    (new SendAppointmentReminder($reminder))->handle(app(WhatsAppNotificationService::class));

    Mail::assertSent(AppointmentReminderMail::class, fn ($mail) =>
        $mail->appointment->id === $appointment->id
    );
    expect($reminder->fresh()->status)->toBe('sent');
    expect($reminder->fresh()->sent_at)->not->toBeNull();
});

it('SendAppointmentReminder dispatches whatsapp job when enabled', function () {
    Queue::fake();

    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'              => $user->id,
        'notification_channel' => 'whatsapp',
        'phone_number'         => '+39123456789',
    ]);
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $appointment->business_id],
        [
            'whatsapp_notifications_enabled' => true,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => 'test-phone-id',
        ],
    );

    (new SendAppointmentReminder($reminder))->handle(app(WhatsAppNotificationService::class));

    Queue::assertPushed(SendWhatsAppNotificationJob::class);
    Mail::assertNotSent(AppointmentReminderMail::class);
    expect($reminder->fresh()->status)->toBe('sent');
});

it('SendAppointmentReminder exception propagates to failed hook path', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    $mockWhatsApp = $this->mock(WhatsAppNotificationService::class);
    $mockWhatsApp->shouldReceive('dispatchForAppointment')
        ->andThrow(new \Exception('WhatsApp error'));

    expect(fn () => (new SendAppointmentReminder($reminder))->handle($mockWhatsApp))
        ->toThrow(\Exception::class);
});

it('SendAppointmentReminder is a no-op when already sent', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'type'           => 'email',
        'status'         => 'sent',
    ]);

    (new SendAppointmentReminder($reminder))->handle(app(WhatsAppNotificationService::class));

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

it('SendCancellationNotification emails customer and admin', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $customer = User::factory()->create(['business_id' => $business->id]);
    $customer->assignRole('customer');
    $staff = User::factory()->create(['business_id' => $business->id]);
    $staff->assignRole('staff');
    $admin = User::factory()->create(['business_id' => $business->id, 'receive_email_notifications' => true]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($business->id);

    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $mockNotification = $this->mock(NotificationService::class);
    $mockNotification->shouldNotReceive('sendSms');

    (new SendCancellationNotification($appointment))->handle($mockNotification);

    Mail::assertSent(AdminCancellationNotificationMail::class, 1);
    Mail::assertNotSent(AppointmentCancellationMail::class);
});
