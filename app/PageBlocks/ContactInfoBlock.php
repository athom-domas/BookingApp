<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ContactInfoBlock extends AbstractPageBlock
{
    public static function type(): string { return 'contact_info'; }
    public static function label(): string { return 'Orari & Contatti'; }
    public static function description(): string { return 'Orari di apertura, telefono, indirizzo e mappa.'; }
    public static function icon(): string { return 'heroicon-o-clock'; }

    public static function variants(): array
    {
        return [
            'simple'   => ['label' => 'Semplice',      'description' => 'Orari, telefono e indirizzo'],
            'with_map' => ['label' => 'Con mappa',     'description' => 'Informazioni + mappa Google integrata'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Orari e contatti', 'subtitle' => ''];
    }

    public static function defaultSettings(): array
    {
        return ['show_phone' => true, 'show_address' => true, 'show_hours' => true, 'contacts_position' => 'right'];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.show_phone'         => ['boolean'],
            'settings.show_address'       => ['boolean'],
            'settings.show_hours'         => ['boolean'],
            'settings.contacts_position'  => ['in:right,below'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Toggle::make('settings.show_phone')->label('Mostra telefono')->default(true),
            Toggle::make('settings.show_address')->label('Mostra indirizzo')->default(true),
            Toggle::make('settings.show_hours')->label('Mostra orari')->default(true),
            Select::make('settings.contacts_position')
                ->label('Posizione contatti')
                ->options(['right' => 'Affiancati agli orari', 'below' => 'Sotto gli orari'])
                ->default('right'),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $profile = SalonProfile::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->first();

        return ['profile' => $profile];
    }
}
