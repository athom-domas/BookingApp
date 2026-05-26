<?php

namespace App\Jobs;

use App\Listeners\MatchWaitlistOnCancellation;
use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireWaitlistOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly WaitlistEntry $entry,
        public readonly array $slotInfo,
        public readonly array $excludeIds = [],
    ) {}

    public function handle(): void
    {
        $entry = $this->entry->fresh();

        if ($entry->status !== 'notified') {
            return;
        }

        $entry->update([
            'status'           => 'waiting',
            'offered_slot'     => null,
            'offer_expires_at' => null,
        ]);

        $newExcludeIds = [...$this->excludeIds, $entry->id];
        $next          = MatchWaitlistOnCancellation::findCandidate($this->slotInfo, $newExcludeIds);

        if ($next) {
            NotifyWaitlistCandidateJob::dispatch($next, $this->slotInfo, $newExcludeIds);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ExpireWaitlistOfferJob failed', [
            'entry_id' => $this->entry->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
