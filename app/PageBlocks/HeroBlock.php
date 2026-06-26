<?php

namespace App\PageBlocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class HeroBlock extends AbstractPageBlock
{
    public static function type(): string
    {
        return 'hero';
    }

    public static function label(): string
    {
        return 'Hero / Header';
    }

    public static function description(): string
    {
        return 'Sezione principale con immagine di sfondo, titolo e CTA.';
    }

    public static function icon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function variants(): array
    {
        return [
            'classic' => ['label' => 'Classico', 'description' => 'Sfondo immagine piena con testo centrato'],
            'editorial' => ['label' => 'Editoriale', 'description' => 'Immagine laterale con testo a sinistra'],
            'centered' => ['label' => 'Centrato', 'description' => 'Sfondo tinta unita con testo centrato'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => '', 'subtitle' => '', 'cta_label' => 'Prenota ora', 'image' => null];
    }

    public static function defaultSettings(): array
    {
        return ['alignment' => 'center', 'show_cta' => true];
    }

    public static function contentRules(): array
    {
        return [
            'content.title' => ['required', 'string', 'max:120'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
            'content.cta_label' => ['nullable', 'string', 'max:50'],
            'content.image' => ['nullable', 'string'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.alignment' => ['required', 'in:left,center'],
            'settings.show_cta' => ['boolean'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo')->required()->maxLength(120),
            Textarea::make('content.subtitle')->label('Sottotitolo')->maxLength(200)->rows(2),
            TextInput::make('content.cta_label')->label('Testo pulsante CTA')->maxLength(50),
            FileUpload::make('content.image')->label('Immagine di sfondo')->image()->directory('site-builder/hero'),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
            Toggle::make('settings.show_cta')->label('Mostra pulsante CTA')->default(true),
        ];
    }
}
