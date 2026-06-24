<?php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $messageId,
        public readonly int $businessId,
    ) {
        $this->onQueue(config('services.whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void {}
}
