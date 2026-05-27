<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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
            'slot_granularity_minutes' => $setting->slot_granularity_minutes,
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

                    ]),
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
