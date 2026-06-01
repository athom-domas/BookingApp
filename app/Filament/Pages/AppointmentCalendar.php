<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppointmentCalendarWidget;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
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
        $user = Filament::auth()->user();

        return $user?->isAdmin() || $user?->isStaff() ?? false;
    }

    public array $filterStaff    = [];
    public array $filterStatus   = [];
    public array $filterService  = [];
    public array $filterCustomer = [];

    public function filtersForm(Schema $schema): Schema
    {
        $user       = Filament::auth()->user();
        $businessId = $user?->business_id;
        $fields     = [];

        if ($user?->isAdmin() || ($user?->isStaff() && $user->can('appointments.view_all'))) {
            $fields[] = Select::make('filterStaff')
                ->label('Staff')
                ->options(fn() => User::role('staff')->where('business_id', $businessId)->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Tutti')
                ->multiple()
                ->live();
        }

        $fields[] = Select::make('filterStatus')
            ->label('Stato')
            ->options([
                'pending'   => 'In attesa',
                'confirmed' => 'Confermato',
                'completed' => 'Completato',
                'cancelled' => 'Annullato',
            ])
            ->placeholder('Tutti')
            ->multiple()
            ->live();

        $fields[] = Select::make('filterService')
            ->label('Servizio')
            ->options(fn() => Service::orderBy('name')->pluck('name', 'id'))
            ->placeholder('Tutti')
            ->multiple()
            ->live();

        $fields[] = Select::make('filterCustomer')
            ->label('Cliente')
            ->options(fn() => User::role('customer')->where('business_id', $businessId)->orderBy('name')->pluck('name', 'id'))
            ->placeholder('Tutti')
            ->multiple()
            ->live();

        return $schema->schema($fields)->columns(4);
    }

    public function updatedFilterStaff(): void
    {
        $this->dispatchFilters();
    }
    public function updatedFilterStatus(): void
    {
        $this->dispatchFilters();
    }
    public function updatedFilterService(): void
    {
        $this->dispatchFilters();
    }
    public function updatedFilterCustomer(): void
    {
        $this->dispatchFilters();
    }

    private function dispatchFilters(): void
    {
        $this->dispatch(
            'calendar-filters-updated',
            staff: $this->filterStaff,
            status: $this->filterStatus,
            service: $this->filterService,
            customer: $this->filterCustomer,
        )->to(AppointmentCalendarWidget::class);
    }

    protected function getFooterWidgets(): array
    {
        return [AppointmentCalendarWidget::class];
    }
}
