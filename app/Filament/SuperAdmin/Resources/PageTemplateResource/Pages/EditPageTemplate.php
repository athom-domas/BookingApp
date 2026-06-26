<?php

namespace App\Filament\SuperAdmin\Resources\PageTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\PageTemplateResource;
use App\Models\PageTemplate;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPageTemplate extends EditRecord
{
    protected static string $resource = PageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['is_default'] ?? false) {
            PageTemplate::where('id', '!=', $this->record->id)->update(['is_default' => false]);
        }

        return $data;
    }
}
