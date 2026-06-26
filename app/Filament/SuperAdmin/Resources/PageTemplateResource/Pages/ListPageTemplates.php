<?php

namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\PageTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageTemplates extends ListRecords
{
    protected static string $resource = PageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
