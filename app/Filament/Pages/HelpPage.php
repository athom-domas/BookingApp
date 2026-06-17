<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class HelpPage extends Page
{
    protected static ?string $navigationLabel = 'Aiuto';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $slug = 'aiuto';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.help';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
