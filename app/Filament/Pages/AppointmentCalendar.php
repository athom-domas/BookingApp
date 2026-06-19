<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppointmentCalendarWidget;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Services\WalkInService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class AppointmentCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.appointment-calendar';

    protected static ?string $navigationLabel = 'Calendario';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Calendario Appuntamenti';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user?->isAdmin() || $user?->isStaff() ?? false;
    }

    public array $filterStaff    = [];
    public array $filterStatus   = [];
    public array $filterService  = [];
    public array $filterCustomer = [];

    protected function getHeaderActions(): array
    {
        $user = Filament::auth()->user();

        return [
            Action::make('createWalkin')
                ->label('Walk-in')
                ->icon('heroicon-o-user-plus')
                ->slideOver()
                ->visible(fn () => Filament::auth()->user()?->isAdmin()
                    || Filament::auth()->user()?->isStaff())
                ->form([
                    DateTimePicker::make('scheduled_date')
                        ->label('Data e ora')
                        ->required()
                        ->seconds(false)
                        ->default(now()),

                    Select::make('staff_id')
                        ->label('Operatore')
                        ->options(fn () => User::role(['admin', 'staff'])
                            ->where('business_id', Filament::auth()->user()?->business_id)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->required()
                        ->default($user?->isStaff() ? $user->id : null),

                    Select::make('service_ids')
                        ->label('Servizi')
                        ->options(fn () => Service::where('business_id', Filament::auth()->user()?->business_id)
                            ->where('active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->multiple()
                        ->required(),

                    Select::make('user_id')
                        ->label('Cliente')
                        ->options(fn () => User::role('customer')
                            ->where('business_id', Filament::auth()->user()?->business_id)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Nome')
                                ->required(),
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->unique(
                                    table: 'users',
                                    column: 'email',
                                    modifyRuleUsing: fn ($rule) => $rule->where('business_id', Filament::auth()->user()?->business_id),
                                ),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            return app(WalkInService::class)
                                ->createInlineCustomer(
                                    $data['name'],
                                    $data['email'] ?: null,
                                    Filament::auth()->user()?->business_id,
                                )
                                ->id;
                        }),

                    Textarea::make('notes')
                        ->label('Note')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    Appointment::create([
                        'business_id'    => Filament::auth()->user()?->business_id,
                        'user_id'        => $data['user_id'],
                        'staff_id'       => $data['staff_id'],
                        'service_ids'    => $data['service_ids'],
                        'scheduled_date' => $data['scheduled_date'],
                        'status'         => 'confirmed',
                        'is_walk_in'     => true,
                        'notes'          => $data['notes'] ?? null,
                    ]);

                    // Se il cliente ha email placeholder (@noreply.local), non inviare
                    // notifiche automatiche — l'observer o job devono controllare
                    // $appointment->user->hasPlaceholderEmail() prima di inviare email.

                    Notification::make()
                        ->title('Walk-in creato')
                        ->success()
                        ->send();

                    $this->dispatch('filament-fullcalendar--refresh')
                        ->to(AppointmentCalendarWidget::class);
                }),
            Action::make('blockSlot')
                ->label('Blocca slot')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->slideOver()
                ->visible(fn () => Filament::auth()->user()?->isAdmin()
                    || Filament::auth()->user()?->isStaff())
                ->form([
                    DatePicker::make('date')
                        ->label('Data')
                        ->required()
                        ->default(today()),

                    Select::make('staff_id')
                        ->label('Operatore')
                        ->options(fn () => User::role(['admin', 'staff'])
                            ->where('business_id', Filament::auth()->user()?->business_id)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->required()
                        ->default(Filament::auth()->user()?->isStaff()
                            ? Filament::auth()->user()?->id
                            : null)
                        ->visible(fn () => Filament::auth()->user()?->isAdmin()
                            || Filament::auth()->user()?->can('appointments.view_all')),

                    TimePicker::make('start_time')
                        ->label('Dalle')
                        ->required()
                        ->seconds(false),

                    TimePicker::make('end_time')
                        ->label('Alle')
                        ->required()
                        ->seconds(false)
                        ->after('start_time'),

                    TextInput::make('reason')
                        ->label('Motivo')
                        ->placeholder('es. Pausa pranzo'),
                ])
                ->action(function (array $data): void {
                    $user = Filament::auth()->user();
                    $staffId = ($user?->isAdmin() || $user?->can('appointments.view_all'))
                        ? ($data['staff_id'] ?? $user?->id)
                        : $user?->id;

                    if (! $staffId) {
                        return;
                    }

                    StaffBlockout::create([
                        'business_id' => Filament::auth()->user()?->business_id,
                        'user_id'     => $staffId,
                        'start_date'  => $data['date'],
                        'end_date'    => $data['date'],
                        'start_time'  => $data['start_time'],
                        'end_time'    => $data['end_time'],
                        'reason'      => $data['reason'] ?? null,
                    ]);

                    Notification::make()
                        ->title('Slot bloccato')
                        ->success()
                        ->send();

                    $this->dispatch('filament-fullcalendar--refresh')
                        ->to(AppointmentCalendarWidget::class);
                }),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $user       = Filament::auth()->user();
        $businessId = $user?->business_id;
        $fields     = [];

        if ($user?->isAdmin() || ($user?->isStaff() && $user->can('appointments.view_all'))) {
            $fields[] = Select::make('filterStaff')
                ->label('Staff')
                ->options(fn() => User::role('staff')->where('business_id', $businessId)->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Tutti')
                ->multiple()
                ->live();
        }

        $fields[] = Select::make('filterStatus')
            ->label('Stato')
            ->options([
                'pending'   => 'In attesa',
                'confirmed' => 'Confermato',
                'completed' => 'Completato',
                'cancelled' => 'Annullato',
            ])
            ->placeholder('Tutti')
            ->multiple()
            ->live();

        $fields[] = Select::make('filterService')
            ->label('Servizio')
            ->options(fn() => Service::orderBy('name')->pluck('name', 'id'))
            ->placeholder('Tutti')
            ->multiple()
            ->live();

        $fields[] = Select::make('filterCustomer')
            ->label('Cliente')
            ->options(fn() => User::role('customer')->where('business_id', $businessId)->orderBy('name')->pluck('name', 'id'))
            ->placeholder('Tutti')
            ->multiple()
            ->live();

        return $schema->schema($fields)->columns(4);
    }

    public function updatedFilterStaff(): void
    {
        $this->dispatchFilters();
    }
    public function updatedFilterStatus(): void
    {
        $this->dispatchFilters();
    }
    public function updatedFilterService(): void
    {
        $this->dispatchFilters();
    }
    public function updatedFilterCustomer(): void
    {
        $this->dispatchFilters();
    }

    private function dispatchFilters(): void
    {
        $this->dispatch(
            'calendar-filters-updated',
            staff: $this->filterStaff,
            status: $this->filterStatus,
            service: $this->filterService,
            customer: $this->filterCustomer,
        )->to(AppointmentCalendarWidget::class);
    }

    protected function getFooterWidgets(): array
    {
        return [AppointmentCalendarWidget::class];
    }
}
