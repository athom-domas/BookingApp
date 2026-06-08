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
        if (! $appointment->wasChanged('status') || $appointment->status !== 'confirmed') {
            return;
        }

        $this->accrue($appointment);
    }

    private function accrue(Appointment $appointment): void
    {
        $price = (float) ($appointment->final_price ?? 0);
        if ($price <= 0) {
            return;
        }

        // In contesti senza middleware tenant (es. job Google Calendar) current_business_id
        // non è bindato: lo leghiamo dall'appuntamento stesso.
        if (! app()->bound('current_business_id')) {
            app()->instance('current_business_id', $appointment->business_id);
        }

        app(LoyaltyService::class)->accrue($appointment, $price);
    }
}
