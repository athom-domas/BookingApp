<?php

namespace App\PageBlocks;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class StaffBlock extends AbstractPageBlock
{
    public static function type(): string { return 'staff'; }
    public static function label(): string { return 'Team'; }
    public static function description(): string { return 'Presentazione del personale con foto e bio.'; }
    public static function icon(): string { return 'heroicon-o-user-group'; }

    public static function variants(): array
    {
        return [
            'cards'       => ['label' => 'Card con foto',     'description' => 'Card con avatar, nome e bio',    'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="8" y="8" width="44" height="74" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="8" y="8" width="44" height="30" fill="#94a3b8" rx="2"/><circle cx="30" cy="38" r="10" fill="#e0e7ef" stroke="white" stroke-width="2"/><rect x="13" y="52" width="34" height="5" fill="#334155" rx="1"/><rect x="16" y="61" width="28" height="3" fill="#cbd5e1" rx="1"/><rect x="13" y="68" width="34" height="3" fill="#e2e8f0" rx="1"/><rect x="58" y="8" width="44" height="74" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="58" y="8" width="44" height="30" fill="#94a3b8" rx="2"/><circle cx="80" cy="38" r="10" fill="#e0e7ef" stroke="white" stroke-width="2"/><rect x="63" y="52" width="34" height="5" fill="#334155" rx="1"/><rect x="66" y="61" width="28" height="3" fill="#cbd5e1" rx="1"/><rect x="63" y="68" width="34" height="3" fill="#e2e8f0" rx="1"/><rect x="108" y="8" width="44" height="74" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="108" y="8" width="44" height="30" fill="#94a3b8" rx="2"/><circle cx="130" cy="38" r="10" fill="#e0e7ef" stroke="white" stroke-width="2"/><rect x="113" y="52" width="34" height="5" fill="#334155" rx="1"/><rect x="116" y="61" width="28" height="3" fill="#cbd5e1" rx="1"/><rect x="113" y="68" width="34" height="3" fill="#e2e8f0" rx="1"/></svg>'],
            'simple_list' => ['label' => 'Lista semplice',    'description' => 'Elenco con foto, nome e bio',   'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="12" y="10" width="136" height="14" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><circle cx="22" cy="17" r="5" fill="#e0e7ef"/><rect x="32" y="14" width="55" height="5" fill="#334155" rx="1"/><rect x="100" y="15" width="38" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="28" width="136" height="14" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><circle cx="22" cy="35" r="5" fill="#e0e7ef"/><rect x="32" y="32" width="50" height="5" fill="#334155" rx="1"/><rect x="100" y="33" width="42" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="46" width="136" height="14" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><circle cx="22" cy="53" r="5" fill="#e0e7ef"/><rect x="32" y="50" width="55" height="5" fill="#334155" rx="1"/><rect x="100" y="51" width="38" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="64" width="136" height="14" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><circle cx="22" cy="71" r="5" fill="#e0e7ef"/><rect x="32" y="68" width="48" height="5" fill="#334155" rx="1"/><rect x="100" y="69" width="40" height="3" fill="#cbd5e1" rx="1"/></svg>'],
            'editorial'   => ['label' => 'Layout editoriale', 'description' => 'Griglia 3 colonne con cerchio foto e bio', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><circle cx="14" cy="22" r="9" fill="#94a3b8"/><rect x="27" y="14" width="24" height="5" fill="#334155" rx="1"/><rect x="27" y="22" width="20" height="3" fill="#cbd5e1" rx="1"/><rect x="27" y="28" width="16" height="3" fill="#cbd5e1" rx="1"/><circle cx="67" cy="22" r="9" fill="#94a3b8"/><rect x="80" y="14" width="24" height="5" fill="#334155" rx="1"/><rect x="80" y="22" width="20" height="3" fill="#cbd5e1" rx="1"/><rect x="80" y="28" width="16" height="3" fill="#cbd5e1" rx="1"/><circle cx="120" cy="22" r="9" fill="#94a3b8"/><rect x="133" y="14" width="20" height="5" fill="#334155" rx="1"/><rect x="133" y="22" width="16" height="3" fill="#cbd5e1" rx="1"/><rect x="133" y="28" width="14" height="3" fill="#cbd5e1" rx="1"/><circle cx="14" cy="65" r="9" fill="#94a3b8"/><rect x="27" y="57" width="24" height="5" fill="#334155" rx="1"/><rect x="27" y="65" width="20" height="3" fill="#cbd5e1" rx="1"/><rect x="27" y="71" width="16" height="3" fill="#cbd5e1" rx="1"/></svg>'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Il nostro team', 'subtitle' => ''];
    }

    public static function contentRules(): array
    {
        return [
            'content.title'    => ['required', 'string', 'max:80'],
            'content.subtitle' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Textarea::make('content.subtitle')->label('Testo introduttivo')->rows(2)->maxLength(200),
        ];
    }

    public static function resolveData(Business $business, BusinessPageBlock $block): array
    {
        $staff = User::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->orderBy('sort_order')
            ->get();

        return ['staff' => $staff];
    }
}
