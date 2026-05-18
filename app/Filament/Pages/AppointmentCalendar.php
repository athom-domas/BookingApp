<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppointmentCalendarWidget;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class AppointmentCalendar extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.appointment-calendar';

    protected static ?string $navigationLabel = 'Calendario';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Calendario Appuntamenti';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isStaff() ?? false;
    }

    public ?int $staffFilter = null;

    public function staffFilterForm(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('staffFilter')
                ->label('Filtra per staff')
                ->options(fn () => User::role('staff')->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Tutti i membri')
                ->live(),
        ]);
    }

    public function updatedStaffFilter(?int $value): void
    {
        $this->dispatch('calendar-staff-filter-updated', staffId: $value)
            ->to(AppointmentCalendarWidget::class);
    }

    protected function getHeaderWidgets(): array
    {
        return [AppointmentCalendarWidget::class];
    }
}
