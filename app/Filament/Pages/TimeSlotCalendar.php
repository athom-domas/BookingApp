<?php

namespace App\Filament\Pages;

use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class TimeSlotCalendar extends Page
{
    protected string $view = 'filament.pages.time-slot-calendar';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Slot';

    protected static ?string $title = 'Calendario Slot';

    protected static ?int $navigationSort = 4;

    public ?int $staffId = null;

    public string $monthStart;

    public function mount(): void
    {
        $this->monthStart = now()->startOfMonth()->format('Y-m-d');
    }

    public function previousMonth(): void
    {
        $this->monthStart = Carbon::parse($this->monthStart)->subMonth()->startOfMonth()->format('Y-m-d');
    }

    public function nextMonth(): void
    {
        $this->monthStart = Carbon::parse($this->monthStart)->addMonth()->startOfMonth()->format('Y-m-d');
    }

    #[Computed]
    public function calendarCells(): array
    {
        $start     = Carbon::parse($this->monthStart);
        $monthEnd  = $start->copy()->endOfMonth();
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $cells   = [];
        $current = $gridStart->copy();
        while ($current <= $gridEnd) {
            $cells[] = [
                'date'    => $current->copy(),
                'inMonth' => $current->month === $start->month,
            ];
            $current->addDay();
        }

        return $cells;
    }

    #[Computed]
    public function slots(): Collection
    {
        if (! $this->staffId) {
            return collect();
        }

        $start = Carbon::parse($this->monthStart)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        return TimeSlot::where('user_id', $this->staffId)
            ->whereHas('user', fn ($q) => $q->role('staff'))
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (TimeSlot $slot) => $slot->date->format('Y-m-d'));
    }

    #[Computed]
    public function staffOptions(): array
    {
        return User::role('staff')->orderBy('name')->pluck('name', 'id')->toArray();
    }

    #[Computed]
    public function monthLabel(): string
    {
        $months = ['Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
        $date   = Carbon::parse($this->monthStart);

        return $months[$date->month - 1] . ' ' . $date->year;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
