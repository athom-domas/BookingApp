# Report Page — Admin Statistics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere una pagina "Report" al pannello admin Filament con KPI card, grafici di trend e tabella performance staff, filtrabili per periodo.

**Architecture:** `ReportPage` (Filament `Page`) mantiene lo stato del filtro date come proprietà Livewire e lo distribuisce ai widget tramite eventi `reportFiltersUpdated`. Sei widget in `app/Filament/Widgets/Reports/` coprono KPI, grafici e tabella staff.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Livewire 3, Chart.js (via `Filament\Widgets\ChartWidget`)

---

## Note tecniche importanti

- Colonna importo pagamenti: `payments.amount` (non `payment_amount`)
- Colonna data appuntamento: `appointments.scheduled_date` (non `scheduled_at`)
- Servizi su appuntamento: colonna JSON `service_ids` (array di ID) — le query su servizi usano PHP, non JOIN
- Auto-discovery widget: tutti i widget in `app/Filament/Widgets/` vengono scoperti — i widget Reports vanno esclusi dalla Dashboard

---

## File Structure

**Creare:**
- `app/Filament/Pages/ReportPage.php`
- `resources/views/filament/pages/report.blade.php`
- `app/Filament/Widgets/Reports/RevenueStatsWidget.php`
- `app/Filament/Widgets/Reports/InsightStatsWidget.php`
- `app/Filament/Widgets/Reports/RevenueChartWidget.php`
- `app/Filament/Widgets/Reports/AppointmentsByStatusChartWidget.php`
- `app/Filament/Widgets/Reports/ServiceBreakdownChartWidget.php`
- `app/Filament/Widgets/Reports/StaffPerformanceWidget.php`
- `resources/views/filament/widgets/reports/staff-performance.blade.php`
- `tests/Feature/Filament/Pages/ReportPageTest.php`

**Modificare:**
- `app/Filament/Pages/Dashboard.php` — esclude widget `Reports\*` dalla dashboard

---

## Task 1: ReportPage + Blade view + access control

**Files:**
- Create: `app/Filament/Pages/ReportPage.php`
- Create: `resources/views/filament/pages/report.blade.php`
- Modify: `app/Filament/Pages/Dashboard.php`
- Test: `tests/Feature/Filament/Pages/ReportPageTest.php`

- [ ] **Step 1: Scrivi il test**

```php
// tests/Feature/Filament/Pages/ReportPageTest.php
<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('returns 200 for admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/report')->assertSuccessful();
});

it('returns 403 for staff', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff)->get('/admin/report')->assertForbidden();
});

it('returns 403 for customer', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)->get('/admin/report')->assertForbidden();
});
```

- [ ] **Step 2: Verifica che il test fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: FAIL — 404 (pagina non ancora esistente)

- [ ] **Step 3: Crea ReportPage**

```php
// app/Filament/Pages/ReportPage.php
<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Reports\AppointmentsByStatusChartWidget;
use App\Filament\Widgets\Reports\InsightStatsWidget;
use App\Filament\Widgets\Reports\RevenueChartWidget;
use App\Filament\Widgets\Reports\RevenueStatsWidget;
use App\Filament\Widgets\Reports\ServiceBreakdownChartWidget;
use App\Filament\Widgets\Reports\StaffPerformanceWidget;
use Filament\Pages\Page;

class ReportPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Report';
    protected static ?string $title           = 'Report';
    protected static string  $view            = 'filament.pages.report';
    protected static ?int    $navigationSort  = 10;

    public string $period   = 'month';
    public string $dateFrom = '';
    public string $dateTo   = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->endOfMonth()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        [$this->dateFrom, $this->dateTo] = match ($period) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'week'  => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'  => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default => [$this->dateFrom, $this->dateTo],
        };
        $this->dispatch('reportFiltersUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);
    }

    public function updatedDateFrom(): void
    {
        $this->period = 'custom';
        $this->dispatch('reportFiltersUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);
    }

    public function updatedDateTo(): void
    {
        $this->period = 'custom';
        $this->dispatch('reportFiltersUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);
    }

    public function getWidgetData(): array
    {
        return ['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo];
    }

    public function getWidgets(): array
    {
        return [
            RevenueStatsWidget::class,
            InsightStatsWidget::class,
            RevenueChartWidget::class,
            AppointmentsByStatusChartWidget::class,
            ServiceBreakdownChartWidget::class,
            StaffPerformanceWidget::class,
        ];
    }
}
```

