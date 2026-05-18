<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendarWidget extends FullCalendarWidget
{
    public ?int $staffFilter = null;

    public function config(): array
    {
        return [
            'initialView'   => 'dayGridMonth',
            'headerToolbar' => [
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => 'dayGridMonth,timeGridWeek,timeGridDay',
            ],
            'locale' => 'it',
        ];
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $query = Appointment::query()
            ->with(['user', 'staff'])
            ->whereBetween('scheduled_date', [$fetchInfo['start'], $fetchInfo['end']]);

        $user = auth()->user();

        if ($user->isStaff()) {
            $query->where('staff_id', $user->id);
        } elseif ($user->isAdmin() && $this->staffFilter) {
            $query->where('staff_id', $this->staffFilter);
        }

        $appointments = $query->get();

        $allServiceIds = $appointments
            ->flatMap(fn ($a) => $a->service_ids ?? [])
            ->unique()
            ->values()
            ->all();

        $services = Service::whereIn('id', $allServiceIds)->get()->keyBy('id');

        return $appointments->map(function ($appointment) use ($services) {
            $duration = collect($appointment->service_ids ?? [])
                ->sum(fn ($id) => $services->get($id)?->duration_minutes ?? 30);

            $serviceNames = collect($appointment->service_ids ?? [])
                ->map(fn ($id) => $services->get($id)?->name)
                ->filter()
                ->implode(', ');

            return [
                'id'              => $appointment->id,
                'title'           => $appointment->user->name . ' – ' . $serviceNames,
                'start'           => $appointment->scheduled_date->toIso8601String(),
                'end'             => $appointment->scheduled_date->copy()->addMinutes($duration)->toIso8601String(),
                'backgroundColor' => $this->staffColor($appointment->staff_id),
                'extendedProps'   => ['status' => $appointment->status],
            ];
        })->toArray();
    }

    private function staffColor(int $staffId): string
    {
        $palette = [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
            '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
        ];

        return $palette[$staffId % count($palette)];
    }

    public function onEventClick(array $event): void
    {
        $this->mountAction('changeStatus', arguments: ['appointmentId' => $event['id']]);
    }

    public function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label('Dettagli prenotazione')
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $arguments = $action->getArguments();
                $appointment = Appointment::with(['user', 'staff'])->find($arguments['appointmentId']);
                $schema?->fill([
                    'appointment_id' => $appointment->id,
                    'customer_name'  => $appointment->user->name,
                    'staff_name'     => $appointment->staff->name,
                    'scheduled_date' => $appointment->scheduled_date->format('d/m/Y H:i'),
                    'services'       => $appointment->services_label,
                    'status'         => $appointment->status,
                ]);
            })
            ->schema([
                Hidden::make('appointment_id'),
                TextInput::make('customer_name')->label('Cliente')->disabled(),
                TextInput::make('staff_name')->label('Staff')->disabled(),
                TextInput::make('scheduled_date')->label('Data e ora')->disabled(),
                TextInput::make('services')->label('Servizi')->disabled(),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'completed' => 'Completato',
                        'cancelled' => 'Annullato',
                    ])
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $appointmentId = $data['appointment_id'] ?? $action->getArguments()['appointmentId'];
                Appointment::find($appointmentId)->update(['status' => $data['status']]);
            });
    }

    #[On('calendar-staff-filter-updated')]
    public function handleStaffFilterUpdated(?int $staffId): void
    {
        $this->staffFilter = $staffId;
        $this->dispatch('filament-fullcalendar--refresh');
    }
}
