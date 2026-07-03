<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Storage;

class GalleryBlock extends AbstractPageBlock
{
    public static function type(): string { return 'gallery'; }
    public static function label(): string { return 'Galleria'; }
    public static function description(): string { return 'Galleria immagini portfolio del salone.'; }
    public static function icon(): string { return 'heroicon-o-photo'; }
    public static function navLabel(): ?string { return 'Galleria'; }

    public static function variants(): array
    {
        return [
            'grid_3col' => ['label' => 'Griglia 3 colonne', 'description' => 'Griglia uniforme a 3 colonne',    'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="10" y="10" width="42" height="32" fill="#94a3b8" rx="2"/><rect x="59" y="10" width="42" height="32" fill="#94a3b8" rx="2"/><rect x="108" y="10" width="42" height="32" fill="#94a3b8" rx="2"/><rect x="10" y="48" width="42" height="32" fill="#94a3b8" rx="2"/><rect x="59" y="48" width="42" height="32" fill="#94a3b8" rx="2"/><rect x="108" y="48" width="42" height="32" fill="#94a3b8" rx="2"/></svg>'],
            'masonry'   => ['label' => 'Masonry',           'description' => 'Griglia con altezze variabili',   'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="10" y="10" width="44" height="38" fill="#94a3b8" rx="2"/><rect x="10" y="54" width="44" height="26" fill="#b0bec5" rx="2"/><rect x="60" y="10" width="44" height="22" fill="#b0bec5" rx="2"/><rect x="60" y="38" width="44" height="42" fill="#94a3b8" rx="2"/><rect x="110" y="10" width="40" height="30" fill="#94a3b8" rx="2"/><rect x="110" y="46" width="40" height="34" fill="#b0bec5" rx="2"/></svg>'],
            'slider'    => ['label' => 'Slider',            'description' => 'Carosello scorribile',            'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="20" y="8" width="120" height="62" fill="#94a3b8" rx="2"/><circle cx="13" cy="39" r="9" fill="white" opacity="0.8"/><circle cx="147" cy="39" r="9" fill="white" opacity="0.8"/><circle cx="72" cy="79" r="3" fill="#3b82f6"/><circle cx="82" cy="79" r="3" fill="#cbd5e1"/><circle cx="92" cy="79" r="3" fill="#cbd5e1"/></svg>'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Galleria', 'subtitle' => '', 'images' => []];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function filamentFields(?BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
            FileUpload::make('content.images')
                ->label('Immagini galleria')
                ->multiple()
                ->reorderable()
                ->disk('public')
                ->image()
                ->maxSize(10240)
                ->saveUploadedFileUsing(fn ($file) => static::storeAsWebp($file, 'site-builder/gallery'))
                ->columnSpanFull(),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $paths = $block->content['images'] ?? [];
        $images = collect($paths)
            ->filter()
            ->map(fn ($path) => Storage::disk('public')->url($path))
            ->values();

        return ['images' => $images];
    }
}