- [ ] **Step 4: Crea il Blade view**

```blade
{{-- resources/views/filament/pages/report.blade.php --}}
<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3 mb-6">
        <div class="flex gap-2">
            @foreach (['today' => 'Oggi', 'week' => 'Settimana', 'month' => 'Mese', 'year' => 'Anno'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    class="px-3 py-1.5 text-sm rounded-lg border transition-colors
                        {{ $period === $key
                            ? 'bg-primary-600 text-white border-primary-600'
                            : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <input
                type="date"
                wire:model.live="dateFrom"
                class="px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm"
            >
            <span>→</span>
            <input
                type="date"
                wire:model.live="dateTo"
                class="px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm"
            >
        </div>
    </div>

    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :data="$this->getWidgetData()"
    />
</x-filament-panels::page>
```

- [ ] **Step 5: Aggiorna Dashboard per escludere widget Reports**

In `app/Filament/Pages/Dashboard.php`, modifica `getWidgets()`:

```php
public function getWidgets(): array
{
    return collect(parent::getWidgets())
        ->reject(fn ($widget) => $widget === AppointmentCalendarWidget::class
            || str_starts_with($widget, 'App\\Filament\\Widgets\\Reports\\'))
        ->values()
        ->all();
}
```

- [ ] **Step 6: Verifica che i test passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/ReportPage.php \
        resources/views/filament/pages/report.blade.php \
        app/Filament/Pages/Dashboard.php \
        tests/Feature/Filament/Pages/ReportPageTest.php
git commit -m "feat: add ReportPage with filter controls and access control"
```

---

## Task 2: RevenueStatsWidget (KPI riga 1)

**Files:**
- Create: `app/Filament/Widgets/Reports/RevenueStatsWidget.php`
- Modify: `tests/Feature/Filament/Pages/ReportPageTest.php`

- [ ] **Step 1: Aggiungi i test**

Aggiungi in fondo a `tests/Feature/Filament/Pages/ReportPageTest.php`:

```php
it('shows revenue stats labels', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Incasso totale')
        ->assertSee('Appuntamenti')
        ->assertSee('Tasso cancellazione')
        ->assertSee('Staff più produttivo');
});

it('shows correct total revenue in range', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $appt = Appointment::factory()->create([
        'scheduled_date' => now()->startOfMonth()->addDays(2),
        'status'         => 'completed',
    ]);
    Payment::factory()->create([
        'appointment_id' => $appt->id,
        'user_id'        => $appt->user_id,
        'amount'         => 120.00,
        'status'         => 'completed',
    ]);

    // Fuori range — non deve apparire nel totale
    $apptOld = Appointment::factory()->create([
        'scheduled_date' => now()->subMonths(2),
        'status'         => 'completed',
    ]);
    Payment::factory()->create([
        'appointment_id' => $apptOld->id,
        'user_id'        => $apptOld->user_id,
        'amount'         => 999.00,
        'status'         => 'completed',
    ]);

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('120');
});
```

- [ ] **Step 2: Verifica che i test falliscano**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php --filter "revenue stats"`
Expected: FAIL

- [ ] **Step 3: Crea RevenueStatsWidget**

```php
// app/Filament/Widgets/Reports/RevenueStatsWidget.php
<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class RevenueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort      = 1;
    protected static bool $isLazy    = false;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getStats(): array
    {
        $from = $this->dateFrom ?? now()->startOfMonth()->toDateString();
        $to   = $this->dateTo   ?? now()->endOfMonth()->toDateString();

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $totalRevenue = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$fromDt, $toDt])
            ->sum('payments.amount');

        $totalAppointments = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])->count();

        $cancelledCount = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->where('status', 'cancelled')
            ->count();

        $cancellationRate = $totalAppointments > 0
            ? round($cancelledCount / $totalAppointments * 100, 1)
            : 0;

        $topStaffRow = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->where('status', 'completed')
            ->select('staff_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('staff_id')
            ->orderByDesc('cnt')
            ->first();

        $topStaffName  = $topStaffRow ? (User::find($topStaffRow->staff_id)?->name ?? '-') : '-';
        $topStaffCount = $topStaffRow?->cnt ?? 0;

        return [
            Stat::make('Incasso totale', '€ ' . number_format((float) $totalRevenue, 2, ',', '.')),

            Stat::make('Appuntamenti', $totalAppointments),

            Stat::make('Tasso cancellazione', $cancellationRate . '%')
                ->color($cancellationRate > 20 ? 'danger' : 'success'),

            Stat::make('Staff più produttivo', $topStaffName)
                ->description($topStaffCount . ' appuntamenti completati'),
        ];
    }
}
```

