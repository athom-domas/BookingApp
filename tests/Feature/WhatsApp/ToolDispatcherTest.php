<?php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\UserPreference;
use App\Services\WhatsAppConversationState;
use App\Services\WhatsAppToolDispatcher;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('lists active services', function () {
    Service::factory()->create(['name' => 'Taglio', 'active' => true]);
    Service::factory()->create(['name' => 'Colore', 'active' => false]);

    $dispatcher = app(WhatsAppToolDispatcher::class);
    $state = app(WhatsAppConversationState::class)->fresh('+393401234567');

    $result = $dispatcher->dispatch(
        ['name' => 'list_services', 'input' => []],
        $state,
        app('current_business_id'),
    );

    expect($result['ok'])->toBeTrue();
    expect(collect($result['services'])->pluck('name'))->toContain('Taglio');
    expect(collect($result['services'])->pluck('name'))->not->toContain('Colore');
});

it('returns SERVICE_NOT_FOUND for unrecognized tool', function () {
    $dispatcher = app(WhatsAppToolDispatcher::class);
    $state      = app(WhatsAppConversationState::class)->fresh('+393401234567');

    $result = $dispatcher->dispatch(
        ['name' => 'drop_table', 'input' => []],
        $state,
        app('current_business_id'),
    );

    expect($result['ok'])->toBeFalse();
    expect($result['code'])->toBe('SERVICE_NOT_FOUND');
});

it('refuses book_appointment without awaiting_confirmation', function () {
    $dispatcher = app(WhatsAppToolDispatcher::class);
    $state      = app(WhatsAppConversationState::class)->fresh('+393401234567');
    $state['awaiting_confirmation'] = false;

    $result = $dispatcher->dispatch(
        ['name' => 'book_appointment', 'input' => ['service_id' => 1, 'staff_id' => 1, 'starts_at' => now()->addDay()->toIso8601String()]],
        $state,
        app('current_business_id'),
    );

    expect($result['ok'])->toBeFalse();
    expect($result['code'])->toBe('CONFIRMATION_REQUIRED');
});

it('refuses book_appointment when slot not in last_available_slots', function () {
    $dispatcher  = app(WhatsAppToolDispatcher::class);
    $state       = app(WhatsAppConversationState::class)->fresh('+393401234567');
    $state['awaiting_confirmation'] = true;
    $state['last_available_slots_generated_at'] = now()->toIso8601String();
    $state['last_available_slots'] = [
        ['starts_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(), 'staff_id' => 99],
    ];
    $state['selected_slot'] = [
        'service_id' => 1,
        'staff_id'   => 99,
        'starts_at'  => now()->addDays(3)->setTime(10, 0)->toIso8601String(),
        'ends_at'    => now()->addDays(3)->setTime(11, 0)->toIso8601String(),
    ];

    $result = $dispatcher->dispatch(
        ['name' => 'book_appointment', 'input' => []],
        $state,
        app('current_business_id'),
    );

    expect($result['ok'])->toBeFalse();
    expect($result['code'])->toBe('SLOT_NO_LONGER_AVAILABLE');
});

it('get_next_appointment returns appointment data for matched customer', function () {
    $businessId = app('current_business_id');
    $phone      = '+393401234567';

    $appointment = Appointment::factory()->create([
        'business_id'    => $businessId,
        'scheduled_date' => now()->addDays(3),
        'status'         => 'confirmed',
    ]);

    UserPreference::factory()->create([
        'business_id'  => $businessId,
        'user_id'      => $appointment->user_id,
        'phone_number' => $phone,
    ]);

    $dispatcher = app(WhatsAppToolDispatcher::class);
    $state      = app(WhatsAppConversationState::class)->fresh($phone);

    $result = $dispatcher->dispatch(
        ['name' => 'get_next_appointment', 'input' => []],
        $state,
        $businessId,
    );

    expect($result['ok'])->toBeTrue();
    expect($result['data']['appointment'])->not->toBeNull();
    expect($result['data']['appointment']['id'])->toBe($appointment->id);
    expect($result['data']['appointment'])->not->toHaveKey('notes');
    expect($result['data']['appointment'])->not->toHaveKey('final_price');
});

it('refuses cancel_appointment when cancellation is disabled', function () {
    IntegrationSetting::current()->update(['whatsapp_ai_cancellation_enabled' => false]);

    $dispatcher = app(WhatsAppToolDispatcher::class);
    $state      = app(WhatsAppConversationState::class)->fresh('+393401234567');

    $result = $dispatcher->dispatch(
        ['name' => 'cancel_appointment', 'input' => ['appointment_id' => 1]],
        $state,
        app('current_business_id'),
    );

    expect($result['ok'])->toBeFalse();
    expect($result['code'])->toBe('CANCELLATION_DISABLED');
});
