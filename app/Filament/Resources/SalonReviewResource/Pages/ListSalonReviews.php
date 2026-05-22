<?php

namespace App\Filament\Resources\SalonReviewResource\Pages;

use App\Filament\Resources\SalonReviewResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalonReviews extends ListRecords
{
    protected static string $resource = SalonReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