- [ ] **Step 4: Verifica che i test passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/Reports/RevenueStatsWidget.php \
        tests/Feature/Filament/Pages/ReportPageTest.php
git commit -m "feat: add RevenueStatsWidget (KPI row 1)"
```

---

## Task 3: InsightStatsWidget (KPI riga 2)

**Files:**
- Create: `app/Filament/Widgets/Reports/InsightStatsWidget.php`
- Modify: `tests/Feature/Filament/Pages/ReportPageTest.php`

- [ ] **Step 1: Aggiungi i test**

```php
it('shows insight stats labels', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Incasso medio')
        ->assertSee('Clienti unici')
        ->assertSee('Servizio più richiesto')
        ->assertSee('Appuntamenti in attesa');
});

it('counts unique customers correctly', function () {
    $admin     = User::factory()->create();
    $admin->assignRole('admin');
    $customer1 = User::factory()->create();
    $customer2 = User::factory()->create();

    // stesso cliente due volte → 1 unico
    Appointment::factory()->count(2)->create([
        'user_id'        => $customer1->id,
        'scheduled_date' => now()->startOfMonth()->addDays(1),
    ]);
    Appointment::factory()->create([
        'user_id'        => $customer2->id,
        'scheduled_date' => now()->startOfMonth()->addDays(2),
    ]);

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Clienti unici')
        ->assertSeeInOrder(['Clienti unici', '2']);
});
```

- [ ] **Step 2: Verifica che i test falliscano**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php --filter "insight stats|unique customers"`
Expected: FAIL

- [ ] **Step 3: Crea InsightStatsWidget**

```php
// app/Filament/Widgets/Reports/InsightStatsWidget.php
<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class InsightStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort      = 2;
    protected static bool $isLazy    = false;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getStats(): array
    {
        $from   = $this->dateFrom ?? now()->startOfMonth()->toDateString();
        $to     = $this->dateTo   ?? now()->endOfMonth()->toDateString();
        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $paidCount = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$fromDt, $toDt])
            ->count();

        $totalRevenue = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$fromDt, $toDt])
            ->sum('payments.amount');

        $avgRevenue = $paidCount > 0 ? round((float) $totalRevenue / $paidCount, 2) : 0;

        $uniqueCustomers = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->distinct('user_id')
            ->count('user_id');

        $pendingCount = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->where('status', 'pending')
            ->count();

        [$topServiceName, $topServiceCount] = $this->topService($fromDt, $toDt);

        return [
            Stat::make('Incasso medio', '€ ' . number_format($avgRevenue, 2, ',', '.'))
                ->description('per appuntamento pagato'),

            Stat::make('Clienti unici', $uniqueCustomers),

            Stat::make('Servizio più richiesto', $topServiceName)
                ->description($topServiceCount . ' prenotazioni'),

            Stat::make('Appuntamenti in attesa', $pendingCount)
                ->color($pendingCount > 0 ? 'warning' : 'success'),
        ];
    }

    private function topService(string $fromDt, string $toDt): array
    {
        $appointments = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->get(['service_ids']);

        $counts = [];
        foreach ($appointments as $appt) {
            foreach ($appt->service_ids ?? [] as $serviceId) {
                $counts[$serviceId] = ($counts[$serviceId] ?? 0) + 1;
            }
        }

        if (empty($counts)) {
            return ['-', 0];
        }

        arsort($counts);
        $topId    = (int) array_key_first($counts);
        $topCount = $counts[$topId];

        return [Service::find($topId)?->name ?? '-', $topCount];
    }
}
```

- [ ] **Step 4: Verifica che i test passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/Reports/InsightStatsWidget.php \
        tests/Feature/Filament/Pages/ReportPageTest.php
