<?php

namespace App\Filament\Widgets;

use App\Exceptions\BookingException;
use App\Exceptions\RescheduleConflictException;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Services\AppointmentRescheduleService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendarWidget extends FullCalendarWidget
{
    protected ?string $pollingInterval = null;

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
                'right'  => 'dayGridMonth,timeGridWeek,resourceTimeGridDay,listWeek',
            ],
            'buttonText' => [
                'dayGridMonth'       => 'Mese',
                'timeGridWeek'       => 'Settimana',
                'resourceTimeGridDay' => 'Giorno',
                'listWeek'           => 'Lista',
                'today'              => 'Oggi',
            ],
            'locale'           => 'it',
            'eventDisplay'     => 'block',
            'displayEventTime' => true,
            'displayEventEnd'  => true,
            'eventTimeFormat'  => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
            'dayMaxEvents'     => true,
            'contentHeight'    => 'auto',
            'slotMinTime'      => '07:00:00',
            'slotMaxTime'      => '21:00:00',
            'allDaySlot'       => false,
            'resources'         => $this->getStaffResources(),
            'resourceAreaWidth' => '0px',
            'editable'              => true,
            'eventStartEditable'    => true,
            'eventDurationEditable' => false,
            'eventResourceEditable' => false,
            'views' => [
                'dayGridMonth' => ['editable' => false],
                'listWeek'     => ['editable' => false],
            ],
        ];
    }

    private function getStaffResources(): array
    {
        return User::role(['admin', 'staff'])
            ->where('business_id', auth()->user()->business_id)
            ->orderBy('name')
            ->get()
            ->map(fn(User $u) => [
                'id'    => (string) $u->id,
                'title' => $u->name,
                'extendedProps' => [
                    'avatar' => $u->getFirstMediaUrl('avatar') ?: null,
                    'color'  => $u->calendar_color,
                ],
            ])
            ->toArray();
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $query = Appointment::query()
            ->with(['user', 'staff'])
            ->whereBetween('scheduled_date', [$fetchInfo['start'], $fetchInfo['end']]);

        $user = Filament::auth()->user();

        if ($user->isAdmin()) {
            if (!empty($this->filterStaff)) {
                $query->whereIn('staff_id', $this->filterStaff);
            }
        } elseif ($user->isStaff()) {
            if ($user->can('appointments.view_all')) {
                if (!empty($this->filterStaff)) {
                    $query->whereIn('staff_id', $this->filterStaff);
                }
            } else {
                $query->where('staff_id', $user->id);
            }
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

        $appointmentEvents = $appointments->map(function ($appointment) use ($services) {
            $duration = collect($appointment->service_ids ?? [])
                ->sum(fn($id) => $services->get($id)?->duration_minutes ?? 30);

            $serviceNames = collect($appointment->service_ids ?? [])
                ->map(fn($id) => $services->get($id)?->name)
                ->filter()
                ->implode(', ');

            return [
                'id'              => $appointment->id,
                'resourceId'      => (string) $appointment->staff_id,
                'title'           => $appointment->user->name . ' – ' . $serviceNames,
                'start'           => $appointment->scheduled_date->toIso8601String(),
                'end'             => $appointment->scheduled_date->copy()->addMinutes($duration)->toIso8601String(),
                'backgroundColor' => $this->staffColor($appointment),
                'borderColor'     => $this->staffColor($appointment),
                'classNames'      => ['fc-appt-' . $appointment->status],
                'extendedProps'   => ['status' => $appointment->status],
                'editable'         => in_array($appointment->status, ['pending', 'confirmed']),
                'startEditable'    => in_array($appointment->status, ['pending', 'confirmed']),
                'durationEditable' => false,
                'resourceEditable' => false,
            ];
        })->toArray();

        $blockoutEvents = StaffBlockout::query()
            ->where('business_id', $user->business_id)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where('start_date', '<=', $fetchInfo['end'])
            ->where('end_date', '>=', $fetchInfo['start'])
            ->get()
            ->map(fn ($blockout) => [
                'id'              => 'blockout-' . $blockout->id,
                'resourceId'      => (string) $blockout->user_id,
                'title'           => $blockout->reason ?? 'Slot bloccato',
                'start'           => $blockout->start_date->format('Y-m-d') . 'T' . $blockout->start_time,
                'end'             => $blockout->end_date->format('Y-m-d') . 'T' . $blockout->end_time,
                'display'         => 'background',
                'backgroundColor' => '#ef4444',
                'extendedProps'   => ['type' => 'blockout'],
                'editable'         => false,
                'startEditable'    => false,
                'durationEditable' => false,
                'resourceEditable' => false,
            ])
            ->toArray();

        return array_merge($appointmentEvents, $blockoutEvents);
    }

    private function authorizeAppointmentAccess(Action $action): bool
    {
        $user          = auth()->user();
        $appointmentId = $action->getArguments()['appointmentId'] ?? null;

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff() && $appointmentId) {
            if ($user->can('appointments.view_all')) {
                return Appointment::where('id', $appointmentId)->exists();
            }
            return Appointment::where('id', $appointmentId)
                ->where('staff_id', $user->id)
                ->exists();
        }

        return false;
    }

    private function authorizeAppointmentEdit(Action $action): bool
    {
        $user          = auth()->user();
        $appointmentId = $action->getArguments()['appointmentId'] ?? null;

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff() && $appointmentId && $user->can('appointments.edit')) {
            if ($user->can('appointments.view_all')) {
                return Appointment::where('id', $appointmentId)->exists();
            }
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
            '#3B82F6',
            '#10B981',
            '#F59E0B',
            '#EF4444',
            '#8B5CF6',
            '#EC4899',
            '#14B8A6',
            '#F97316',
        ];

        return $palette[$appointment->staff_id % count($palette)];
    }

    public function onEventClick(array $event): void
    {
        if (($event['extendedProps']['type'] ?? null) === 'blockout') {
            return;
        }

        $status = $event['extendedProps']['status'] ?? null;

        if ($status === 'completed') {
            $this->mountAction('viewAppointment', arguments: ['appointmentId' => $event['id']]);
        } else {
            $this->mountAction('changeStatus', arguments: ['appointmentId' => $event['id']]);
        }
    }

    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource = null, ?array $newResource = null): bool
    {
        if (str_starts_with((string) ($event['id'] ?? ''), 'blockout-')) {
            return true;
        }

        $user = Filament::auth()->user();

        $appointment = Appointment::where('id', (int) $event['id'])
            ->where('business_id', $user?->business_id)
            ->first();

        if (! $appointment) {
            return true;
        }

        // Guard cambio staff — non supportato in v1
        $eventResourceId = $event['resourceId'] ?? null;
        if ($eventResourceId !== null && (int) $eventResourceId !== (int) $appointment->staff_id) {
            Notification::make()
                ->title('Il cambio operatore tramite drag non è supportato.')
                ->danger()
                ->send();

            return true;
        }

        try {
            app(AppointmentRescheduleService::class)->reschedule(
                $appointment,
                Carbon::parse($event['start']),
                $user,
            );
        } catch (RescheduleConflictException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            // saade/filament-fullcalendar v4: return true → JS chiama revert(), false → mantiene posizione
            return true;
        }

        Notification::make()
            ->title('Appuntamento spostato alle ' . Carbon::parse($event['start'])->format('H:i'))
            ->success()
            ->actions([
                Action::make('undo')
                    ->label('Annulla')
                    ->dispatch('undo-reschedule', [
                        'appointmentId'    => $appointment->id,
                        'previousDateTime' => $oldEvent['start'],
                    ]),
            ])
            ->send();

        $this->dispatch('filament-fullcalendar--refresh');

        return false;
    }

    #[On('undo-reschedule')]
    public function undoReschedule(int $appointmentId, string $previousDateTime): void
    {
        $user = Filament::auth()->user();

        $appointment = Appointment::where('id', $appointmentId)
            ->where('business_id', $user?->business_id)
            ->first();

        if (! $appointment) {
            Notification::make()
                ->title('Appuntamento non trovato.')
                ->danger()
                ->send();

            return;
        }

        try {
            $parsedPrevious = Carbon::createFromFormat(\DateTime::ATOM, $previousDateTime);
        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            Notification::make()
                ->title('Data non valida.')
                ->danger()
                ->send();

            return;
        }

        try {
            app(AppointmentRescheduleService::class)->reschedule(
                $appointment,
                $parsedPrevious,
                $user,
            );

            Notification::make()
                ->title('Spostamento annullato.')
                ->success()
                ->send();
        } catch (RescheduleConflictException $e) {
            Notification::make()
                ->title('Non è più possibile ripristinare l\'orario precedente.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->dispatch('filament-fullcalendar--refresh');
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
                    'appointment_id'        => $appointment->id,
                    'customer_name'         => $appointment->user->name,
                    'staff_name'            => $appointment->staff->name,
                    'scheduled_date'        => $appointment->scheduled_date->format('d/m/Y H:i'),
                    'services'              => $appointment->services_label,
                    'status'                => $appointment->status,
                    'has_completed_payment' => $hasCompletedPayment,
                    'payment_amount'        => $hasCompletedPayment ? null : $appointment->final_price,
                    'customer_confirmed'    => (bool) $appointment->customer_confirmed_at,
                    'notes'                 => $appointment->notes ?? '',
                ]);
            })
            ->schema([
                Hidden::make('appointment_id'),
                Hidden::make('has_completed_payment'),
                Hidden::make('customer_confirmed'),
                Hidden::make('customer_name'),
                Hidden::make('staff_name'),
                Hidden::make('scheduled_date'),
                Hidden::make('services'),
                Hidden::make('notes'),
                Html::make(fn(Get $get): string => sprintf(
                    '<div class="grid grid-cols-2 gap-4 text-sm rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 p-4 mb-1">
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cliente</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Staff</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Data e ora</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Servizi</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        %s
                    </div>',
                    e($get('customer_name')),
                    e($get('staff_name')),
                    e($get('scheduled_date')),
                    e($get('services')),
                    $get('notes') ? sprintf('<div class="col-span-2"><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Note</p><p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">%s</p></div>', e($get('notes'))) : ''
                )),
                Html::make(
                    fn(Get $get): string => (bool) $get('customer_confirmed')
                        ? '<p class="text-sm font-medium text-success-600 dark:text-success-400 -mt-1 mb-1">✓ Presenza confermata via email</p>'
                        : ''
                ),
                Section::make('Aggiorna prenotazione')
                    ->schema([
                        Select::make('status')
                            ->label('Stato')
                            ->options([
                                'pending'   => 'In attesa',
                                'confirmed' => 'Confermato',
                                'completed' => 'Completato',
                                'cancelled' => 'Annullato',
                            ])
                            ->in(['pending', 'confirmed', 'completed', 'cancelled'])
                            ->required()
                            ->rules(fn(Get $get): array => [
                                function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    if (
                                        $value === 'completed'
                                        && ! (bool) $get('has_completed_payment')
                                        && empty($get('payment_method'))
                                    ) {
                                        $fail('Per completare è necessario registrare un pagamento.');
                                    }
                                },
                            ])
                            ->columnSpanFull(),
                        Select::make('payment_method')
                            ->label('Metodo di pagamento')
                            ->options([
                                'cash' => 'Contanti',
                                'pos'  => 'POS (carta)',
                            ])
                            ->hidden(fn(Get $get): bool => (bool) $get('has_completed_payment')),
                        TextInput::make('payment_amount')
                            ->label('Importo (€)')
                            ->numeric()
                            ->rules(['nullable', 'numeric', 'min:0.01'])
                            ->required(fn(Get $get): bool => filled($get('payment_method')))
                            ->hidden(fn(Get $get): bool => (bool) $get('has_completed_payment')),
                        Html::make(
                            fn(Get $get): string => (bool) $get('has_completed_payment')
                                ? '<p class="text-sm font-medium text-success-600 dark:text-success-400">✓ Pagamento già registrato</p>'
                                : ''
                        ),
                    ])
                    ->columns(2),
            ])
            ->authorize(fn(Action $action) => $this->authorizeAppointmentEdit($action))
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

                $this->dispatch('filament-fullcalendar--refresh');
            });
    }

    public function viewAppointmentAction(): Action
    {
        return Action::make('viewAppointment')
            ->label('Dettagli prenotazione')
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                $arguments   = $action->getArguments();
                $appointment    = Appointment::with(['user', 'staff'])->find($arguments['appointmentId']);
                $completedPayment = \App\Models\Payment::where('appointment_id', $appointment->id)
                    ->where('status', 'completed')
                    ->latest()
                    ->first();

                $paymentRow = '';
                if ($completedPayment) {
                    $methods    = ['cash' => 'Contanti', 'pos' => 'POS (carta)', 'stripe' => 'Stripe'];
                    $method     = $methods[$completedPayment->payment_method] ?? $completedPayment->payment_method;
                    $amount     = number_format((float) $completedPayment->amount, 2, ',', '.');
                    $paymentRow = sprintf(
                        '<div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Pagamento</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>',
                        e($method . ' – €' . $amount)
                    );
                }

                $notesRow = $appointment->notes
                    ? sprintf(
                        '<div class="col-span-2"><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Note</p><p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">%s</p></div>',
                        e($appointment->notes)
                    )
                    : '';

                $html = sprintf(
                    '<div class="grid grid-cols-2 gap-4 text-sm rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 p-4 mb-1">
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Cliente</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Staff</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Data e ora</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Servizi</p><p class="font-semibold text-gray-900 dark:text-white">%s</p></div>
                        <div><p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Stato</p><p class="font-semibold text-gray-900 dark:text-white">Completato</p></div>
                        %s
                        %s
                    </div>',
                    e($appointment->user->name),
                    e($appointment->staff->name),
                    e($appointment->scheduled_date->format('d/m/Y H:i')),
                    e($appointment->services_label),
                    $paymentRow,
                    $notesRow
                );

                $schema?->fill(['content' => $html]);
            })
            ->schema([
                Hidden::make('content'),
                Html::make(fn(Get $get): string => (string) $get('content')),
            ])
            ->authorize(fn(Action $action) => $this->authorizeAppointmentAccess($action))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi');
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
