<?php

use App\Jobs\NotifyWaitlistCandidateJob;
use App\Mail\WaitlistOfferMail;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('sets entry to notified with offered_slot', function () {
    Queue::fake();
    Mail::fake();

    $user    = User::factory()->create();
    $user->assignRole('customer');
    $service = Service::factory()->create();
    $entry   = WaitlistEntry::factory()->create([
        'user_id'     => $user->id,
        'service_ids' => [$service->id],
        'status'      => 'waiting',
    ]);

    $slotInfo = [
        'date'        => today()->addDay()->toDateString(),
        'time'        => '10:00',
        'staff_id'    => 1,
        'service_ids' => [$service->id],
    ];

    (new NotifyWaitlistCandidateJob($entry, $slotInfo))->handle();

    $entry->refresh();
    expect($entry->status)->toBe('notified')
        ->and($entry->offered_slot)->toEqual($slotInfo);
});

it('does not dispatch ExpireWaitlistOfferJob', function () {
    Queue::fake();
    Mail::fake();

    $user  = User::factory()->create();
    $user->assignRole('customer');
    $entry = WaitlistEntry::factory()->create(['user_id' => $user->id, 'status' => 'waiting']);

    $slotInfo = ['date' => today()->addDay()->toDateString(), 'time' => '10:00', 'staff_id' => 1, 'service_ids' => $entry->service_ids];

    (new NotifyWaitlistCandidateJob($entry, $slotInfo))->handle();

    Queue::assertNothingPushed();
});

it('sends email to user', function () {
    Queue::fake();
    Mail::fake();

    $user = User::factory()->create();
    $user->assignRole('customer');
    $entry = WaitlistEntry::factory()->create([
        'user_id' => $user->id,
        'status'  => 'waiting',
    ]);

    $slotInfo = [
        'date'        => today()->addDay()->toDateString(),
        'time'        => '10:00',
        'staff_id'    => 1,
        'service_ids' => $entry->service_ids,
    ];

    (new NotifyWaitlistCandidateJob($entry, $slotInfo))->handle();

    Mail::assertSent(WaitlistOfferMail::class);
});
