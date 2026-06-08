<?php

namespace App\Observers;

use App\Models\Appointment;
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
        }
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
    }
}
