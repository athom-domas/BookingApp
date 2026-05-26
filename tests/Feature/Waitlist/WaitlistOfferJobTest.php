<?php

use App\Jobs\ExpireWaitlistOfferJob;
use App\Jobs\NotifyWaitlistCandidateJob;
use App\Mail\WaitlistOfferMail;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WaitlistEntry;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('sets entry to notified and dispatches ExpireWaitlistOfferJob', function () {
    \Illuminate\Support\Facades\Queue::fake();
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

    (new NotifyWaitlistCandidateJob($entry, $slotInfo))->handle(
        app(NotificationService::class)
    );

    $entry->refresh();
    expect($entry->status)->toBe('notified')
        ->and($entry->offered_slot)->toEqual($slotInfo)
        ->and($entry->offer_expires_at)->not->toBeNull();

    \Illuminate\Support\Facades\Queue::assertPushed(ExpireWaitlistOfferJob::class);
});

it('sends email when notification_channel is email', function () {
    \Illuminate\Support\Facades\Queue::fake();
    Mail::fake();

    $user = User::factory()->create();
    $user->assignRole('customer');
    UserPreference::factory()->create([
        'user_id'              => $user->id,
        'notification_channel' => 'email',
    ]);
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

    (new NotifyWaitlistCandidateJob($entry, $slotInfo))->handle(
        app(NotificationService::class)
    );

    Mail::assertSent(WaitlistOfferMail::class);
});

it('resets entry to waiting and notifies next candidate when offer expires', function () {
    \Illuminate\Support\Facades\Queue::fake();
    Mail::fake();

    $service = Service::factory()->create();

    $firstEntry = WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'notified',
        'offered_slot'        => ['date' => today()->addDay()->toDateString(), 'time' => '10:00', 'staff_id' => 1, 'service_ids' => [$service->id]],
        'offer_expires_at'    => now()->subMinute(),
    ]);

    $secondEntry = WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'waiting',
    ]);

    $slotInfo = ['date' => today()->addDay()->toDateString(), 'time' => '10:00', 'staff_id' => 1, 'service_ids' => [$service->id]];

    (new ExpireWaitlistOfferJob($firstEntry, $slotInfo, excludeIds: []))->handle();

    $firstEntry->refresh();
    expect($firstEntry->status)->toBe('waiting')
        ->and($firstEntry->offered_slot)->toBeNull()
        ->and($firstEntry->offer_expires_at)->toBeNull();

    \Illuminate\Support\Facades\Queue::assertPushed(NotifyWaitlistCandidateJob::class, fn ($job) => $job->entry->id === $secondEntry->id);
});

it('is idempotent when entry is no longer notified', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $entry = WaitlistEntry::factory()->create(['status' => 'booked']);
    $slotInfo = ['date' => today()->addDay()->toDateString(), 'time' => '10:00', 'staff_id' => 1, 'service_ids' => [1]];

    (new ExpireWaitlistOfferJob($entry, $slotInfo))->handle();

    \Illuminate\Support\Facades\Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
    expect($entry->fresh()->status)->toBe('booked');
});
