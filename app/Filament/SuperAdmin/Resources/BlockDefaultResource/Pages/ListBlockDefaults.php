<?php

namespace App\Filament\SuperAdmin\Resources\BlockDefaultResource\Pages;

use App\Filament\SuperAdmin\Resources\BlockDefaultResource;
use Filament\Resources\Pages\ListRecords;

class ListBlockDefaults extends ListRecords
{
    protected static string $resource = BlockDefaultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
