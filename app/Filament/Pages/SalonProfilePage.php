<?php

namespace App\Filament\Pages;

use App\Models\SalonProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SalonProfilePage extends Page
{
    protected string $view = 'filament.pages.salon-profile';

    protected static ?string $navigationLabel = 'Profilo Salone';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 98;

    public ?array $data = [];

    public function mount(): void
    {
        $profile = SalonProfile::current();
        $this->form->fill([
            'name'          => $profile->name,
            'logo_path'     => $profile->logo_path,
            'primary_color' => $profile->primary_color,
            'phone'         => $profile->phone,
            'address'       => $profile->address,
            'website'       => $profile->website,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome del salone')
                                    ->required(),
                                ColorPicker::make('primary_color')
                                    ->label('Colore primario')
                                    ->required(),
                                TextInput::make('phone')
                                    ->label('Telefono'),
                                TextInput::make('website')
                                    ->label('Sito web')
                                    ->url(),
                                TextInput::make('address')
                                    ->label('Indirizzo')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(2),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('salon-logo')
                            ->maxSize(2048)
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SalonProfile::current()->update($this->form->getState());

        Notification::make()
            ->title('Profilo salvato')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
