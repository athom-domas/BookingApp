<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SystemSettings extends Page
{
    protected string $view = 'filament.pages.system-settings';

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Configurazioni';
    protected static ?int $navigationSort = 6;

    public ?array $data = [];

    public function mount(): void
    {
        $setting = SystemSetting::current();
        $this->form->fill([
            'slot_granularity_minutes'    => $setting->slot_granularity_minutes,
            'booking_max_days_ahead'      => $setting->booking_max_days_ahead,
            'cancellation_deadline_hours' => $setting->cancellation_deadline_hours,
            'reminder_count'              => (string) $setting->reminder_count,
            'reminder_1_hours'            => $setting->reminder_1_hours,
            'reminder_2_hours'            => $setting->reminder_2_hours,
            'payment_mode'                => $setting->payment_mode ?? 'both',
            'reviews_enabled'             => $setting->reviews_enabled ?? true,
            'review_request_enabled'      => $setting->review_request_enabled ?? false,
            'review_request_delay_hours'  => $setting->review_request_delay_hours ?? 2,
            'loyalty_enabled'             => $setting->loyalty_enabled ?? false,
            'loyalty_points_per_euro'     => $setting->loyalty_points_per_euro ?? 1,
            'loyalty_reward_threshold'    => $setting->loyalty_reward_threshold ?? 100,
            'loyalty_reward_percentage'   => $setting->loyalty_reward_percentage ?? 10,
            'low_stock_notify_user_ids'   => $setting->low_stock_notify_user_ids ?? [],
            'order_notify_user_ids'       => $setting->order_notify_user_ids ?? [],
            'follow_up_reminders_enabled' => $setting->follow_up_reminders_enabled ?? false,
            'follow_up_reminder_days'     => $setting->follow_up_reminder_days ?? 30,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $staffOptions = fn () => User::where('business_id', \App\Models\Business::currentId())
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'staff'])->where('guard_name', 'web'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return $schema
            ->schema([
                Section::make('Prenotazioni')
                    ->columns(3)
                    ->schema([
                        Select::make('slot_granularity_minutes')
                            ->label('Granularità slot')
                            ->helperText('Intervallo tra uno slot e l\'altro nel calendario')
                            ->options([5 => '5 min', 10 => '10 min', 15 => '15 min', 20 => '20 min', 30 => '30 min', 60 => '60 min'])
                            ->required(),

                        TextInput::make('booking_max_days_ahead')
                            ->label('Prenotazione max anticipata')
                            ->helperText('Giorni in anticipo consentiti')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->suffix('giorni'),

                        TextInput::make('cancellation_deadline_hours')
                            ->label('Scadenza cancellazione')
                            ->helperText('Ore prima entro cui il cliente può cancellare')
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->suffix('ore'),
                    ]),

                Section::make('Promemoria appuntamento')
                    ->columns(3)
                    ->schema([
                        Select::make('reminder_count')
                            ->label('Numero di promemoria')
                            ->options(['0' => 'Nessuno', '1' => '1 promemoria', '2' => '2 promemoria'])
                            ->required()
                            ->live(),

                        Select::make('reminder_1_hours')
                            ->label('Primo promemoria')
                            ->helperText('Ore prima dell\'appuntamento')
                            ->options([1 => '1 ora', 2 => '2 ore', 4 => '4 ore', 6 => '6 ore', 12 => '12 ore', 24 => '24 ore', 48 => '48 ore'])
                            ->required()
                            ->visible(fn (Get $get): bool => (int) $get('reminder_count') >= 1),

                        Select::make('reminder_2_hours')
                            ->label('Secondo promemoria')
                            ->helperText('Ore prima dell\'appuntamento')
                            ->options([1 => '1 ora', 2 => '2 ore', 4 => '4 ore', 6 => '6 ore', 12 => '12 ore', 24 => '24 ore', 48 => '48 ore'])
                            ->required()
                            ->visible(fn (Get $get): bool => (int) $get('reminder_count') >= 2),
                    ]),

                Section::make('Pagamenti')
                    ->schema([
                        Select::make('payment_mode')
                            ->label('Metodi di pagamento accettati')
                            ->options([
                                'both'     => 'Online (Stripe) e in salone',
                                'online'   => 'Solo online (Stripe)',
                                'in_salon' => 'Solo in salone',
                            ])
                            ->required(),
                    ]),

                Section::make('Sito web e recensioni')
                    ->columns(2)
                    ->schema([
                        Toggle::make('reviews_enabled')
                            ->label('Mostra sezione recensioni')
                            ->helperText('Se disattivato, la sezione recensioni non compare sul sito')
                            ->default(true)
                            ->columnSpanFull(),

                        Toggle::make('review_request_enabled')
                            ->label('Richiesta recensione automatica')
                            ->helperText('Invia un\'email al cliente dopo il completamento dell\'appuntamento con invito a lasciare una recensione')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('review_request_delay_hours')
                            ->label('Ore di attesa prima dell\'invio')
                            ->helperText('L\'email viene inviata dopo questo numero di ore dal completamento')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(72)
                            ->default(2)
                            ->suffix('ore')
                            ->visible(fn (Get $get): bool => (bool) $get('review_request_enabled')),
                    ]),

                Section::make('Programma fedeltà')
                    ->columns(3)
                    ->schema([
                        Toggle::make('loyalty_enabled')
                            ->label('Abilita programma fedeltà')
                            ->helperText('I clienti accumulano punti sulla spesa e sbloccano uno sconto')
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('loyalty_points_per_euro')
                            ->label('Punti per euro speso')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('punti/€')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),

                        TextInput::make('loyalty_reward_threshold')
                            ->label('Punti per lo sconto')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('punti')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),

                        TextInput::make('loyalty_reward_percentage')
                            ->label('Sconto sbloccato')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),
                    ]),

                Section::make('Notifiche')
                    ->columns(2)
                    ->schema([
                        Select::make('low_stock_notify_user_ids')
                            ->label('Scorte basse — notifica a')
                            ->helperText('Email quando le scorte di un prodotto scendono sotto la soglia')
                            ->multiple()
                            ->options($staffOptions),

                        Select::make('order_notify_user_ids')
                            ->label('Ordini ricevuti — notifica a')
                            ->helperText('Email quando un cliente effettua un ordine prodotti')
                            ->multiple()
                            ->options($staffOptions),
                    ]),

                Section::make('Promemoria di follow-up')
                    ->columns(2)
                    ->schema([
                        Toggle::make('follow_up_reminders_enabled')
                            ->label('Abilita promemoria di follow-up')
                            ->helperText('Invia un\'email ai clienti che non prenotano entro N giorni dall\'ultimo appuntamento')
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('follow_up_reminder_days')
                            ->label('Giorni dopo l\'ultimo appuntamento')
                            ->integer()
                            ->minValue(7)
                            ->maxValue(365)
                            ->required()
                            ->suffix('giorni')
                            ->visible(fn (Get $get): bool => (bool) $get('follow_up_reminders_enabled')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['reminder_count'] = (int) $data['reminder_count'];
        SystemSetting::current()->update($data);

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
