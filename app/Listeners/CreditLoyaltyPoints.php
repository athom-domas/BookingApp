<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Services\LoyaltyService;
use Illuminate\Events\Attributes\ListensTo;

#[ListensTo(PaymentCompleted::class)]
class CreditLoyaltyPoints
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function handle(PaymentCompleted $event): void
    {
        $appointment = $event->payment->appointment;
        if (! $appointment) {
            return;
        }

        $this->loyalty->accrue($appointment, (float) $event->payment->amount);
    }
}
