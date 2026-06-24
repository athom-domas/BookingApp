<?php
namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppMessageJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public array $backoff = [5, 30, 120];

    public function __construct(
        public readonly int $messageId,
        public readonly int $businessId,
    ) {
        $this->onQueue(config('services.whatsapp.queue', 'whatsapp'));
    }

    public function handle(WhatsAppConversationService $service): void
    {
        $service->handle($this->messageId, $this->businessId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessWhatsAppMessageJob failed permanently', [
            'message_id'  => $this->messageId,
            'business_id' => $this->businessId,
            'error'       => $exception->getMessage(),
        ]);

        WhatsAppMessage::where('id', $this->messageId)->update([
            'failed_at'    => now(),
            'error_code'   => 'JOB_FAILED',
            'error_message'=> substr($exception->getMessage(), 0, 500),
        ]);
    }
}
