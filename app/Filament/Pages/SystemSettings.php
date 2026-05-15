<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SystemSettings extends Page
{
    protected string $view = 'filament.pages.system-settings';

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount(): void
    {
        $setting = SystemSetting::current();
        $this->form->fill([
            'slot_granularity_minutes'     => $setting->slot_granularity_minutes,
            'hold_duration_minutes'        => $setting->hold_duration_minutes,
            'hold_extension_minutes'       => $setting->hold_extension_minutes,
            'min_service_duration_minutes' => $setting->min_service_duration_minutes,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('slot_granularity_minutes')
                    ->label('Granularità slot (minuti)')
                    ->helperText('Intervallo di tempo in minuti che divide il calendario in fasce orarie prenotabili. Ad esempio, con 15 minuti gli slot disponibili saranno 09:00, 09:15, 09:30 ecc.')
                    ->integer()
                    ->minValue(5)
                    ->maxValue(60)
                    ->required()
                    ->suffix('min'),

                TextInput::make('hold_duration_minutes')
                    ->label('Durata prenotazione temporanea')
                    ->helperText('Tempo massimo in minuti durante il quale uno slot rimane riservato mentre il cliente completa la procedura di prenotazione. Allo scadere, lo slot torna disponibile.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(60)
                    ->required()
                    ->suffix('min'),

                TextInput::make('hold_extension_minutes')
                    ->label('Estensione prenotazione temporanea')
                    ->helperText('Minuti aggiuntivi concessi automaticamente se il cliente è ancora attivo nel wizard di prenotazione prima della scadenza del blocco temporaneo.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(60)
                    ->required()
                    ->suffix('min'),

                TextInput::make('min_service_duration_minutes')
                    ->label('Durata minima servizio')
                    ->helperText('Durata minima in minuti che un servizio può avere. Utilizzata come validazione durante la creazione e modifica dei servizi.')
                    ->integer()
                    ->minValue(5)
                    ->maxValue(120)
                    ->required()
                    ->suffix('min'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SystemSetting::current()->update($this->form->getState());

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
