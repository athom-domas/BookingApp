<?php

namespace App\Filament\Pages;

use App\Models\SalonProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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
                        Grid::make(1)
                            ->schema([
                                Placeholder::make('logo_preview')
                                    ->label('Logo attuale')
                                    ->content(fn () => ($url = SalonProfile::current()->logoUrl())
                                        ? new HtmlString("<img src=\"{$url}\" class=\"max-h-24 rounded-md object-contain\">")
                                        : new HtmlString('<span class="text-sm text-gray-400">Nessun logo caricato</span>'))
                                    ->visible(fn () => true),
                                FileUpload::make('logo_path')
                                    ->label('Carica nuovo logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('salon-logo')
                                    ->maxSize(2048),
                            ])
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if (blank($state['logo_path'] ?? null)) {
            unset($state['logo_path']);
        }

        $profile = SalonProfile::current();
        $profile->update($state);
        $profile->refresh();

        $this->form->fill([
            'name'          => $profile->name,
            'primary_color' => $profile->primary_color,
            'phone'         => $profile->phone,
            'address'       => $profile->address,
            'website'       => $profile->website,
        ]);

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
