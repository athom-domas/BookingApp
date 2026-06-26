<?php

namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\PageTemplateResource;
use App\Models\PageTemplate;
use Filament\Resources\Pages\CreateRecord;

class CreatePageTemplate extends CreateRecord
{
    protected static string $resource = PageTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['is_default'] ?? false) {
            PageTemplate::query()->update(['is_default' => false]);
        }

        return $data;
    }
}
