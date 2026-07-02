<?php

use App\Models\ActivityLog;
use App\Models\Business;

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
