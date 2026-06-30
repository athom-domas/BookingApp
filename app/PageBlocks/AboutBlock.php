<?php

namespace App\PageBlocks;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
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

    public static function navLabel(): ?string { return 'Il salone'; }

    public static function variants(): array
    {
        return [
            'centered'         => ['label' => 'Centrato',              'description' => 'Testo centrato con foto sotto',       'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="40" y="10" width="80" height="8" fill="#334155" rx="2"/><rect x="30" y="24" width="100" height="4" fill="#cbd5e1" rx="1"/><rect x="35" y="32" width="90" height="4" fill="#cbd5e1" rx="1"/><rect x="45" y="40" width="70" height="4" fill="#cbd5e1" rx="1"/><rect x="28" y="52" width="104" height="30" fill="#94a3b8" rx="2"/></svg>'],
            'split_image'      => ['label' => 'Immagine sinistra',     'description' => 'Immagine a sinistra, testo a destra', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="12" y="12" width="62" height="66" fill="#94a3b8" rx="2"/><rect x="86" y="18" width="60" height="7" fill="#334155" rx="2"/><rect x="86" y="30" width="56" height="4" fill="#cbd5e1" rx="1"/><rect x="86" y="38" width="52" height="4" fill="#cbd5e1" rx="1"/><rect x="86" y="46" width="48" height="4" fill="#cbd5e1" rx="1"/></svg>'],
            'split_image_right'=> ['label' => 'Immagine destra',      'description' => 'Testo a sinistra, immagine a destra',  'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="12" y="18" width="60" height="7" fill="#334155" rx="2"/><rect x="12" y="30" width="56" height="4" fill="#cbd5e1" rx="1"/><rect x="12" y="38" width="52" height="4" fill="#cbd5e1" rx="1"/><rect x="12" y="46" width="48" height="4" fill="#cbd5e1" rx="1"/><rect x="86" y="12" width="62" height="66" fill="#94a3b8" rx="2"/></svg>'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Il Salone', 'body' => '', 'images' => [], 'owner_signature' => null];
    }

    public static function defaultSettings(): array
    {
        return [];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'           => ['required', 'string', 'max:80'],
            'content.body'            => ['nullable', 'string'],
            'content.images'          => ['nullable', 'array', 'max:3'],
            'content.images.*'        => ['string'],
            'content.owner_signature' => ['nullable', 'string', 'max:60'],
        ];
    }

    public static function settingsRules(): array
    {
        return [];
    }

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            RichEditor::make('content.body')
                ->label('Testo')
                ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'orderedList', 'bulletList', 'undo', 'redo']),
            FileUpload::make('content.images')
                ->label('Immagini (max 3)')
                ->image()
                ->multiple()
                ->maxFiles(3)
                ->disk('public')
                ->saveUploadedFileUsing(fn ($file) => static::storeAsWebp($file, 'site-builder/about')),
            TextInput::make('content.owner_signature')->label('Firma proprietario')->maxLength(60),
        ];
    }
}
