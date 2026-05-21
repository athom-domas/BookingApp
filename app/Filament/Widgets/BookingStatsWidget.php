<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $user    = auth()->user();
        $isAdmin = $user?->isAdmin();

        $base = $isAdmin
            ? Appointment::query()
            : Appointment::where('staff_id', $user?->id);

        $stats = [
            Stat::make(
                $isAdmin ? 'Appuntamenti oggi' : 'I miei appuntamenti oggi',
                (clone $base)->whereDate('scheduled_date', today())->count()
            ),
            Stat::make(
                $isAdmin ? 'Appuntamenti questo mese' : 'I miei appuntamenti questo mese',
                (clone $base)
                    ->whereMonth('scheduled_date', now()->month)
                    ->whereYear('scheduled_date', now()->year)
                    ->count()
            ),
        ];

        if ($isAdmin) {
            $stats[] = Stat::make(
                'Ricavi del mese',
                '€ ' . number_format(
                    Payment::completed()
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('amount'),
                    2, ',', '.'
                )
            );
        }

        return $stats;
    }
}
