<?php

namespace App\PageBlocks;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

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

    public static function variants(): array
    {
        return [
            'accordion' => ['label' => 'Accordion', 'description' => 'Domande espandibili/collassabili'],
            'list' => ['label' => 'Lista', 'description' => 'Domande e risposte in lista aperta'],
        ];
    }

    public static function defaultContent(): array
    {
        return ['title' => 'Domande frequenti', 'items' => []];
    }

    public static function defaultSettings(): array
    {
        return [];
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
        return [];
    }

    public static function filamentFields(): array
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
        ];
    }
}
