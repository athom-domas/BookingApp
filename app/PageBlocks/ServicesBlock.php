<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\Service;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ServicesBlock extends AbstractPageBlock
{
    public static function type(): string { return 'services'; }
    public static function label(): string { return 'Servizi'; }
    public static function description(): string { return 'Elenco dei servizi con prezzo e durata.'; }
    public static function icon(): string { return 'heroicon-o-scissors'; }

    public static function variants(): array
    {
        return [
            'grid_cards'   => ['label' => 'Griglia card',   'description' => 'Servizi in card disposte a griglia'],
            'compact_list' => ['label' => 'Lista compatta', 'description' => 'Elenco verticale con prezzo e durata'],
            'price_list'   => ['label' => 'Listino prezzi', 'description' => 'Formato listino elegante'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'I nostri servizi', 'subtitle' => ''];
    }

    public static function defaultSettings(): array
    {
        return ['show_prices' => true, 'show_duration' => true, 'featured_only' => false];
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
            'settings.show_prices'   => ['boolean'],
            'settings.show_duration' => ['boolean'],
            'settings.featured_only' => ['boolean'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
            Toggle::make('settings.show_prices')->label('Mostra prezzi')->default(true),
            Toggle::make('settings.show_duration')->label('Mostra durata')->default(true),
            Toggle::make('settings.featured_only')->label('Solo servizi in evidenza'),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $query = Service::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($block->settings['featured_only'] ?? false) {
            $query->where('featured', true);
        }

        return ['services' => $query->get()];
    }
}
