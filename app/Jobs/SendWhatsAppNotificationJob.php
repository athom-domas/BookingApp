<?php

namespace App\Jobs;

use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int   $tries   = 3;
    public array $backoff = [30, 60, 300];
    public int   $timeout = 60;

    public function __construct(public readonly int $whatsappMessageId) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $message = WhatsAppMessage::find($this->whatsappMessageId);

        if (! $message || $message->status !== 'queued') {
            return;
        }

        $settings = IntegrationSetting::withoutGlobalScope('business')
            ->where('business_id', $message->business_id)
            ->first();

        $wamid = $whatsApp->sendTemplate(
            $message->phone_normalized,
            $message->template_name,
            $settings?->getWhatsAppAiLanguage() ?? 'it',
            'UTILITY',
            $message->payload['parameters'] ?? [],
            $message->business_id,
        );

        if ($wamid !== null) {
            $message->update([
                'status'  => 'sent',
                'wamid'   => $wamid !== '' ? $wamid : null,
                'sent_at' => now(),
            ]);

            IntegrationSetting::withoutGlobalScope('business')
                ->where('business_id', $message->business_id)
                ->increment('whatsapp_monthly_sent');
        } else {
            $message->update([
                'status'        => 'failed',
                'failed_at'     => now(),
                'error_message' => 'Meta API send failed',
            ]);

            Log::warning('WhatsApp notification send failed', [
                'message_id' => $message->id,
                'template'   => $message->template_name,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        WhatsAppMessage::where('id', $this->whatsappMessageId)->update([
            'status'        => 'failed',
            'failed_at'     => now(),
            'error_message' => $e->getMessage(),
        ]);
    }
}
