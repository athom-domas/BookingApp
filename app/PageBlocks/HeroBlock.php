<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
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
        return ['alignment' => 'center', 'show_cta' => true, 'image_preset' => ''];
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
            'settings.alignment'    => ['required', 'in:left,center'],
            'settings.show_cta'     => ['boolean'],
            'settings.image_preset' => ['nullable', 'string'],
        ];
    }

    public static function filamentFields(): array
    {
        return [
            TextInput::make('content.title')->label('Titolo')->required()->maxLength(120),
            Textarea::make('content.subtitle')->label('Sottotitolo')->maxLength(200)->rows(2),
            TextInput::make('content.cta_label')->label('Testo pulsante CTA')->maxLength(50),
            FileUpload::make('content.image')
                ->label('Immagine di sfondo (carica la tua)')
                ->image()
                ->directory('site-builder/hero')
                ->helperText('Ha priorità sull\'immagine predefinita.'),
            Radio::make('settings.image_preset')
                ->label('Oppure scegli immagine predefinita')
                ->options(array_merge(
                    ['' => 'Nessuna'],
                    array_map(fn ($p) => $p['label'], SalonProfile::heroPresets())
                ))
                ->dehydrateStateUsing(fn ($state) => $state ?: null)
                ->view('filament.forms.hero-preset-picker'),
            Select::make('settings.alignment')->label('Allineamento')->options(['left' => 'Sinistra', 'center' => 'Centrato'])->required(),
            Toggle::make('settings.show_cta')->label('Mostra pulsante CTA')->default(true),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $preset    = $block->settings['image_preset'] ?? '';
        $presetUrl = $preset ? (SalonProfile::heroPresets()[$preset]['url'] ?? null) : null;
        return ['hero_preset_url' => $presetUrl];
    }
}
