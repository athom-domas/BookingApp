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

    public static function variants(): array
    {
        return [
            'cards'    => ['label' => 'Card',      'description' => 'Recensioni in card con stelle'],
            'carousel' => ['label' => 'Carosello', 'description' => 'Carosello scorrevole'],
            'minimal'  => ['label' => 'Minimale',  'description' => 'Lista testuale compatta'],
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

    public static function filamentFields(): array
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
