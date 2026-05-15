<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly ?string $reason = null,
    ) {}
}
