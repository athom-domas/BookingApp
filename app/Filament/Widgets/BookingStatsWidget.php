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

        $todayCount = (clone $base)->whereDate('scheduled_date', today())->count();
        $monthCount = (clone $base)
            ->whereMonth('scheduled_date', now()->month)
            ->whereYear('scheduled_date', now()->year)
            ->count();

        $stats = [
            Stat::make(
                $isAdmin ? 'Appuntamenti oggi' : 'I miei appuntamenti oggi',
                $todayCount
            )
                ->icon('heroicon-o-calendar-days')
                ->description(today()->translatedFormat('l j F'))
                ->color('primary'),
            Stat::make(
                $isAdmin ? 'Appuntamenti questo mese' : 'I miei appuntamenti questo mese',
                $monthCount
            )
                ->icon('heroicon-o-calendar')
                ->description(now()->translatedFormat('F Y'))
                ->color('info'),
        ];

        if ($isAdmin) {
            $revenue = Payment::completed()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            $stats[] = Stat::make(
                'Ricavi del mese',
                '€ ' . number_format((float) $revenue, 2, ',', '.')
            )
                ->icon('heroicon-o-banknotes')
                ->description('Pagamenti completati ' . now()->translatedFormat('F'))
                ->color('success');
        }

        return $stats;
    }
}
