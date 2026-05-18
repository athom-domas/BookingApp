<?php

namespace App\Filament\Widgets;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendarWidget extends FullCalendarWidget
{
    public array $filterStaff    = [];
    public array $filterStatus   = [];
    public array $filterService  = [];
    public array $filterCustomer = [];

    protected function headerActions(): array
    {
        return [];
    }

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
        } elseif ($user->isAdmin() && !empty($this->filterStaff)) {
            $query->whereIn('staff_id', $this->filterStaff);
        }

        if (!empty($this->filterStatus)) {
            $query->whereIn('status', $this->filterStatus);
        }

        if (!empty($this->filterCustomer)) {
            $query->whereIn('user_id', $this->filterCustomer);
        }

        if (!empty($this->filterService)) {
            $query->whereRaw('JSON_OVERLAPS(service_ids, ?)', [json_encode(array_map('intval', $this->filterService))]);
        }

        $appointments = $query->get();

        $allServiceIds = $appointments
            ->flatMap(fn($a) => $a->service_ids ?? [])
            ->unique()
            ->values()
            ->all();

        $services = Service::whereIn('id', $allServiceIds)->get()->keyBy('id');

        return $appointments->map(function ($appointment) use ($services) {
            $duration = collect($appointment->service_ids ?? [])
                ->sum(fn($id) => $services->get($id)?->duration_minutes ?? 30);

            $serviceNames = collect($appointment->service_ids ?? [])
                ->map(fn($id) => $services->get($id)?->name)
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

    private function authorizeAppointmentAccess(Action $action): bool
    {
        $user          = auth()->user();
        $appointmentId = $action->getArguments()['appointmentId'] ?? null;

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff() && $appointmentId) {
            return Appointment::where('id', $appointmentId)
                ->where('staff_id', $user->id)
                ->exists();
        }

        return false;
    }

    private function staffColor(int $staffId): string
    {
        $palette = [
            '#3B82F6',
            '#10B981',
            '#F59E0B',
            '#EF4444',
            '#8B5CF6',
            '#EC4899',
            '#14B8A6',
            '#F97316',
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
                    ->in(['pending', 'confirmed', 'completed', 'cancelled'])
                    ->required(),
            ])
            ->authorize(fn(Action $action) => $this->authorizeAppointmentAccess($action))
            ->extraModalFooterActions(function (Action $action): array {
                $appointmentId = $action->getArguments()['appointmentId'] ?? null;
                $appointment   = Appointment::with('payment')->find($appointmentId);

                if (! $appointment || $appointment->payment?->status === 'completed') {
                    return [];
                }

                return [
                    Action::make('goToRegisterPayment')
                        ->label('Registra pagamento')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->action(fn() => $this->mountAction('registerPayment', arguments: ['appointmentId' => $appointmentId])),
                ];
            })
            ->action(function (array $data, Action $action): void {
                $appointmentId = $data['appointment_id'] ?? $action->getArguments()['appointmentId'];
                Appointment::findOrFail($appointmentId)->update(['status' => $data['status']]);
            });
    }

    public function registerPaymentAction(): Action
    {
        return Action::make('registerPayment')
            ->label('Registra pagamento')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $appointment = Appointment::find($action->getArguments()['appointmentId']);
                $schema?->fill(['amount' => $appointment?->final_price]);
            })
            ->schema([
                Select::make('method')
                    ->label('Metodo di pagamento')
                    ->options([
                        'cash' => 'Contanti',
                        'pos'  => 'POS (carta)',
                    ])
                    ->required(),
                TextInput::make('amount')
                    ->label('Importo (€)')
                    ->numeric()
                    ->rules(['numeric', 'min:0.01'])
                    ->required(),
            ])
            ->authorize(fn(Action $action) => $this->authorizeAppointmentAccess($action))
            ->action(function (array $data, Action $action): void {
                $appointmentId = $action->getArguments()['appointmentId'];
                try {
                    app(PaymentService::class)->recordInPersonPayment(
                        $appointmentId,
                        $data['method'],
                        (float) $data['amount']
                    );
                } catch (BookingException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();

                    $this->halt();
                }
            });
    }

    #[On('calendar-filters-updated')]
    public function handleFiltersUpdated(array $staff, array $status, array $service, array $customer): void
    {
        $this->filterStaff    = $staff;
        $this->filterStatus   = $status;
        $this->filterService  = $service;
        $this->filterCustomer = $customer;
        $this->dispatch('filament-fullcalendar--refresh');
    }
}
