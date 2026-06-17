<?php

namespace App\Filament\Resources\SalonReviewResource\Pages;

use App\Filament\Resources\SalonReviewResource;
use App\Models\SalonReview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalonReviews extends ListRecords
{
    protected static string $resource = SalonReviewResource::class;

    public function mount(): void
    {
        parent::mount();

        SalonReview::whereNull('seen_at')->update(['seen_at' => now()]);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
