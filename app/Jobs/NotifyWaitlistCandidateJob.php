<?php

namespace App\Jobs;

use App\Mail\WaitlistOfferMail;
use App\Models\SystemSetting;
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
        public readonly array $excludeIds = [],
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $timeout   = SystemSetting::getWaitlistOfferTimeout();
        $expiresAt = now()->addMinutes($timeout);

        $this->entry->update([
            'status'           => 'notified',
            'offered_slot'     => $this->slotInfo,
            'offer_expires_at' => $expiresAt,
        ]);

        $offerUrl = URL::temporarySignedRoute(
            'waitlist.offer.accept',
            $expiresAt,
            ['entry' => $this->entry->id],
        );

        $user    = $this->entry->user->load('preferences');
        $prefs   = $user->preferences;
        $channel = $prefs?->notification_channel ?? 'email';

        match ($channel) {
            'sms'      => $prefs->phone_number
                ? $notificationService->sendSms($prefs->phone_number, $this->buildSmsText($offerUrl, $expiresAt))
                : Mail::to($user->email)->send(new WaitlistOfferMail($this->entry, $offerUrl)),
            'whatsapp' => $prefs->phone_number
                ? $notificationService->sendWhatsApp($prefs->phone_number, $this->buildSmsText($offerUrl, $expiresAt))
                : Mail::to($user->email)->send(new WaitlistOfferMail($this->entry, $offerUrl)),
            default    => Mail::to($user->email)->send(new WaitlistOfferMail($this->entry, $offerUrl)),
        };

        ExpireWaitlistOfferJob::dispatch($this->entry, $this->slotInfo, $this->excludeIds)
            ->delay($expiresAt);
    }

    private function buildSmsText(string $offerUrl, \Carbon\Carbon $expiresAt): string
    {
        $date = \Carbon\Carbon::parse($this->slotInfo['date'])->locale('it')->isoFormat('D MMMM');
        $time = $this->slotInfo['time'];

        return "Posto disponibile il {$date} alle {$time}. Prenota entro le {$expiresAt->format('H:i')}: {$offerUrl}";
    }

    public function failed(\Throwable $e): void
    {
        Log::error('NotifyWaitlistCandidateJob failed', [
            'entry_id' => $this->entry->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
