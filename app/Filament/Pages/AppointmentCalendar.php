<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppointmentCalendarWidget;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\SalonProfile;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Models\SystemSetting;
use App\Mail\WelcomeCredentialsMail;
use App\Services\Booking\SlotCalculationService;
use App\Services\WalkInService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

    private static function timeOptions(?string $date = null): array
    {
        $ranges = $date ? self::salonRangesForDate($date) : [];

        if (empty($ranges)) {
            $ranges = [['05:00', '23:30']];
        }

        $options = [];
        foreach ($ranges as [$from, $to]) {
            [$fh, $fm] = array_map('intval', explode(':', $from));
            [$th, $tm] = array_map('intval', explode(':', $to));
            for ($mins = $fh * 60 + $fm; $mins <= $th * 60 + $tm; $mins += 30) {
                $time           = sprintf('%02d:%02d', intdiv($mins, 60), $mins % 60);
                $options[$time] = $time;
            }
        }

        return $options;
    }

    private static function salonRangesForDate(string $date): array
    {
        static $dayKeys = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
        $dayKey = $dayKeys[Carbon::parse($date)->dayOfWeek];
        $hours  = SalonProfile::current()?->opening_hours ?? [];
        $day    = $hours[$dayKey] ?? [];

        return match ($day['type'] ?? 'closed') {
            'continuous' => [[$day['open_time'],    $day['close_time']]],
            'split'      => [[$day['morning_open'], $day['morning_close']], [$day['afternoon_open'], $day['afternoon_close']]],
            default      => [],
        };
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
                ->label('Nuovo appuntamento')
                ->icon('heroicon-o-user-plus')
                ->slideOver()
                ->visible(fn() => Filament::auth()->user()?->isAdmin()
                    || (Filament::auth()->user()?->isStaff() && Filament::auth()->user()?->can('appointments.create')))
                ->form([
                    Grid::make(1)
                        ->extraAttributes(['class' => '!gap-y-4'])
                        ->schema([
                            // Riga 1: Operatore + Servizi
                            Grid::make(['default' => 1, 'sm' => 2])->schema([
                                Select::make('staff_id')
                                    ->label('Operatore')
                                    ->options(fn() => User::role('staff')
                                        ->where('business_id', Filament::auth()->user()?->business_id)
                                        ->orderBy('name')
                                        ->pluck('name', 'id'))
                                    ->required()
                                    ->default($user?->isStaff() ? $user->id : null)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('service_ids', []);
                                        $set('scheduled_time', null);
                                    }),

                                Select::make('service_ids')
                                    ->label('Servizi')
                                    ->options(function (Get $get): array {
                                        $staffId = (int) ($get('staff_id') ?? 0);
                                        if (! $staffId) {
                                            return [];
                                        }
                                        $staff = User::find($staffId);
                                        return $staff
                                            ? $staff->services()->where('active', true)->orderBy('services.name')->pluck('services.name', 'services.id')->toArray()
                                            : [];
                                    })
                                    ->placeholder('Seleziona prima un operatore')
                                    ->multiple()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn(Set $set) => $set('scheduled_time', null)),
                            ]),

                            // Separatore visivo tra sezione "chi" e sezione "quando"
                            Html::make(fn() => '<div class="h-px bg-gray-100 dark:bg-white/10"></div>'),

                            // Riga 2: Data + Ora
                            Grid::make(['default' => 1, 'sm' => 2])->schema([
                                DatePicker::make('scheduled_date')
                                    ->label('Data')
                                    ->required()
                                    ->default(today())
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->live()
                                    ->disabledDates(function (Get $get): array {
                                        $staffId = (int) ($get('staff_id') ?? 0);
                                        if (! $staffId) {
                                            return [];
                                        }
                                        $workingDays = AvailabilityRule::where('user_id', $staffId)
                                            ->where('is_available', true)
                                            ->pluck('day_of_week')
                                            ->toArray();
                                        if (empty($workingDays)) {
                                            return [];
                                        }
                                        $disabled = [];
                                        $cursor   = now()->startOfDay();
                                        for ($i = 0; $i < 90; $i++) {
                                            if (! in_array($cursor->dayOfWeek, $workingDays)) {
                                                $disabled[] = $cursor->format('Y-m-d');
                                            }
                                            $cursor->addDay();
                                        }
                                        return $disabled;
                                    })
                                    ->afterStateUpdated(fn(Set $set) => $set('scheduled_time', null)),

                                Select::make('scheduled_time')
                                    ->label('Ora')
                                    ->required()
                                    ->options(function (Get $get): array {
                                        $date    = $get('scheduled_date');
                                        $staffId = (int) ($get('staff_id') ?? 0);

                                        if (! $date || ! $staffId) {
                                            return [];
                                        }

                                        $staff = User::find($staffId);
                                        if (! $staff) {
                                            return [];
                                        }

                                        $granularity = SystemSetting::getSlotGranularity();
                                        $serviceIds  = array_filter(array_map('intval', (array) ($get('service_ids') ?? [])));
                                        $svc         = app(SlotCalculationService::class);
                                        $duration    = ! empty($serviceIds) ? $svc->calculateTotalDuration($serviceIds) : 0;
                                        $checkLen    = max($duration, $granularity);

                                        $ranges  = $svc->getFreeRangesForOperator($staff, Carbon::parse($date));
                                        $options = [];
                                        foreach ($ranges as $range) {
                                            $cursor = $range['start']->copy();
                                            while ($cursor->copy()->addMinutes($checkLen) <= $range['end']) {
                                                $time           = $cursor->format('H:i');
                                                $options[$time] = $time;
                                                $cursor->addMinutes($granularity);
                                            }
                                        }
                                        return $options;
                                    })
                                    ->placeholder(fn(Get $get) => ! $get('staff_id')
                                        ? 'Seleziona prima un operatore'
                                        : (! $get('scheduled_date') ? 'Seleziona prima una data' : '—'))
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->validationMessages(['in' => 'Seleziona un orario disponibile.']),
                            ]),

                            // Hint disponibilità orario
                            Html::make(function (Get $get): string {
                                $date    = $get('scheduled_date');
                                $staffId = (int) ($get('staff_id') ?? 0);
                                $time    = $get('scheduled_time');

                                if (! $date || ! $staffId || ! $time) {
                                    return '';
                                }

                                $staff = User::find($staffId);
                                if (! $staff) {
                                    return '';
                                }

                                $svc      = app(SlotCalculationService::class);
                                $dateC    = Carbon::parse($date);
                                $datetime = Carbon::parse("$date $time");

                                $inWork = collect($svc->getWorkRangesForOperator($staff, $dateC))->contains(
                                    fn($r) => $r['start'] <= $datetime && $r['end'] > $datetime
                                );

                                if (! $inWork) {
                                    return '<p class="text-sm font-medium text-danger-600 dark:text-danger-400">Operatore non disponibile in questo orario.</p>';
                                }

                                $inFree = collect($svc->getFreeRangesForOperator($staff, $dateC))->contains(
                                    fn($r) => $r['start'] <= $datetime && $r['end'] > $datetime
                                );

                                if (! $inFree) {
                                    $conflict = Appointment::withoutGlobalScope('business')
                                        ->with('user')
                                        ->where('staff_id', $staffId)
                                        ->where('business_id', $staff->business_id)
                                        ->whereIn('status', ['pending', 'confirmed'])
                                        ->whereDate('scheduled_date', $date)
                                        ->get()
                                        ->first(function ($appt) use ($datetime, $svc) {
                                            $start    = Carbon::parse($appt->scheduled_date);
                                            $ids      = is_array($appt->service_ids) ? $appt->service_ids : [];
                                            $duration = ! empty($ids) ? $svc->calculateTotalDuration($ids) : 30;
                                            return $start <= $datetime && $start->copy()->addMinutes($duration) > $datetime;
                                        });

                                    if ($conflict) {
                                        $name  = e($conflict->user?->name ?? 'un cliente');
                                        $start = Carbon::parse($conflict->scheduled_date)->format('H:i');
                                        $ids   = is_array($conflict->service_ids) ? $conflict->service_ids : [];
                                        $end   = Carbon::parse($conflict->scheduled_date)
                                            ->addMinutes(! empty($ids) ? $svc->calculateTotalDuration($ids) : 30)
                                            ->format('H:i');
                                        return "<p class=\"text-sm font-medium text-danger-600 dark:text-danger-400\">Occupato con {$name} dalle {$start} alle {$end}.</p>";
                                    }

                                    return '<p class="text-sm font-medium text-danger-600 dark:text-danger-400">Operatore non disponibile in questo orario.</p>';
                                }

                                return '';
                            })
                                ->hidden(function (Get $get): bool {
                                    $date    = $get('scheduled_date');
                                    $staffId = (int) ($get('staff_id') ?? 0);
                                    $time    = $get('scheduled_time');
                                    if (! $date || ! $staffId || ! $time) {
                                        return true;
                                    }
                                    $staff = User::find($staffId);
                                    if (! $staff) {
                                        return true;
                                    }
                                    $svc      = app(SlotCalculationService::class);
                                    $dateC    = Carbon::parse($date);
                                    $datetime = Carbon::parse("$date $time");
                                    $inWork   = collect($svc->getWorkRangesForOperator($staff, $dateC))->contains(
                                        fn($r) => $r['start'] <= $datetime && $r['end'] > $datetime
                                    );
                                    if (! $inWork) {
                                        return false;
                                    }
                                    return collect($svc->getFreeRangesForOperator($staff, $dateC))->contains(
                                        fn($r) => $r['start'] <= $datetime && $r['end'] > $datetime
                                    );
                                }),

                            // Riepilogo servizi: orario, durata, totale
                            Html::make(function (Get $get): string {
                                $ids  = $get('service_ids') ?? [];
                                $time = $get('scheduled_time');
                                if (empty($ids) || ! $time) {
                                    return '';
                                }
                                $services      = Service::whereIn('id', $ids)->get();
                                $duration      = $services->sum('duration_minutes');
                                $price         = $services->sum('price');
                                $hours         = intdiv($duration, 60);
                                $minutes       = $duration % 60;
                                $durationLabel = $hours > 0
                                    ? $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'min' : '')
                                    : $minutes . ' min';
                                $end           = Carbon::createFromFormat('H:i', $time)->addMinutes($duration);
                                $sep           = '<div class="w-px self-stretch bg-gray-200 dark:bg-white/10 hidden sm:block"></div>';

                                return '
                                    <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/[0.03] px-4 py-3 flex flex-wrap items-start gap-x-6 gap-y-2">
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Orario</div>
                                            <div class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-100">' . $time . ' - ' . $end->format('H:i') . '</div>
                                        </div>'
                                    . $sep . '
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Durata</div>
                                            <div class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-100">' . $durationLabel . '</div>
                                        </div>'
                                    . $sep . '
                                        <div>
                                            <div class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Totale</div>
                                            <div class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-100">€' . number_format((float) $price, 2, ',', '.') . '</div>
                                        </div>
                                    </div>';
                            })->hidden(fn(Get $get): bool => empty($get('service_ids')) || ! $get('scheduled_time')),

                            Select::make('user_id')
                                ->label('Cliente')
                                ->options(fn() => User::role('customer')
                                    ->where('business_id', Filament::auth()->user()?->business_id)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn($u) => [
                                        $u->id => str_ends_with($u->email, '@noreply.local')
                                            ? $u->name
                                            : $u->name . ' (' . $u->email . ')',
                                    ]))
                                ->searchable()
                                ->required()
                                ->createOptionModalHeading('Crea nuovo cliente')
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Nome')
                                        ->required(),
                                    TextInput::make('email')
                                        ->label('Email')
                                        ->email()
                                        ->required()
                                        ->unique(
                                            table: 'users',
                                            column: 'email',
                                            modifyRuleUsing: fn($rule) => $rule->where('business_id', Filament::auth()->user()?->business_id),
                                        ),
                                    Toggle::make('send_credentials')
                                        ->label('Invia credenziali di accesso')
                                        ->default(false),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $user = app(WalkInService::class)
                                        ->createInlineCustomer(
                                            $data['name'],
                                            $data['email'],
                                            Filament::auth()->user()?->business_id,
                                        );

                                    if (! empty($data['send_credentials'])) {
                                        $tempPassword = Str::random(10);
                                        $user->update(['password' => Hash::make($tempPassword)]);
                                        Mail::to($user->email)->send(new WelcomeCredentialsMail($user, $tempPassword));
                                    }

                                    return $user->id;
                                }),

                            Textarea::make('notes')
                                ->label('Note')
                                ->rows(2),
                        ]),
                ])
                ->action(function (array $data): void {
                    $scheduledDate = $data['scheduled_date'] . ' ' . $data['scheduled_time'];

                    Appointment::create([
                        'business_id'    => Filament::auth()->user()?->business_id,
                        'user_id'        => $data['user_id'],
                        'staff_id'       => $data['staff_id'],
                        'service_ids'    => $data['service_ids'],
                        'scheduled_date' => $scheduledDate,
                        'status'         => 'confirmed',
                        'is_walk_in'     => true,
                        'notes'          => $data['notes'] ?? null,
                    ]);

                    // Se il cliente ha email placeholder (@noreply.local), non inviare
                    // notifiche automatiche — l'observer o job devono controllare
                    // $appointment->user->hasPlaceholderEmail() prima di inviare email.

                    Notification::make()
                        ->title('Appuntamento creato')
                        ->success()
                        ->send();

                    $this->dispatch('filament-fullcalendar--refresh');
                }),
            Action::make('blockSlot')
                ->label('Blocca slot')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->slideOver()
                ->visible(fn() => Filament::auth()->user()?->isAdmin())
                ->form([
                    DatePicker::make('date')
                        ->label('Data')
                        ->required()
                        ->default(today())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->live(),

                    Select::make('staff_id')
                        ->label('Operatore')
                        ->options(fn() => User::role('staff')
                            ->where('business_id', Filament::auth()->user()?->business_id)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->required()
                        ->default(Filament::auth()->user()?->isStaff()
                            ? Filament::auth()->user()?->id
                            : null)
                        ->visible(fn() => Filament::auth()->user()?->isAdmin()
                            || Filament::auth()->user()?->can('appointments.view_all')),

                    Select::make('start_time')
                        ->label('Dalle')
                        ->options(fn(Get $get) => self::timeOptions($get('date')))
                        ->required(),

                    Select::make('end_time')
                        ->label('Alle')
                        ->options(fn(Get $get) => self::timeOptions($get('date')))
                        ->required()
                        ->rules(fn(Get $get): array => [
                            function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                $start = $get('start_time');
                                if ($value && $start && $value <= $start) {
                                    $fail('L\'ora di fine deve essere dopo l\'ora di inizio.');
                                }
                            },
                        ]),

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

                    $this->dispatch('filament-fullcalendar--refresh');
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
