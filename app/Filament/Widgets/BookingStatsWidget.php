<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        return [
            Stat::make(
                'Appuntamenti oggi',
                Appointment::whereDate('scheduled_date', today())->count()
            ),
            Stat::make(
                'Appuntamenti questo mese',
                Appointment::whereMonth('scheduled_date', now()->month)
                    ->whereYear('scheduled_date', now()->year)
                    ->count()
            ),
            Stat::make(
                'Ricavi del mese',
                '€ ' . number_format(
                    Payment::completed()
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('amount'),
                    2, ',', '.'
                )
            ),
        ];
    }
}
