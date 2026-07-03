<?php

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Queue::fake();
});

afterEach(function () {
    app()->forgetInstance('current_business_id');
});

function makeNotifAppointment(Business $business, string $phone = '+393331234567', string $channel = 'whatsapp'): Appointment
{
    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);
    UserPreference::factory()->create([
        'user_id'              => $customer->id,
        'business_id'          => $business->id,
        'phone_number'         => $phone,
        'notification_channel' => $channel,
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

function enableNotifSettings(Business $business, array $extra = []): IntegrationSetting
{
    return IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        array_merge([
            'whatsapp_notifications_enabled' => true,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => '1234567890',
        ], $extra),
    );
}

it('creates queued whatsapp_message and dispatches job', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);

    $appointment = makeNotifAppointment($business);
    $message     = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']);

    expect($message)->not->toBeNull();
    expect($message->status)->toBe('queued');
    expect($message->direction)->toBe('outbound');
    expect($message->type)->toBe('template');
    expect($message->template_name)->toBe('appointment_confirmed');
    expect($message->phone_normalized)->toBe('+393331234567');
    Queue::assertPushed(SendWhatsAppNotificationJob::class);
});

it('returns null when notifications not enabled', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business, ['whatsapp_notifications_enabled' => false]);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

it('returns null when meta credentials missing', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business, ['meta_whatsapp_token' => null]);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when monthly limit reached', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business, ['whatsapp_monthly_limit' => 10, 'whatsapp_monthly_sent' => 10]);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when customer channel is not whatsapp', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business, channel: 'email'), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when customer has no phone', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business, phone: ''), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when same template already queued or sent for appointment', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);
    $appointment = makeNotifAppointment($business);

    $svc = app(WhatsAppNotificationService::class);
    expect($svc->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']))->not->toBeNull();
    expect($svc->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']))->toBeNull();
    expect(WhatsAppMessage::where('appointment_id', $appointment->id)->count())->toBe(1);
});

it('allows different templates for same appointment', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);
    $appointment = makeNotifAppointment($business);

    $svc = app(WhatsAppNotificationService::class);
    $svc->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']);

    expect($svc->dispatchForAppointment($appointment, 'appointment_reminder', ['Mario']))->not->toBeNull();
});
