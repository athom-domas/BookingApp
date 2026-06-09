<?php

namespace App\Jobs;

use App\Mail\LowStockNotificationMail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLowStockNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly array $userIds,
    ) {}

    public function handle(): void
    {
        if (empty($this->userIds)) {
            return;
        }

        app()->instance('current_business_id', $this->product->business_id);

        $users = User::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            Mail::send(new LowStockNotificationMail($this->product, $user));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendLowStockNotificationJob failed', [
            'product_id' => $this->product->id,
            'error'      => $e->getMessage(),
        ]);
    }
}
