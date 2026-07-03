<?php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
});

it('notifications are disabled by default', function () {
    $business = Business::factory()->create();
    $setting  = IntegrationSetting::create(['business_id' => $business->id]);

    expect($setting->hasWhatsAppNotificationsEnabled())->toBeFalse();
});

it('hasWhatsAppMonthlyCapacity returns true when limit is null', function () {
    $business = Business::factory()->create();
    $setting  = IntegrationSetting::create([
        'business_id'           => $business->id,
        'whatsapp_monthly_limit' => null,
        'whatsapp_monthly_sent' => 9999,
    ]);

    expect($setting->hasWhatsAppMonthlyCapacity())->toBeTrue();
});

it('hasWhatsAppMonthlyCapacity returns false when limit reached', function () {
    $business = Business::factory()->create();
    $setting  = IntegrationSetting::create([
        'business_id'            => $business->id,
        'whatsapp_monthly_limit' => 100,
        'whatsapp_monthly_sent'  => 100,
    ]);

    expect($setting->hasWhatsAppMonthlyCapacity())->toBeFalse();
});

it('forAppointmentTemplate scope finds notification rows', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $appointment = Appointment::factory()->create(['business_id' => $business->id]);

    WhatsAppMessage::create([
        'business_id'      => $business->id,
        'appointment_id'   => $appointment->id,
        'phone'            => '+393331234567',
        'phone_normalized' => '+393331234567',
        'direction'        => 'outbound',
        'type'             => 'template',
        'template_name'    => 'appointment_confirmed',
        'payload'          => ['parameters' => ['Mario']],
        'status'           => 'sent',
    ]);

    $exists = WhatsAppMessage::where('business_id', $business->id)
        ->forAppointmentTemplate($appointment->id, 'appointment_confirmed')
        ->whereIn('status', ['queued', 'sent'])
        ->exists();

    expect($exists)->toBeTrue();
    expect(WhatsAppMessage::first()->appointment->id)->toBe($appointment->id);
});
