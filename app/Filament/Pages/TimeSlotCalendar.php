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

    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->format('Y-m-d');
    }

    #[Computed]
    public function weekDays(): array
    {
        $start = Carbon::parse($this->weekStart);

        return array_map(fn (int $i) => $start->copy()->addDays($i), range(0, 6));
    }

    #[Computed]
    public function slots(): Collection
    {
        if (! $this->staffId) {
            return collect();
        }

        $start = Carbon::parse($this->weekStart);
        $end = $start->copy()->addDays(6);

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
    public function weekLabel(): string
    {
        $start = Carbon::parse($this->weekStart);
        $end = $start->copy()->addDays(6);

        return $start->format('d/m') . ' – ' . $end->format('d/m/Y');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
