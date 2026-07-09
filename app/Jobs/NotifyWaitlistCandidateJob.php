<?php

namespace App\Jobs;

use App\Mail\WaitlistOfferMail;
use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class NotifyWaitlistCandidateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly WaitlistEntry $entry,
        public readonly array $slotInfo,
    ) {}

    public function handle(): void
    {
        app()->instance('current_business_id', $this->entry->business_id);

        $this->entry->update([
            'status'       => 'notified',
            'offered_slot' => $this->slotInfo,
        ]);

        $offerUrl = URL::temporarySignedRoute(
            'waitlist.offer.accept',
            now()->addDays(7),
            ['entry' => $this->entry->id],
        );

        Mail::to($this->entry->user->email)->send(new WaitlistOfferMail($this->entry, $offerUrl));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyWaitlistCandidateJob failed', [
            'entry_id' => $this->entry->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
