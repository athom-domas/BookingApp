<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendLowStockNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
        public readonly array $notifyUserIds,
    ) {}

    public function handle(): void {}
}
