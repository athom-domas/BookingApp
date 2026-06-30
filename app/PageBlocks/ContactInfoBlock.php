<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\SalonProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class ContactInfoBlock extends AbstractPageBlock
{
    public static function type(): string { return 'contact_info'; }
    public static function label(): string { return 'Orari & Contatti'; }
    public static function description(): string { return 'Orari di apertura, telefono, indirizzo e mappa.'; }
    public static function icon(): string { return 'heroicon-o-clock'; }

    public static function variants(): array
    {
        return [
            'simple'   => ['label' => 'Semplice',   'description' => 'Orari, telefono e indirizzo',             'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="20" y="12" width="60" height="7" fill="#334155" rx="2"/><rect x="20" y="26" width="55" height="4" fill="#cbd5e1" rx="1"/><rect x="20" y="34" width="50" height="4" fill="#cbd5e1" rx="1"/><rect x="20" y="42" width="55" height="4" fill="#cbd5e1" rx="1"/><rect x="20" y="50" width="45" height="4" fill="#cbd5e1" rx="1"/><rect x="90" y="26" width="50" height="4" fill="#cbd5e1" rx="1"/><rect x="90" y="34" width="45" height="4" fill="#cbd5e1" rx="1"/><rect x="90" y="42" width="50" height="4" fill="#cbd5e1" rx="1"/></svg>'],
            'with_map' => ['label' => 'Con mappa', 'description' => 'Informazioni + mappa Google integrata', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="20" y="8" width="60" height="7" fill="#334155" rx="2"/><rect x="12" y="22" width="45" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="29" width="42" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="36" width="45" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="43" width="38" height="3" fill="#cbd5e1" rx="1"/><rect x="72" y="22" width="76" height="3" fill="#cbd5e1" rx="1"/><rect x="72" y="29" width="68" height="3" fill="#cbd5e1" rx="1"/><rect x="72" y="38" width="76" height="44" fill="#94a3b8" rx="2"/><circle cx="110" cy="60" r="6" fill="#ef4444"/><circle cx="110" cy="60" r="2.5" fill="white"/></svg>'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Orari e contatti', 'subtitle' => ''];
    }

    public static function defaultSettings(): array
    {
        return ['show_phone' => true, 'show_address' => true, 'show_hours' => true, 'contacts_position' => 'right'];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function settingsRules(): array
    {
        return [
            'settings.show_phone'         => ['boolean'],
            'settings.show_address'       => ['boolean'],
            'settings.show_hours'         => ['boolean'],
            'settings.contacts_position'  => ['in:right,below'],
        ];
    }

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Toggle::make('settings.show_phone')->label('Mostra telefono')->default(true),
            Toggle::make('settings.show_address')->label('Mostra indirizzo')->default(true),
            Toggle::make('settings.show_hours')->label('Mostra orari')->default(true),
            Select::make('settings.contacts_position')
                ->label('Posizione contatti')
                ->options(['right' => 'Affiancati agli orari', 'below' => 'Sotto gli orari'])
                ->default('right'),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $profile = SalonProfile::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->first();

        return ['profile' => $profile];
    }
}
