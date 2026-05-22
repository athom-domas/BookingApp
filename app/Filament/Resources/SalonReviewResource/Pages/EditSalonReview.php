<?php

namespace App\Filament\Resources\SalonReviewResource\Pages;

use App\Filament\Resources\SalonReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalonReview extends EditRecord
{
    protected static string $resource = SalonReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
