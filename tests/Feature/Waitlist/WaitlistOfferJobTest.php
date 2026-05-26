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