git commit -m "feat: add InsightStatsWidget (KPI row 2)"
```

---

## Task 4: RevenueChartWidget (line chart)

**Files:**
- Create: `app/Filament/Widgets/Reports/RevenueChartWidget.php`
- Modify: `tests/Feature/Filament/Pages/ReportPageTest.php`

- [ ] **Step 1: Aggiungi il test**

```php
it('shows revenue chart heading', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Incassi nel tempo');
});
```

- [ ] **Step 2: Verifica che il test fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php --filter "revenue chart"`
Expected: FAIL

- [ ] **Step 3: Crea RevenueChartWidget**

Raggruppa per giorno se il range ≤ 31 giorni, per mese altrimenti.

```php
// app/Filament/Widgets/Reports/RevenueChartWidget.php
<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class RevenueChartWidget extends ChartWidget
{
    protected static ?string $heading   = 'Incassi nel tempo';
    protected static ?int    $sort      = 3;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getData(): array
    {
        $from  = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to    = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());
        $byDay = $from->diffInDays($to) <= 31;

        [$displayLabels, $revenueMap] = $byDay
            ? $this->dailyRevenue($from, $to)
            : $this->monthlyRevenue($from, $to);

        $data = array_map(fn ($label) => $revenueMap[$label] ?? 0, $displayLabels);

        return [
            'datasets' => [[
                'label'           => 'Incasso (€)',
                'data'            => $data,
                'borderColor'     => '#2563eb',
                'backgroundColor' => 'rgba(37,99,235,0.1)',
                'fill'            => true,
                'tension'         => 0.3,
            ]],
            'labels' => $displayLabels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function dailyRevenue(Carbon $from, Carbon $to): array
    {
        $rows = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                DB::raw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m-%d') as period"),
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        $labels = [];
        $map    = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key       = $day->format('Y-m-d');
            $display   = $day->format('d/m');
            $labels[]  = $display;
            $map[$display] = isset($rows[$key]) ? (float) $rows[$key] : 0;
        }

        return [$labels, $map];
    }

    private function monthlyRevenue(Carbon $from, Carbon $to): array
    {
        $rows = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                DB::raw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m') as period"),
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        $labels  = [];
        $map     = [];
        $current = $from->copy()->startOfMonth();
        while ($current <= $to) {
            $key       = $current->format('Y-m');
            $display   = $current->translatedFormat('M Y');
            $labels[]  = $display;
            $map[$display] = isset($rows[$key]) ? (float) $rows[$key] : 0;
            $current->addMonth();
        }

        return [$labels, $map];
    }
}
```

- [ ] **Step 4: Verifica che i test passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/Reports/RevenueChartWidget.php \
        tests/Feature/Filament/Pages/ReportPageTest.php
git commit -m "feat: add RevenueChartWidget (line chart)"
```

---

## Task 5: AppointmentsByStatusChartWidget (bar chart)

**Files:**
- Create: `app/Filament/Widgets/Reports/AppointmentsByStatusChartWidget.php`
- Modify: `tests/Feature/Filament/Pages/ReportPageTest.php`

- [ ] **Step 1: Aggiungi il test**

```php
it('shows appointments by status chart heading', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Appuntamenti per stato');
});
```

- [ ] **Step 2: Verifica che il test fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php --filter "appointments by status"`
Expected: FAIL

- [ ] **Step 3: Crea AppointmentsByStatusChartWidget**

```php
// app/Filament/Widgets/Reports/AppointmentsByStatusChartWidget.php
<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AppointmentsByStatusChartWidget extends ChartWidget
{
    protected static ?string $heading   = 'Appuntamenti per stato';
    protected static ?int    $sort      = 4;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getData(): array
    {
        $from  = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to    = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());
        $byDay = $from->diffInDays($to) <= 31;

        // dbKeys: ['Y-m-d' => 'd/m'] per daily, ['Y-m' => 'M Y'] per monthly
        $dbKeys = $byDay ? $this->dailyKeys($from, $to) : $this->monthlyKeys($from, $to);
        $format = $byDay ? '%Y-%m-%d' : '%Y-%m';

        $rows = Appointment::whereBetween('scheduled_date', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                DB::raw("DATE_FORMAT(scheduled_date, '$format') as period"),
                'status',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('period', 'status')
            ->get();

        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $colors   = [
            'pending'   => 'rgba(234,179,8,0.7)',
            'confirmed' => 'rgba(59,130,246,0.7)',
            'completed' => 'rgba(34,197,94,0.7)',
            'cancelled' => 'rgba(239,68,68,0.7)',
        ];
        $italianLabels = [
            'pending'   => 'In attesa',
            'confirmed' => 'Confermati',
            'completed' => 'Completati',
            'cancelled' => 'Cancellati',
        ];

        $datasets = [];
        foreach ($statuses as $status) {
            $map = $rows->where('status', $status)->pluck('cnt', 'period')->toArray();
            $datasets[] = [
                'label'           => $italianLabels[$status],
                'data'            => array_map(fn ($dbKey) => (int) ($map[$dbKey] ?? 0), array_keys($dbKeys)),
                'backgroundColor' => $colors[$status],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels'   => array_values($dbKeys),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function dailyKeys(Carbon $from, Carbon $to): array
    {
        $keys = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $keys[$day->format('Y-m-d')] = $day->format('d/m');
        }
        return $keys;
    }

    private function monthlyKeys(Carbon $from, Carbon $to): array
    {
        $keys    = [];
        $current = $from->copy()->startOfMonth();
        while ($current <= $to) {
            $keys[$current->format('Y-m')] = $current->translatedFormat('M Y');
            $current->addMonth();
        }
        return $keys;
    }
}
```

