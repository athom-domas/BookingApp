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
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
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
            'locale'           => 'it',
            'eventDisplay'     => 'block',
            'displayEventTime' => true,
            'displayEventEnd'  => true,
            'eventTimeFormat'  => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
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
                'backgroundColor' => $this->staffColor($appointment),
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

    private function staffColor(Appointment $appointment): string
    {
        if ($appointment->staff?->calendar_color) {
            return $appointment->staff->calendar_color;
        }

        $palette = [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
            '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
        ];

        return $palette[$appointment->staff_id % count($palette)];
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
                $arguments   = $action->getArguments();
                $appointment = Appointment::with(['user', 'staff', 'payment'])->find($arguments['appointmentId']);

                $hasCompletedPayment = $appointment->payment?->status === 'completed';

                $schema?->fill([
                    'appointment_id'       => $appointment->id,
                    'customer_name'        => $appointment->user->name,
                    'staff_name'           => $appointment->staff->name,
                    'scheduled_date'       => $appointment->scheduled_date->format('d/m/Y H:i'),
                    'services'             => $appointment->services_label,
                    'status'               => $appointment->status,
                    'has_completed_payment'=> $hasCompletedPayment,
                    'payment_amount'       => $hasCompletedPayment ? null : $appointment->final_price,
                ]);
            })
            ->schema([
                Hidden::make('appointment_id'),
                Hidden::make('has_completed_payment'),
                Hidden::make('customer_name'),
                Hidden::make('staff_name'),
                Hidden::make('scheduled_date'),
                Hidden::make('services'),
                Html::make(fn (Get $get): string => sprintf(
                    '<div class="grid grid-cols-2 gap-4 text-sm rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 p-4 mb-1">
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cliente</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Staff</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Data e ora</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Servizi</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                    </div>',
                    e($get('customer_name')),
                    e($get('staff_name')),
                    e($get('scheduled_date')),
                    e($get('services'))
                )),
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
                Select::make('payment_method')
                    ->label('Metodo di pagamento')
                    ->options([
                        'cash' => 'Contanti',
                        'pos'  => 'POS (carta)',
                    ])
                    ->hidden(fn (Get $get): bool => (bool) $get('has_completed_payment')),
                TextInput::make('payment_amount')
                    ->label('Importo (€)')
                    ->numeric()
                    ->rules(['nullable', 'numeric', 'min:0.01'])
                    ->required(fn (Get $get): bool => filled($get('payment_method')))
                    ->hidden(fn (Get $get): bool => (bool) $get('has_completed_payment')),
                Html::make(fn (Get $get): string => (bool) $get('has_completed_payment')
                    ? '<p class="text-sm font-medium text-success-600 dark:text-success-400">✓ Pagamento già registrato</p>'
                    : ''
                ),
            ])
            ->authorize(fn(Action $action) => $this->authorizeAppointmentAccess($action))
            ->action(function (array $data, Action $action): void {
                $appointmentId = $data['appointment_id'] ?? $action->getArguments()['appointmentId'];

                Appointment::findOrFail($appointmentId)->update(['status' => $data['status']]);

                if (empty($data['has_completed_payment']) && !empty($data['payment_method'])) {
                    try {
                        app(PaymentService::class)->recordInPersonPayment(
                            $appointmentId,
                            $data['payment_method'],
                            (float) ($data['payment_amount'] ?? 0)
                        );

                        Notification::make()
                            ->title('Pagamento registrato con successo')
                            ->success()
                            ->send();
                    } catch (BookingException $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();
                    }
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
