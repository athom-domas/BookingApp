<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    public function filterToday(): void
    {
        $this->tableFilters['created_at'] = [
            'from'  => now()->toDateString(),
            'until' => now()->toDateString(),
        ];
    }
}
