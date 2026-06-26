<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class GalleryBlock extends AbstractPageBlock
{
    public static function type(): string { return 'gallery'; }
    public static function label(): string { return 'Galleria'; }
    public static function description(): string { return 'Galleria immagini portfolio del salone.'; }
    public static function icon(): string { return 'heroicon-o-photo'; }

    public static function variants(): array
    {
        return [
            'grid_3col' => ['label' => 'Griglia 3 colonne', 'description' => 'Griglia uniforme a 3 colonne'],
            'masonry'   => ['label' => 'Masonry',           'description' => 'Griglia con altezze variabili'],
            'slider'    => ['label' => 'Slider',            'description' => 'Carosello scorribile'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Galleria', 'subtitle' => ''];
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
        $profile = SalonProfile::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->first();

        return ['images' => $profile ? $profile->getMedia('portfolio') : collect()];
    }
}
