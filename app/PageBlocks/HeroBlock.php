<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

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
            'classic'   => ['label' => 'Classico',    'description' => 'Sfondo immagine piena con testo centrato', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#94a3b8" rx="3"/><rect width="160" height="90" fill="#0f172a" opacity="0.5" rx="3"/><rect x="32" y="26" width="96" height="9" fill="white" opacity="0.9" rx="2"/><rect x="48" y="40" width="64" height="5" fill="white" opacity="0.55" rx="1"/><rect x="56" y="52" width="48" height="14" fill="#3b82f6" rx="3"/></svg>'],
            'editorial' => ['label' => 'Editoriale',  'description' => 'Immagine laterale con testo a sinistra',   'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect width="74" height="90" fill="#94a3b8" rx="3"/><rect x="84" y="20" width="56" height="8" fill="#334155" rx="2"/><rect x="84" y="33" width="48" height="4" fill="#cbd5e1" rx="1"/><rect x="84" y="41" width="40" height="4" fill="#cbd5e1" rx="1"/><rect x="84" y="53" width="40" height="12" fill="#3b82f6" rx="3"/></svg>'],
            'centered'  => ['label' => 'Centrato',    'description' => 'Sfondo tinta unita con testo centrato',    'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f1f5f9" rx="3"/><rect x="32" y="26" width="96" height="9" fill="#334155" rx="2"/><rect x="48" y="40" width="64" height="5" fill="#94a3b8" rx="1"/><rect x="56" y="52" width="48" height="14" fill="#3b82f6" rx="3"/></svg>'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => '', 'subtitle' => '', 'cta_label' => 'Prenota ora', 'image' => null, 'image_mobile' => null];
    }

    public static function defaultSettings(): array
    {
        return ['show_cta' => true, 'image_preset' => ''];
    }

    public static function contentRules(): array
    {
        return [
            'content.title' => ['required', 'string', 'max:120'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
            'content.cta_label' => ['nullable', 'string', 'max:50'],
            'content.image'        => ['nullable', 'string'],
            'content.image_mobile' => ['nullable', 'string'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.show_cta'     => ['boolean'],
            'settings.image_preset' => ['nullable', 'string'],
        ];
    }

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo')->required()->maxLength(120),
            Textarea::make('content.subtitle')->label('Sottotitolo')->maxLength(200)->rows(2),
            FileUpload::make('content.image')
                ->label('Immagine desktop')
                ->image()
                ->disk('public')
                ->saveUploadedFileUsing(fn ($file) => static::storeAsWebp($file, 'site-builder/hero'))
                ->helperText('Mostrata su tutti i dispositivi se non viene caricata un\'immagine mobile.'),
            FileUpload::make('content.image_mobile')
                ->label('Immagine mobile (opzionale)')
                ->image()
                ->disk('public')
                ->saveUploadedFileUsing(fn ($file) => static::storeAsWebp($file, 'site-builder/hero'))
                ->helperText('Sostituisce l\'immagine desktop su schermi piccoli (≤ 640px). Usa un formato verticale o quadrato.'),
            Radio::make('settings.image_preset')
                ->label('Oppure scegli immagine predefinita')
                ->options(array_merge(
                    ['' => 'Nessuna'],
                    array_map(fn ($p) => $p['label'], SalonProfile::heroPresets())
                ))
                ->dehydrateStateUsing(fn ($state) => $state ?: null)
                ->view('filament.forms.hero-preset-picker'),
            Toggle::make('settings.show_cta')->label('Mostra pulsante CTA')->default(true)->live(),
            TextInput::make('content.cta_label')
                ->label('Testo pulsante CTA')
                ->maxLength(50)
                ->visible(fn (Get $get): bool => (bool) $get('settings.show_cta')),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $preset    = $block->settings['image_preset'] ?? '';
        $presetUrl = $preset ? (SalonProfile::heroPresets()[$preset]['url'] ?? null) : null;
        return ['hero_preset_url' => $presetUrl];
    }
}
