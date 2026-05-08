<?php

namespace App\Filament\Resources\AvailabilityRuleResource\Pages;

use App\Filament\Resources\AvailabilityRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAvailabilityRule extends EditRecord
{
    protected static string $resource = AvailabilityRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
