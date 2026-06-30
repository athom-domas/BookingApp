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
            'full_width' => ['label' => 'Larghezza piena', 'description' => 'Mappa a tutta larghezza',       'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#94a3b8" rx="3"/><rect x="0" y="30" width="160" height="0.5" fill="#7b8fa1"/><rect x="0" y="60" width="160" height="0.5" fill="#7b8fa1"/><rect x="53" y="0" width="0.5" height="90" fill="#7b8fa1"/><rect x="107" y="0" width="0.5" height="90" fill="#7b8fa1"/><circle cx="80" cy="42" r="8" fill="#ef4444"/><circle cx="80" cy="42" r="3.5" fill="white"/></svg>'],
            'contained'  => ['label' => 'Contenuta',       'description' => 'Mappa in contenitore centrato', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="20" y="12" width="120" height="66" fill="#94a3b8" rx="3"/><rect x="20" y="37" width="120" height="0.5" fill="#7b8fa1"/><rect x="20" y="62" width="120" height="0.5" fill="#7b8fa1"/><rect x="60" y="12" width="0.5" height="66" fill="#7b8fa1"/><rect x="100" y="12" width="0.5" height="66" fill="#7b8fa1"/><circle cx="80" cy="44" r="8" fill="#ef4444"/><circle cx="80" cy="44" r="3.5" fill="white"/></svg>'],
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

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
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
