<?php

namespace App\Observers;

use App\Jobs\SendReviewRequestJob;
use App\Models\Appointment;
use App\Models\SystemSetting;
use App\Services\LoyaltyService;

class AppointmentObserver
{
    public function created(Appointment $appointment): void
    {
        if ($appointment->status !== 'confirmed') {
            return;
        }

        $this->accrue($appointment);
    }

    public function updated(Appointment $appointment): void
    {
        if (! $appointment->wasChanged('status')) {
            return;
        }

        if ($appointment->status === 'confirmed') {
            $this->accrue($appointment);
        } elseif ($appointment->status === 'cancelled') {
            $this->reverse($appointment);
        } elseif ($appointment->status === 'completed') {
            $this->scheduleReviewRequest($appointment);
        }
    }

    private function scheduleReviewRequest(Appointment $appointment): void
    {
        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        if (! SystemSetting::isReviewRequestEnabled()) {
            return;
        }

        $delay = SystemSetting::getReviewRequestDelayHours();

        SendReviewRequestJob::dispatch($appointment)->delay(now()->addHours($delay));
    }

    private function accrue(Appointment $appointment): void
    {
        $price = (float) ($appointment->final_price ?? 0);
        if ($price <= 0) {
            return;
        }

        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        app(LoyaltyService::class)->accrue($appointment, $price);
    }

    private function reverse(Appointment $appointment): void
    {
        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        app(LoyaltyService::class)->reverse($appointment);

        $payment = $appointment->payment;
        if ($payment && $payment->status === 'pending') {
            app(\App\Services\PaymentService::class)->cancelPendingPayment($payment);
        }
    }
}
