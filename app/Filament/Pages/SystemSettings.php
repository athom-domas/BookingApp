<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
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
    protected static string|\UnitEnum|null $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 3;

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
            'loyalty_enabled'             => $setting->loyalty_enabled ?? false,
            'loyalty_points_per_euro'     => $setting->loyalty_points_per_euro ?? 1,
            'loyalty_reward_threshold'    => $setting->loyalty_reward_threshold ?? 100,
            'loyalty_reward_percentage'   => $setting->loyalty_reward_percentage ?? 10,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Calendario e prenotazioni')
                    ->columns(2)
                    ->schema([
                        TextInput::make('slot_granularity_minutes')
                            ->label('Granularità slot (min)')
                            ->helperText('Intervallo tra uno slot e l\'altro nel calendario (es. 15 min → 09:00, 09:15, 09:30…)')
                            ->integer()
                            ->minValue(5)
                            ->maxValue(60)
                            ->required()
                            ->suffix('min'),

                        TextInput::make('booking_max_days_ahead')
                            ->label('Prenotazione massima anticipata')
                            ->helperText('Quanti giorni in anticipo può prenotare un cliente')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required()
                            ->suffix('giorni'),

                        TextInput::make('cancellation_deadline_hours')
                            ->label('Scadenza cancellazione')
                            ->helperText('Entro quante ore prima dell\'appuntamento il cliente può cancellare')
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->suffix('ore'),
                    ]),

                Section::make('Promemoria')
                    ->columns(2)
                    ->schema([
                        Select::make('reminder_count')
                            ->label('Numero di promemoria')
                            ->options([
                                '0' => 'Nessuno',
                                '1' => '1 promemoria',
                                '2' => '2 promemoria',
                            ])
                            ->required()
                            ->live(),

                        TextInput::make('reminder_1_hours')
                            ->label('Primo promemoria')
                            ->helperText('Ore prima dell\'appuntamento')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('ore prima')
                            ->visible(fn (Get $get): bool => (int) $get('reminder_count') >= 1),

                        TextInput::make('reminder_2_hours')
                            ->label('Secondo promemoria')
                            ->helperText('Ore prima dell\'appuntamento')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('ore prima')
                            ->visible(fn (Get $get): bool => (int) $get('reminder_count') >= 2),
                    ]),

                Section::make('Pagamenti')
                    ->schema([
                        Select::make('payment_mode')
                            ->label('Metodi di pagamento accettati')
                            ->options([
                                'both'      => 'Online (Stripe) e in salone',
                                'online'    => 'Solo online (Stripe)',
                                'in_salon'  => 'Solo in salone',
                            ])
                            ->required(),
                    ]),

                Section::make('Sito web')
                    ->schema([
                        Toggle::make('reviews_enabled')
                            ->label('Mostra sezione recensioni')
                            ->helperText('Se disattivato, la sezione recensioni non compare sul sito del salone')
                            ->default(true),
                    ]),

                Section::make('Fedeltà')
                    ->columns(2)
                    ->schema([
                        Toggle::make('loyalty_enabled')
                            ->label('Abilita programma fedeltà')
                            ->helperText('I clienti accumulano punti sulla spesa e sbloccano uno sconto')
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('loyalty_points_per_euro')
                            ->label('Punti per euro speso')
                            ->helperText('Punti accreditati per ogni euro di spesa')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('punti/€')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),

                        TextInput::make('loyalty_reward_threshold')
                            ->label('Punti per lo sconto')
                            ->helperText('Punti necessari al cliente per sbloccare lo sconto')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('punti')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),

                        TextInput::make('loyalty_reward_percentage')
                            ->label('Sconto sbloccato')
                            ->helperText('Percentuale di sconto sbloccata al raggiungimento della soglia')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),
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
