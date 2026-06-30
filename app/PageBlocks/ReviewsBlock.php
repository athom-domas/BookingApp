<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonReview;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class ReviewsBlock extends AbstractPageBlock
{
    public static function type(): string { return 'reviews'; }
    public static function label(): string { return 'Recensioni'; }
    public static function description(): string { return 'Testimonianze e recensioni dei clienti.'; }
    public static function icon(): string { return 'heroicon-o-star'; }
    public static function navLabel(): ?string { return 'Recensioni'; }

    public static function variants(): array
    {
        return [
            'cards'    => ['label' => 'Card',      'description' => 'Recensioni in card con stelle', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="8" y="10" width="44" height="70" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="13" y="16" width="34" height="5" fill="#fbbf24" rx="1"/><rect x="13" y="26" width="34" height="3" fill="#cbd5e1" rx="1"/><rect x="13" y="32" width="30" height="3" fill="#cbd5e1" rx="1"/><rect x="13" y="38" width="34" height="3" fill="#cbd5e1" rx="1"/><rect x="13" y="66" width="28" height="3" fill="#334155" rx="1"/><rect x="58" y="10" width="44" height="70" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="63" y="16" width="34" height="5" fill="#fbbf24" rx="1"/><rect x="63" y="26" width="34" height="3" fill="#cbd5e1" rx="1"/><rect x="63" y="32" width="28" height="3" fill="#cbd5e1" rx="1"/><rect x="63" y="38" width="34" height="3" fill="#cbd5e1" rx="1"/><rect x="63" y="66" width="24" height="3" fill="#334155" rx="1"/><rect x="108" y="10" width="44" height="70" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="113" y="16" width="34" height="5" fill="#fbbf24" rx="1"/><rect x="113" y="26" width="34" height="3" fill="#cbd5e1" rx="1"/><rect x="113" y="32" width="30" height="3" fill="#cbd5e1" rx="1"/><rect x="113" y="38" width="34" height="3" fill="#cbd5e1" rx="1"/><rect x="113" y="66" width="20" height="3" fill="#334155" rx="1"/></svg>'],
            'carousel' => ['label' => 'Carosello', 'description' => 'Carosello scorrevole',              'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="28" y="10" width="104" height="60" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="44" y="18" width="72" height="6" fill="#fbbf24" rx="1"/><rect x="44" y="30" width="72" height="4" fill="#cbd5e1" rx="1"/><rect x="44" y="38" width="60" height="4" fill="#cbd5e1" rx="1"/><rect x="54" y="52" width="38" height="3" fill="#334155" rx="1"/><circle cx="17" cy="40" r="8" fill="#e2e8f0"/><circle cx="143" cy="40" r="8" fill="#e2e8f0"/><circle cx="72" cy="78" r="3" fill="#3b82f6"/><circle cx="82" cy="78" r="3" fill="#cbd5e1"/><circle cx="92" cy="78" r="3" fill="#cbd5e1"/></svg>'],
            'minimal'  => ['label' => 'Minimale',  'description' => 'Lista testuale compatta',             'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="12" y="13" width="36" height="4" fill="#334155" rx="1"/><rect x="12" y="20" width="28" height="3" fill="#fbbf24" rx="1"/><rect x="12" y="27" width="100" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="33" width="80" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="43" width="136" height="0.5" fill="#e2e8f0"/><rect x="12" y="49" width="44" height="4" fill="#334155" rx="1"/><rect x="12" y="56" width="28" height="3" fill="#fbbf24" rx="1"/><rect x="12" y="63" width="110" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="69" width="88" height="3" fill="#cbd5e1" rx="1"/></svg>'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Cosa dicono di noi', 'subtitle' => ''];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $reviews = SalonReview::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->published()
            ->ordered()
            ->get();

        return ['reviews' => $reviews];
    }
}
