<?php

namespace App\Jobs;

use App\Mail\OrderReceivedNotificationMail;
use App\Models\ProductOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderReceivedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly ProductOrder $order,
        public readonly array $userIds,
    ) {}

    public function handle(): void
    {
        if (empty($this->userIds)) {
            return;
        }

        app()->instance('current_business_id', $this->order->business_id);

        $order = $this->order->load('items.product', 'user');
        $users = User::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            Mail::send(new OrderReceivedNotificationMail($order, $user));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendOrderReceivedNotificationJob failed', [
            'order_id' => $this->order->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
