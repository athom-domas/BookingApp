<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppointmentCalendarWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    public function getWidgets(): array
    {
        return collect(parent::getWidgets())
            ->reject(fn ($widget) => $widget === AppointmentCalendarWidget::class)
            ->values()
            ->all();
    }
}
