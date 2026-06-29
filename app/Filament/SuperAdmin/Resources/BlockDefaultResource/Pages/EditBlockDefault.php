<?php

namespace App\Filament\SuperAdmin\Resources\BlockDefaultResource\Pages;

use App\Filament\SuperAdmin\Resources\BlockDefaultResource;
use Filament\Resources\Pages\EditRecord;

class EditBlockDefault extends EditRecord
{
    protected static string $resource = BlockDefaultResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