- [ ] **Step 4: Verifica che i test passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/Reports/AppointmentsByStatusChartWidget.php \
        tests/Feature/Filament/Pages/ReportPageTest.php
git commit -m "feat: add AppointmentsByStatusChartWidget (grouped bar)"
```

---

## Task 6: ServiceBreakdownChartWidget (bar orizzontale)

**Files:**
- Create: `app/Filament/Widgets/Reports/ServiceBreakdownChartWidget.php`
- Modify: `tests/Feature/Filament/Pages/ReportPageTest.php`

- [ ] **Step 1: Aggiungi il test**

```php
it('shows service breakdown chart heading', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Appuntamenti per servizio');
});
```

- [ ] **Step 2: Verifica che il test fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php --filter "service breakdown"`
Expected: FAIL

- [ ] **Step 3: Crea ServiceBreakdownChartWidget**

```php
// app/Filament/Widgets/Reports/ServiceBreakdownChartWidget.php
<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class ServiceBreakdownChartWidget extends ChartWidget
{
    protected static ?string $heading   = 'Appuntamenti per servizio';
    protected static ?int    $sort      = 5;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getData(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to   = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());

        $appointments = Appointment::whereBetween('scheduled_date', [$from->startOfDay(), $to->copy()->endOfDay()])
            ->get(['service_ids']);

        $counts = [];
        foreach ($appointments as $appt) {
            foreach ($appt->service_ids ?? [] as $serviceId) {
                $counts[$serviceId] = ($counts[$serviceId] ?? 0) + 1;
            }
        }

        arsort($counts);

        $serviceIds   = array_keys($counts);
        $serviceNames = Service::whereIn('id', $serviceIds)->pluck('name', 'id');

        $labels = array_map(fn ($id) => $serviceNames[$id] ?? "Servizio #$id", $serviceIds);
        $data   = array_values($counts);

        return [
            'datasets' => [[
                'label'           => 'Appuntamenti',
                'data'            => $data,
                'backgroundColor' => 'rgba(37,99,235,0.7)',
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins'   => ['legend' => ['display' => false]],
        ];
    }
}
```

- [ ] **Step 4: Verifica che i test passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/Reports/ServiceBreakdownChartWidget.php \
        tests/Feature/Filament/Pages/ReportPageTest.php
git commit -m "feat: add ServiceBreakdownChartWidget (horizontal bar)"
```

---

## Task 7: StaffPerformanceWidget (tabella Blade)

**Files:**
- Create: `app/Filament/Widgets/Reports/StaffPerformanceWidget.php`
- Create: `resources/views/filament/widgets/reports/staff-performance.blade.php`
- Modify: `tests/Feature/Filament/Pages/ReportPageTest.php`

- [ ] **Step 1: Aggiungi i test**

```php
it('shows staff performance heading', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Performance Staff');
});

it('shows staff member with revenue in performance table', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staffMember = User::factory()->create(['name' => 'Marco Rossi']);
    $staffMember->assignRole('staff');

    $appt = Appointment::factory()->create([
        'staff_id'       => $staffMember->id,
        'scheduled_date' => now()->startOfMonth()->addDays(3),
        'status'         => 'completed',
    ]);
    Payment::factory()->create([
        'appointment_id' => $appt->id,
        'user_id'        => $appt->user_id,
        'amount'         => 85.00,
        'status'         => 'completed',
    ]);

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Marco Rossi')
        ->assertSee('85');
});
```

- [ ] **Step 2: Verifica che i test falliscano**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php --filter "staff performance"`
Expected: FAIL

