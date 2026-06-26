<?php

namespace App\PageBlocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class CtaBlock extends AbstractPageBlock
{
    public static function type(): string
    {
        return 'cta';
    }

    public static function label(): string
    {
        return 'CTA Prenotazione';
    }

    public static function description(): string
    {
        return 'Sezione di invito all\'azione con pulsante di prenotazione.';
    }

    public static function icon(): string
    {
        return 'heroicon-o-cursor-arrow-rays';
    }

    public static function variants(): array
    {
        return [
            'simple' => ['label' => 'Semplice', 'description' => 'Testo centrato con pulsante'],
            'with_image' => ['label' => 'Con immagine', 'description' => 'Sfondo immagine con testo e pulsante'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Prenota ora', 'subtitle' => '', 'button_label' => 'Prenota', 'image' => null];
    }

    public static function defaultSettings(): array
    {
        return ['alignment' => 'center'];
    }

    public static function contentRules(): array
    {
        return [
            'content.title' => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
            'content.button_label' => ['nullable', 'string', 'max:50'],
            'content.image' => ['nullable', 'string'],
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
            TextInput::make('content.title')->label('Titolo')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Sottotitolo')->rows(2)->maxLength(200),
            TextInput::make('content.button_label')->label('Testo pulsante')->maxLength(50),
            FileUpload::make('content.image')->label('Immagine di sfondo')->image()->directory('site-builder/cta'),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
        ];
    }
}
