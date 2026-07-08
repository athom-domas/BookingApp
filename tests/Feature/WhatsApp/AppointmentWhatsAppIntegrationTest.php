<?php

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Queue::fake();
});

afterEach(fn () => app()->forgetInstance('current_business_id'));

function makeWaEventAppointment(bool $enabled = true): Appointment
{
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        [
            'whatsapp_notifications_enabled' => $enabled,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => '1234567890',
        ],
    );

    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);
    UserPreference::factory()->create([
        'user_id'              => $customer->id,
        'business_id'          => $business->id,
        'phone_number'         => '+393331234567',
        'notification_channel' => 'whatsapp',
    ]);
    $service = Service::factory()->create(['business_id' => $business->id]);

    return Appointment::factory()->create([
        'business_id'    => $business->id,
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->addDays(2),
        'status'         => 'confirmed',
    ]);
}

it('sends appointment_confirmed whatsapp when AppointmentConfirmed fired', function () {
    $appointment = makeWaEventAppointment();

    AppointmentConfirmed::dispatch($appointment, byAdmin: true);

    Queue::assertPushed(SendWhatsAppNotificationJob::class);
    expect(WhatsAppMessage::forAppointmentTemplate($appointment->id, 'appointment_confirmed')->exists())->toBeTrue();
});

it('sends appointment_cancelled whatsapp when AppointmentCancelled fired', function () {
    $appointment = makeWaEventAppointment();

    AppointmentCancelled::dispatch($appointment, 'admin ha cancellato', byAdmin: true);

    Queue::assertPushed(SendWhatsAppNotificationJob::class);
    expect(WhatsAppMessage::forAppointmentTemplate($appointment->id, 'appointment_cancelled')->exists())->toBeTrue();
});

it('does not send whatsapp when notifications disabled', function () {
    $appointment = makeWaEventAppointment(enabled: false);

    AppointmentConfirmed::dispatch($appointment, byAdmin: true);

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

it('does not crash on appointment without customer', function () {
    $appointment = makeWaEventAppointment();
    $appointment->setAttribute('user_id', null);

    app(\App\Listeners\SendWhatsAppAppointmentNotification::class)
        ->handle(new AppointmentConfirmed($appointment));

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});
