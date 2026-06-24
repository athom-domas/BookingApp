<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];
    public int $timeout = 120;

    public function __construct(public readonly int $messageId)
    {
        $this->onQueue(config('services.whatsapp.queue', 'whatsapp'));
    }

    public function handle(WhatsAppConversationService $service): void
    {
        $message = WhatsAppMessage::find($this->messageId);

        if (! $message) {
            Log::warning('ProcessWhatsAppMessageJob: message not found', ['message_id' => $this->messageId]);
            return;
        }

        if ($message->direction !== 'inbound') {
            return;
        }

        if ($message->processed_at !== null) {
            return; // already processed — idempotent
        }

        $service->handle($this->messageId, $message->business_id);
    }

    public function failed(\Throwable $exception): void
    {
        WhatsAppMessage::where('id', $this->messageId)->update([
            'failed_at'     => now(),
            'error_code'    => 'JOB_FAILED',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