- [ ] **Step 3: Crea StaffPerformanceWidget**

```php
// app/Filament/Widgets/Reports/StaffPerformanceWidget.php
<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class StaffPerformanceWidget extends Widget
{
    protected static string $view      = 'filament.widgets.reports.staff-performance';
    protected static ?int   $sort      = 6;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function getRows(): Collection
    {
        $from   = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to     = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());
        $fromDt = $from->startOfDay();
        $toDt   = $to->copy()->endOfDay();

        $rows = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->join('users', 'users.id', '=', 'appointments.staff_id')
            ->leftJoin('payments', function ($join) {
                $join->on('payments.appointment_id', '=', 'appointments.id')
                     ->where('payments.status', '=', 'completed');
            })
            ->select(
                'appointments.staff_id',
                'users.name',
                DB::raw('COUNT(appointments.id) as total'),
                DB::raw('SUM(CASE WHEN appointments.status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
                DB::raw('COALESCE(SUM(payments.amount), 0) as revenue')
            )
            ->groupBy('appointments.staff_id', 'users.name')
            ->orderByDesc('revenue')
            ->get();

        // Calcola servizio top per staff in PHP (service_ids è JSON array)
        $allAppointments = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->get(['staff_id', 'service_ids']);

        $countsByStaff = [];
        foreach ($allAppointments as $appt) {
            foreach ($appt->service_ids ?? [] as $sid) {
                $countsByStaff[$appt->staff_id][$sid] = ($countsByStaff[$appt->staff_id][$sid] ?? 0) + 1;
            }
        }

        $allServiceIds = collect($countsByStaff)
            ->flatMap(fn ($c) => array_keys($c))
            ->unique()->values()->all();
        $serviceNames = Service::whereIn('id', $allServiceIds)->pluck('name', 'id');

        return $rows->map(function ($row) use ($countsByStaff, $serviceNames) {
            $staffCounts = $countsByStaff[$row->staff_id] ?? [];
            arsort($staffCounts);
            $topId               = (int) (array_key_first($staffCounts) ?? 0);
            $row->top_service        = $topId ? ($serviceNames[$topId] ?? '-') : '-';
            $row->cancellation_rate  = $row->total > 0
                ? round((int) $row->cancelled / (int) $row->total * 100, 1)
                : 0;
            return $row;
        });
    }
}
```

- [ ] **Step 4: Crea il Blade view**

```blade
{{-- resources/views/filament/widgets/reports/staff-performance.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section heading="Performance Staff">
        @php $rows = $this->getRows() @endphp

        @if($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nessun dato per il periodo selezionato.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-6 font-medium">Staff</th>
                            <th class="py-2 pr-6 font-medium text-right">Appuntamenti</th>
                            <th class="py-2 pr-6 font-medium text-right">Incasso</th>
                            <th class="py-2 pr-6 font-medium text-right">% Cancellazione</th>
                            <th class="py-2 font-medium">Servizio top</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-6 font-medium text-gray-900 dark:text-gray-100">{{ $row->name }}</td>
                                <td class="py-2 pr-6 text-right text-gray-700 dark:text-gray-300">{{ $row->total }}</td>
                                <td class="py-2 pr-6 text-right text-gray-700 dark:text-gray-300">
                                    € {{ number_format((float) $row->revenue, 2, ',', '.') }}
                                </td>
                                <td class="py-2 pr-6 text-right">
                                    <span class="{{ $row->cancellation_rate > 20 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $row->cancellation_rate }}%
                                    </span>
                                </td>
                                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $row->top_service }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

- [ ] **Step 5: Verifica che tutti i test passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/ReportPageTest.php`
Expected: PASS (tutti i test)

- [ ] **Step 6: Esegui la suite completa**

Run: `docker-compose run --rm app ./vendor/bin/pest`
Expected: PASS (nessuna regressione)

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Widgets/Reports/StaffPerformanceWidget.php \
        resources/views/filament/widgets/reports/staff-performance.blade.php \
        tests/Feature/Filament/Pages/ReportPageTest.php
git commit -m "feat: add StaffPerformanceWidget with Blade table"
```
