<?php

namespace App\PageBlocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class AboutBlock extends AbstractPageBlock
{
    public static function type(): string
    {
        return 'about';
    }

    public static function label(): string
    {
        return 'Descrizione Salone';
    }

    public static function description(): string
    {
        return 'Testo di presentazione del salone con foto opzionale.';
    }

    public static function icon(): string
    {
        return 'heroicon-o-building-storefront';
    }

    public static function variants(): array
    {
        return [
            'centered' => ['label' => 'Centrato', 'description' => 'Testo centrato con foto sotto'],
            'split_image' => ['label' => 'Immagine + testo', 'description' => 'Immagine a sinistra, testo a destra'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Il Salone', 'body' => '', 'image' => null, 'owner_signature' => null];
    }

    public static function defaultSettings(): array
    {
        return ['alignment' => 'center'];
    }

    public static function contentRules(): array
    {
        return [
            'content.title' => ['required', 'string', 'max:80'],
            'content.body' => ['nullable', 'string', 'max:2000'],
            'content.image' => ['nullable', 'string'],
            'content.owner_signature' => ['nullable', 'string', 'max:60'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.alignment' => ['required', 'in:left,center'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.body')->label('Testo')->rows(5)->maxLength(2000),
            FileUpload::make('content.image')->label('Immagine')->image()->directory('site-builder/about'),
            TextInput::make('content.owner_signature')->label('Firma proprietario')->maxLength(60),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
        ];
    }
}
