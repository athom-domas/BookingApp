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
            'cards'       => ['label' => 'Card con foto',     'description' => 'Card con avatar, nome e bio'],
            'simple_list' => ['label' => 'Lista semplice',    'description' => 'Elenco nomi e ruoli senza foto'],
            'editorial'   => ['label' => 'Layout editoriale', 'description' => 'Foto grande con bio estesa'],
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

    public static function filamentFields(): array
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
            ->with('media')
            ->orderBy('sort_order')
            ->get();

        return ['staff' => $staff];
    }
}
