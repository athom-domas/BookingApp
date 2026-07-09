<?php

use App\Jobs\SendWhatsAppNotificationJob;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\PlanFeature;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\LoyaltyService;
use App\Services\WhatsAppNotificationService;
use App\Http\Middleware\EnforceTenantStatus;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware([PreventRequestForgery::class, EnforceTenantStatus::class]);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Queue::fake();
});

function gatingBusiness(string $plan = 'base'): Business
{
    return $plan === 'plus'
        ? Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null])
        : Business::factory()->create(['trial_ends_at' => null]);
}

function gatingAppointment(Business $business): Appointment
{
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

function enableWhatsAppSettings(Business $business): void
{
    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        ['whatsapp_notifications_enabled' => true, 'meta_whatsapp_token' => 'tok', 'meta_whatsapp_phone_id' => '123'],
    );
}

// --- whatsapp_notifications ---

it('whatsapp_notifications: dispatches job when feature allows (plus plan + plus feature)', function () {
    PlanFeature::where('key', 'whatsapp_notifications')->update(['min_plan' => 'plus']);
    $business = gatingBusiness('plus');
    app()->instance('current_business_id', $business->id);
    enableWhatsAppSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(gatingAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->not->toBeNull();
    Queue::assertPushed(SendWhatsAppNotificationJob::class);
});

it('whatsapp_notifications: returns null when feature is plus and business is base plan', function () {
    PlanFeature::where('key', 'whatsapp_notifications')->update(['min_plan' => 'plus']);
    $business = gatingBusiness('base');
    app()->instance('current_business_id', $business->id);
    enableWhatsAppSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(gatingAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

// --- online_payments ---

it('canAcceptOnlinePayments returns false when online_payments feature is plus and business is base', function () {
    PlanFeature::where('key', 'online_payments')->update(['min_plan' => 'plus']);
    $business = gatingBusiness('base');

    expect($business->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments checks Stripe Connect when feature allows', function () {
    PlanFeature::where('key', 'online_payments')->update(['min_plan' => 'base']);
    $business = gatingBusiness('base');

    // No Stripe Connect → false from Stripe check (not from plan gate)
    expect($business->canAcceptOnlinePayments())->toBeFalse();
});

// --- google_calendar ---

it('SyncGoogleCalendar returns early when google_calendar feature is plus and business is base', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'plus']);
    $business    = gatingBusiness('base');
    $appointment = gatingAppointment($business);

    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        ['google_calendar_id' => 'cal@group.calendar.google.com'],
    );

    // Should return without calling GoogleCalendarService (no exception)
    (new SyncGoogleCalendar($appointment, 'create'))->handle(
        app(\App\Services\GoogleCalendarService::class)
    );

    // No google_event_id set = job returned early
    expect($appointment->fresh()->google_event_id)->toBeNull();
});

// --- loyalty_program ---

it('LoyaltyService::accrue does nothing when loyalty_program feature is plus and business is base', function () {
    PlanFeature::where('key', 'loyalty_program')->update(['min_plan' => 'plus']);
    $business    = gatingBusiness('base');
    $appointment = gatingAppointment($business);

    app(\App\Models\SystemSetting::class); // ensure system setting exists (current() creates it)
    \App\Models\SystemSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        ['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1],
    );

    app()->instance('current_business_id', $business->id);
    app(LoyaltyService::class)->accrue($appointment, 100.0);

    expect(\App\Models\LoyaltyTransaction::where('appointment_id', $appointment->id)->exists())->toBeFalse();
});

// --- waitlist ---

it('WaitlistController::store aborts 403 when waitlist feature is plus and business is base', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => 'plus']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $business = gatingBusiness('base');
    app()->instance('current_business_id', $business->id);
    $user = User::factory()->create(['business_id' => $business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->post(route('portal.waitlist.store'), [
            'service_ids'         => [Service::factory()->create(['business_id' => $business->id])->id],
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '11:00',
            'preferred_days'      => [now()->addDay()->format('Y-m-d')],
        ])
        ->assertForbidden();
});
