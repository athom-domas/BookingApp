<?php

namespace App\Jobs;

use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function handle(): void
    {
        app()->instance('current_business_id', $this->appointment->business_id);

        $appointment = $this->appointment->load('user', 'staff');

        Mail::send(new AppointmentConfirmationMail($appointment));
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('SendAppointmentConfirmation failed', [
            'appointment_id' => $this->appointment->id,
            'error'          => $e->getMessage(),
        ]);
    }
}
