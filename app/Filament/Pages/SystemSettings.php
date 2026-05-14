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
        $this->form->fill([
            'slot_generation_weeks' => SystemSetting::current()->slot_generation_weeks,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('slot_generation_weeks')
                    ->label('Settimane di anticipo per la generazione degli slot')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(52)
                    ->required()
                    ->suffix('settimane'),
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
