<?php

namespace App\PageBlocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class FaqBlock extends AbstractPageBlock
{
    public static function type(): string
    {
        return 'faq';
    }

    public static function label(): string
    {
        return 'FAQ';
    }

    public static function description(): string
    {
        return 'Domande frequenti con risposte espandibili.';
    }

    public static function icon(): string
    {
        return 'heroicon-o-question-mark-circle';
    }

    public static function navLabel(): ?string { return 'FAQ'; }

    public static function variants(): array
    {
        return [
            'accordion' => ['label' => 'Accordion', 'description' => 'Domande espandibili/collassabili', 'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="12" y="10" width="136" height="18" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="20" y="16" width="80" height="6" fill="#334155" rx="1"/><rect x="136" y="15" width="6" height="2" fill="#94a3b8" rx="1"/><rect x="139" y="12" width="2" height="8" fill="#94a3b8" rx="1"/><rect x="20" y="32" width="112" height="3" fill="#cbd5e1" rx="1"/><rect x="20" y="38" width="96" height="3" fill="#cbd5e1" rx="1"/><rect x="12" y="48" width="136" height="14" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="20" y="53" width="70" height="5" fill="#334155" rx="1"/><rect x="136" y="53" width="6" height="2" fill="#94a3b8" rx="1"/><rect x="12" y="68" width="136" height="14" fill="white" stroke="#e2e8f0" stroke-width="1" rx="2"/><rect x="20" y="73" width="75" height="5" fill="#334155" rx="1"/><rect x="136" y="73" width="6" height="2" fill="#94a3b8" rx="1"/></svg>'],
            'list'      => ['label' => 'Lista',     'description' => 'Domande e risposte in lista aperta',      'preview' => '<svg viewBox="0 0 160 90" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="160" height="90" fill="#f8fafc" rx="3"/><rect x="12" y="10" width="80" height="6" fill="#334155" rx="1"/><rect x="12" y="20" width="120" height="4" fill="#cbd5e1" rx="1"/><rect x="12" y="27" width="100" height="4" fill="#cbd5e1" rx="1"/><rect x="12" y="37" width="136" height="1" fill="#e2e8f0"/><rect x="12" y="44" width="90" height="6" fill="#334155" rx="1"/><rect x="12" y="54" width="118" height="4" fill="#cbd5e1" rx="1"/><rect x="12" y="61" width="95" height="4" fill="#cbd5e1" rx="1"/><rect x="12" y="71" width="136" height="1" fill="#e2e8f0"/><rect x="12" y="78" width="70" height="6" fill="#334155" rx="1"/></svg>'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Domande frequenti', 'items' => []];
    }

    public static function defaultSettings(): array
    {
        return ['include_cancellation_policy' => false];
    }

    public static function contentRules(): array
    {
        return [
            'content.title' => ['required', 'string', 'max:80'],
            'content.items' => ['array'],
            'content.items.*.question' => ['required', 'string', 'max:200'],
            'content.items.*.answer' => ['required', 'string', 'max:1000'],
        ];
    }

    public static function settingsRules(): array
    {
        return ['settings.include_cancellation_policy' => ['boolean']];
    }

    public static function filamentFields(?\App\Models\BusinessPageBlock $record = null): array
    {
        return [
            TextInput::make('content.title')->label('Titolo sezione')->required()->maxLength(80),
            Repeater::make('content.items')
                ->label('Domande e risposte')
                ->schema([
                    TextInput::make('question')->label('Domanda')->required()->maxLength(200),
                    Textarea::make('answer')->label('Risposta')->required()->rows(3)->maxLength(1000),
                ])
                ->defaultItems(0)
                ->collapsible()
                ->addActionLabel('Aggiungi domanda'),
            Toggle::make('settings.include_cancellation_policy')
                ->label('Mostra politica di cancellazione')
                ->helperText('Aggiunge automaticamente la politica di cancellazione in fondo alla sezione FAQ.')
                ->default(false),
        ];
    }
}
