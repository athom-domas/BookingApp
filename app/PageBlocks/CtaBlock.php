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
            'simple'     => ['label' => 'Semplice',      'description' => 'Testo centrato con pulsante',          'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f1f5f9" rx="3"/><rect x="40" y="24" width="80" height="9" fill="#334155" rx="2"/><rect x="48" y="38" width="64" height="5" fill="#94a3b8" rx="1"/><rect x="56" y="51" width="48" height="14" fill="#3b82f6" rx="3"/></svg>'],
            'with_image' => ['label' => 'Con immagine', 'description' => 'Sfondo immagine con testo e pulsante', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#94a3b8" rx="3"/><rect width="160" height="90" fill="#0f172a" opacity="0.55" rx="3"/><rect x="40" y="24" width="80" height="9" fill="white" opacity="0.9" rx="2"/><rect x="48" y="38" width="64" height="5" fill="white" opacity="0.55" rx="1"/><rect x="56" y="51" width="48" height="14" fill="#3b82f6" rx="3"/></svg>'],
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

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Sottotitolo')->rows(2)->maxLength(200),
            TextInput::make('content.button_label')->label('Testo pulsante')->maxLength(50),
            FileUpload::make('content.image')
                ->label('Immagine di sfondo')
                ->image()
                ->disk('public')
                ->saveUploadedFileUsing(fn ($file) => static::storeAsWebp($file, 'site-builder/cta')),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
        ];
    }
}
