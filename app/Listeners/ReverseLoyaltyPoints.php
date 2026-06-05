<?php

namespace App\Listeners;

use App\Events\PaymentRefunded;
use App\Services\LoyaltyService;
use Illuminate\Events\Attributes\ListensTo;

#[ListensTo(PaymentRefunded::class)]
class ReverseLoyaltyPoints
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function handle(PaymentRefunded $event): void
    {
        $appointment = $event->payment->appointment;
        if (! $appointment) {
            return;
        }

        $this->loyalty->reverse($appointment);
    }
}
