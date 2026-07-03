<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;

it('activity helper returns ActivityLog instance', function () {
    $log = activity()->log('test event');

    expect($log)->toBeInstanceOf(ActivityLog::class);
});

it('ActivityLog has business relation', function () {
    $business = Business::factory()->create();

    $log = activity()
        ->tap(function (ActivityLog $a) use ($business) {
            $a->business_id = $business->id;
        })
        ->log('test');

    expect($log->fresh()->business->id)->toBe($business->id);
});

it('logs appointment creation with correct business_id and source', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);

    Appointment::factory()->create([
        'business_id' => $business->id,
        'staff_id'    => $staff->id,
        'user_id'     => $customer->id,
    ]);

    $log = ActivityLog::where('subject_type', Appointment::class)
        ->where('event', 'created')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id)
        ->and($log->type)->toBe('activity')
        ->and($log->level)->toBe('info')
        ->and($log->source)->toBe('model_event');
});

it('logs appointment status update', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);

    $appointment = Appointment::factory()->create([
        'business_id' => $business->id,
        'staff_id'    => $staff->id,
        'user_id'     => $customer->id,
        'status'      => 'pending',
    ]);

    ActivityLog::truncate();
    $appointment->update(['status' => 'confirmed']);

    $log = ActivityLog::where('subject_type', Appointment::class)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id);
});

it('logs service creation with correct business_id', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Service::factory()->create(['business_id' => $business->id]);

    $log = ActivityLog::where('subject_type', Service::class)
        ->where('event', 'created')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id);
});

it('Business activityLogs relation returns only its own logs', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    activity()->tap(fn(ActivityLog $a) => $a->business_id = $b1->id)->log('b1 log');
    activity()->tap(fn(ActivityLog $a) => $a->business_id = $b2->id)->log('b2 log');
    activity()->log('no business log');

    expect($b1->activityLogs)->toHaveCount(1)
        ->and($b1->activityLogs->first()->description)->toBe('b1 log');
});
