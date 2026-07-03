<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppointmentCalendarWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return collect(parent::getWidgets())
            ->reject(fn ($widget) => $widget === AppointmentCalendarWidget::class
                || str_starts_with($widget, 'App\\Filament\\Widgets\\Reports\\'))
            ->values()
            ->all();
    }
}
