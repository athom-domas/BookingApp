<?php

namespace App\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\AppointmentResource;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

public function filterToday(): void
    {
        $this->tableFilters['scheduled_date'] = [
            'from'  => now()->toDateString(),
            'until' => now()->toDateString(),
        ];
    }
}
