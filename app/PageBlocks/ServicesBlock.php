<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\Service;
use App\Models\ServiceCategory;
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
            'grid_cards'   => ['label' => 'Griglia card',   'description' => 'Servizi in card disposte a griglia',   'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="8" y="8" width="44" height="50" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="13" y="14" width="34" height="16" fill="#e0e7ef" rx="1"/><rect x="13" y="34" width="28" height="5" fill="#334155" rx="1"/><rect x="13" y="43" width="22" height="3" fill="#cbd5e1" rx="1"/><rect x="58" y="8" width="44" height="50" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="63" y="14" width="34" height="16" fill="#e0e7ef" rx="1"/><rect x="63" y="34" width="28" height="5" fill="#334155" rx="1"/><rect x="63" y="43" width="22" height="3" fill="#cbd5e1" rx="1"/><rect x="108" y="8" width="44" height="50" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="113" y="14" width="34" height="16" fill="#e0e7ef" rx="1"/><rect x="113" y="34" width="28" height="5" fill="#334155" rx="1"/><rect x="113" y="43" width="22" height="3" fill="#cbd5e1" rx="1"/><rect x="8" y="64" width="44" height="8" fill="#f0fdf4" rx="2"/><rect x="58" y="64" width="44" height="8" fill="#f0fdf4" rx="2"/><rect x="108" y="64" width="44" height="8" fill="#f0fdf4" rx="2"/><rect x="17" y="67" width="26" height="3" fill="#16a34a" rx="1"/><rect x="67" y="67" width="26" height="3" fill="#16a34a" rx="1"/><rect x="117" y="67" width="26" height="3" fill="#16a34a" rx="1"/></svg>'],
            'compact_list' => ['label' => 'Lista compatta con bottone prenotazione', 'description' => 'Elenco verticale con prezzo, durata e bottone prenota', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="12" y="10" width="136" height="20" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="18" y="14" width="60" height="5" fill="#334155" rx="1"/><rect x="116" y="13" width="25" height="4" fill="#16a34a" rx="1"/><rect x="116" y="19" width="20" height="3" fill="#cbd5e1" rx="1"/><rect x="116" y="24" width="25" height="4" fill="#e2e8f0" rx="1"/><rect x="12" y="34" width="136" height="20" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="18" y="38" width="70" height="5" fill="#334155" rx="1"/><rect x="116" y="37" width="25" height="4" fill="#16a34a" rx="1"/><rect x="116" y="43" width="20" height="3" fill="#cbd5e1" rx="1"/><rect x="116" y="48" width="25" height="4" fill="#e2e8f0" rx="1"/><rect x="12" y="58" width="136" height="20" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="18" y="62" width="55" height="5" fill="#334155" rx="1"/><rect x="116" y="61" width="25" height="4" fill="#16a34a" rx="1"/><rect x="116" y="67" width="20" height="3" fill="#cbd5e1" rx="1"/><rect x="116" y="72" width="25" height="4" fill="#e2e8f0" rx="1"/></svg>'],
            'price_list'   => ['label' => 'Listino prezzi', 'description' => 'Formato listino elegante',            'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="40" y="8" width="80" height="8" fill="#334155" rx="2"/><rect x="20" y="22" width="120" height="0.5" fill="#e2e8f0"/><rect x="20" y="28" width="55" height="4" fill="#334155" rx="1"/><rect x="126" y="28" width="14" height="4" fill="#334155" rx="1"/><rect x="20" y="38" width="65" height="4" fill="#334155" rx="1"/><rect x="126" y="38" width="14" height="4" fill="#334155" rx="1"/><rect x="20" y="48" width="50" height="4" fill="#334155" rx="1"/><rect x="126" y="48" width="14" height="4" fill="#334155" rx="1"/><rect x="20" y="58" width="120" height="0.5" fill="#e2e8f0"/><rect x="55" y="62" width="50" height="5" fill="#94a3b8" rx="1"/><rect x="20" y="72" width="58" height="4" fill="#334155" rx="1"/><rect x="126" y="72" width="14" height="4" fill="#334155" rx="1"/></svg>'],
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

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
            Toggle::make('settings.show_prices')->label('Mostra prezzi')->default(true),
            Toggle::make('settings.show_duration')->label('Mostra durata')->default(true),
            Toggle::make('settings.featured_only')
                ->label('Solo servizi in evidenza')
                ->helperText('I servizi non in evidenza restano disponibili dietro un bottone "Mostra tutti i servizi", come sul form di prenotazione.'),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $services = Service::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('active', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = ServiceCategory::withoutGlobalScope('business')
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->whereHas('services', fn ($q) => $q
                ->where('business_id', $business->id)
                ->where('active', true))
            ->orderBy('sort_order')
            ->get();

        if ($categories->isEmpty()) {
            $grouped = [['category' => null, 'services' => $services]];
        } else {
            $grouped = [];
            foreach ($categories as $cat) {
                $catServices = $services->where('service_category_id', $cat->id)->values();
                if ($catServices->isNotEmpty()) {
                    $grouped[] = ['category' => $cat, 'services' => $catServices];
                }
            }
            $uncategorized = $services->whereNull('service_category_id')->values();
            if ($uncategorized->isNotEmpty()) {
                $grouped[] = ['category' => null, 'services' => $uncategorized];
            }
        }

        return ['services' => $services, 'grouped_services' => $grouped];
    }
}
