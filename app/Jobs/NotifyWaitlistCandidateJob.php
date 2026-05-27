<?php

namespace App\Jobs;

use App\Mail\WaitlistOfferMail;
use App\Models\WaitlistEntry;
use App\Services\NotificationService;
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

    public function handle(NotificationService $notificationService): void
    {
        $this->entry->update([
            'status'       => 'notified',
            'offered_slot' => $this->slotInfo,
        ]);

        $offerUrl = URL::temporarySignedRoute(
            'waitlist.offer.accept',
            now()->addDays(7),
            ['entry' => $this->entry->id],
        );

        $user    = $this->entry->user->load('preferences');
        $prefs   = $user->preferences;
        $channel = $prefs?->notification_channel ?? 'email';

        match ($channel) {
            'sms'      => $prefs->phone_number
                ? $notificationService->sendSms($prefs->phone_number, $this->buildSmsText($offerUrl))
                : Mail::to($user->email)->send(new WaitlistOfferMail($this->entry, $offerUrl)),
            'whatsapp' => $prefs->phone_number
                ? $notificationService->sendWhatsApp($prefs->phone_number, $this->buildSmsText($offerUrl))
                : Mail::to($user->email)->send(new WaitlistOfferMail($this->entry, $offerUrl)),
            default    => Mail::to($user->email)->send(new WaitlistOfferMail($this->entry, $offerUrl)),
        };
    }

    private function buildSmsText(string $offerUrl): string
    {
        $date = \Carbon\Carbon::parse($this->slotInfo['date'])->locale('it')->isoFormat('D MMMM');
        $time = $this->slotInfo['time'];

        return "Posto disponibile il {$date} alle {$time}. Prenota subito (prima di altri): {$offerUrl}";
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyWaitlistCandidateJob failed', [
            'entry_id' => $this->entry->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
