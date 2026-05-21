<?php

namespace App\Livewire;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PendingCompletionNotifications extends Component
{
    #[Computed]
    public function pendingAppointments(): Collection
    {
        if (! auth()->user()?->isAdmin()) {
            return collect();
        }

        $appointments = Appointment::where('status', 'confirmed')
            ->where('scheduled_date', '<', now())
            ->with(['user', 'staff'])
            ->get();

        if ($appointments->isEmpty()) {
            return collect();
        }

        $allServiceIds = $appointments
            ->flatMap(fn($a) => $a->service_ids ?? [])
            ->unique()
            ->values()
            ->all();

        $services = Service::whereIn('id', $allServiceIds)->get()->keyBy('id');

        return $appointments->filter(function (Appointment $appointment) use ($services): bool {
            $duration = collect($appointment->service_ids ?? [])
                ->sum(fn($id) => $services->get($id)?->duration_minutes ?? 0);

            $appointment->end_time = $appointment->scheduled_date->copy()->addMinutes($duration);

            return $appointment->end_time->isPast();
        })->values();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->pendingAppointments->count();
    }

    public function render()
    {
        return view('livewire.pending-completion-notifications');
    }
}
