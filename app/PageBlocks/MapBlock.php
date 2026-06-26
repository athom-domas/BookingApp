<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class MapBlock extends AbstractPageBlock
{
    public static function type(): string { return 'map'; }
    public static function label(): string { return 'Mappa'; }
    public static function description(): string { return 'Mappa Google integrata con indirizzo del salone.'; }
    public static function icon(): string { return 'heroicon-o-map-pin'; }

    public static function variants(): array
    {
        return [
            'full_width' => ['label' => 'Larghezza piena', 'description' => 'Mappa a tutta larghezza'],
            'contained'  => ['label' => 'Contenuta',       'description' => 'Mappa in contenitore centrato'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => ''];
    }

    public static function defaultSettings(): array
    {
        return ['height' => 'md', 'show_directions_link' => true];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.height'                => ['required', 'in:sm,md,lg'],
            'settings.show_directions_link'  => ['boolean'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo (opzionale)')->maxLength(80),
            Select::make('settings.height')->label('Altezza mappa')->options(['sm' => 'Piccola', 'md' => 'Media', 'lg' => 'Grande'])->required(),
            Toggle::make('settings.show_directions_link')->label('Mostra link "Ottieni indicazioni"')->default(true),
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
