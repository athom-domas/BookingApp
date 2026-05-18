# Calendario Appuntamenti — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere una pagina calendario nel pannello admin Filament 4 che mostra gli appuntamenti come eventi FullCalendar, con colori per staff, viste mese/settimana/giorno, filtro staff per admin, e popup inline con cambio stato e registrazione pagamento.

**Architecture:** Un `AppointmentCalendarWidget` (estende `FullCalendarWidget` del package `saade/filament-fullcalendar`) ospitato in una `AppointmentCalendar` Page Filament dedicata. `fetchEvents()` carica appuntamenti nel range con eager load servizi (no N+1). Il filtro staff è un `filterForm()` nel widget, visibile solo all'admin. Il popup si apre via `onEventClick` e monta una Filament Action con form inline.

**Tech Stack:** Laravel 13, Filament 4, `saade/filament-fullcalendar` (verificare v4 in Task 1), FullCalendar.js (bundled dal package), Pest + Livewire test helpers

---

## File map

| Operazione | Percorso |
|-----------|----------|
| Crea | `app/Filament/Widgets/AppointmentCalendarWidget.php` |
| Crea | `app/Filament/Pages/AppointmentCalendar.php` |
| Crea | `resources/views/filament/pages/appointment-calendar.blade.php` |
| Crea | `tests/Feature/Filament/AppointmentCalendarTest.php` |

Nessuna modifica a `AdminPanelProvider` (auto-discovery già attivo). Nessuna migrazione.

---

### Task 1: Installare saade/filament-fullcalendar

**Files:**
- Modify: `composer.json` (via composer require)

- [ ] **Step 1: Verificare versione compatibile con Filament 4**

```bash
docker-compose run --rm --no-deps app composer show saade/filament-fullcalendar 2>&1 | head -20
```

Se il package non esiste o non supporta Filament 4 (errore di constraint), usare l'approccio B: installare FullCalendar.js via npm (`npm install @fullcalendar/core @fullcalendar/daygrid @fullcalendar/timegrid @fullcalendar/interaction`) e creare un widget Livewire custom. La logica di business dei Task 2-7 rimane identica — cambia solo la classe base del widget.

- [ ] **Step 2: Installare il package**

```bash
docker-compose run --rm --no-deps app composer require saade/filament-fullcalendar
```

Expected: package installato senza errori di constraint.

- [ ] **Step 3: Pubblicare la configurazione**

```bash
docker-compose run --rm app php artisan vendor:publish --tag="filament-fullcalendar-config"
```

Se il tag non esiste, saltare — la configurazione può essere passata direttamente al widget.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/
git commit -m "chore: install saade/filament-fullcalendar"
```

---

### Task 2: AppointmentCalendarWidget — fetchEvents con accesso per ruolo

**Files:**
- Create: `app/Filament/Widgets/AppointmentCalendarWidget.php`
- Create: `tests/Feature/Filament/AppointmentCalendarTest.php`

- [ ] **Step 1: Creare il file di test**

```php
<?php

use App\Filament\Widgets\AppointmentCalendarWidget;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

$fetchRange = fn () => [
    'start' => now()->subDay()->toIso8601String(),
    'end'   => now()->addMonth()->toIso8601String(),
];

it('restituisce tutti gli appuntamenti per admin', function () use (&$fetchRange) {
    $admin  = User::factory()->create()->assignRole('admin');
    $staff1 = User::factory()->create()->assignRole('staff');
    $staff2 = User::factory()->create()->assignRole('staff');

    Appointment::factory()->create(['staff_id' => $staff1->id, 'scheduled_date' => now()->addDays(1)]);
    Appointment::factory()->create(['staff_id' => $staff2->id, 'scheduled_date' => now()->addDays(2)]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(2);
});

it('restituisce solo i propri appuntamenti per lo staff', function () use (&$fetchRange) {
    $staff = User::factory()->create()->assignRole('staff');
    $other = User::factory()->create()->assignRole('staff');

    $own = Appointment::factory()->create(['staff_id' => $staff->id, 'scheduled_date' => now()->addDays(1)]);
    Appointment::factory()->create(['staff_id' => $other->id, 'scheduled_date' => now()->addDays(2)]);

    $this->actingAs($staff);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($own->id);
});
```

- [ ] **Step 2: Eseguire i test — devono fallire**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: FAIL — classe `AppointmentCalendarWidget` non trovata.

- [ ] **Step 3: Creare il widget**

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class AppointmentCalendarWidget extends FullCalendarWidget
{
    public ?int $staffFilter = null;

    public function fetchEvents(array $fetchInfo): array
    {
        $query = Appointment::query()
            ->with(['user', 'staff'])
            ->whereBetween('scheduled_date', [$fetchInfo['start'], $fetchInfo['end']]);

        $user = auth()->user();

        if ($user->isStaff()) {
            $query->where('staff_id', $user->id);
        } elseif ($this->staffFilter) {
            $query->where('staff_id', $this->staffFilter);
        }

        $appointments = $query->get();

        $allServiceIds = $appointments
            ->flatMap(fn ($a) => $a->service_ids ?? [])
            ->unique()
            ->values()
            ->all();

        $services = Service::whereIn('id', $allServiceIds)->get()->keyBy('id');

        return $appointments->map(function ($appointment) use ($services) {
            $duration = collect($appointment->service_ids ?? [])
                ->sum(fn ($id) => $services->get($id)?->duration_minutes ?? 30);

            $serviceNames = collect($appointment->service_ids ?? [])
                ->map(fn ($id) => $services->get($id)?->name)
                ->filter()
                ->implode(', ');

            return [
                'id'              => $appointment->id,
                'title'           => $appointment->user->name . ' – ' . $serviceNames,
                'start'           => $appointment->scheduled_date->toIso8601String(),
                'end'             => $appointment->scheduled_date->copy()->addMinutes($duration)->toIso8601String(),
                'backgroundColor' => $this->staffColor($appointment->staff_id),
                'extendedProps'   => ['status' => $appointment->status],
            ];
        })->toArray();
    }

    private function staffColor(int $staffId): string
    {
        $palette = [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
            '#8B5CF6', '#EC4899', '#14B8A6', '#F97316',
        ];

        return $palette[$staffId % count($palette)];
    }

    public function filterForm(Form $form): Form
    {
        if (! auth()->user()->isAdmin()) {
            return $form->schema([]);
        }

        return $form->schema([
            Select::make('staffFilter')
                ->label('Filtra per staff')
                ->options(User::role('staff')->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Tutti i membri')
                ->live(),
        ]);
    }

    public function updatedStaffFilter(): void
    {
        $this->dispatch('filament-fullcalendar--refetch');
    }
}
```

> **Nota:** Se il package installato usa un nome evento diverso per il refetch, consultare il README del package (es. `filament-fullcalendar:refetch` o simile).

- [ ] **Step 4: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: 2 test passano.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/AppointmentCalendarWidget.php tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "feat: add AppointmentCalendarWidget with role-based fetchEvents"
```

---

### Task 3: Struttura degli eventi — fine orario e titolo

**Files:**
- Modify: `tests/Feature/Filament/AppointmentCalendarTest.php`

- [ ] **Step 1: Aggiungere test per struttura evento**

Aggiungere in coda a `tests/Feature/Filament/AppointmentCalendarTest.php`:

```php
it('calcola end time dalla somma dei duration_minutes dei servizi', function () use (&$fetchRange) {
    $admin   = User::factory()->create()->assignRole('admin');
    $staff   = User::factory()->create()->assignRole('staff');
    $service1 = Service::factory()->create(['duration_minutes' => 30]);
    $service2 = Service::factory()->create(['duration_minutes' => 45]);

    $start = now()->addDays(1)->startOfHour();

    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'scheduled_date' => $start,
        'service_ids'    => [$service1->id, $service2->id],
    ]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events[0]['end'])->toBe($start->copy()->addMinutes(75)->toIso8601String());
});

it('costruisce il titolo da nome cliente e nomi servizi', function () use (&$fetchRange) {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $staff    = User::factory()->create()->assignRole('staff');
    $service  = Service::factory()->create(['name' => 'Taglio']);

    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(1),
        'service_ids'    => [$service->id],
    ]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events[0]['title'])->toBe('Mario Rossi – Taglio');
});
```

- [ ] **Step 2: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: 4 test passano.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "test: add event end-time and title assertions"
```

---

### Task 4: Filtro staff per admin

**Files:**
- Modify: `tests/Feature/Filament/AppointmentCalendarTest.php`

- [ ] **Step 1: Aggiungere test per il filtro**

Aggiungere in coda a `tests/Feature/Filament/AppointmentCalendarTest.php`:

```php
it('filtra gli eventi per staff quando admin imposta staffFilter', function () use (&$fetchRange) {
    $admin  = User::factory()->create()->assignRole('admin');
    $staff1 = User::factory()->create()->assignRole('staff');
    $staff2 = User::factory()->create()->assignRole('staff');

    $own = Appointment::factory()->create(['staff_id' => $staff1->id, 'scheduled_date' => now()->addDays(1)]);
    Appointment::factory()->create(['staff_id' => $staff2->id, 'scheduled_date' => now()->addDays(2)]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->set('staffFilter', $staff1->id)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($own->id);
});
```

- [ ] **Step 2: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: 5 test passano.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "test: verify admin staff filter in calendar widget"
```

---

### Task 5: AppointmentCalendar Page, navigazione e configurazione viste

**Files:**
- Create: `app/Filament/Pages/AppointmentCalendar.php`
- Create: `resources/views/filament/pages/appointment-calendar.blade.php`
- Modify: `app/Filament/Widgets/AppointmentCalendarWidget.php` (aggiungere `getOptions()`)

La Page è auto-discovered tramite `discoverPages` già configurato in `AdminPanelProvider`. Non serve modifica al provider.

- [ ] **Step 1: Aggiungere test di accesso alla pagina**

Aggiungere in coda a `tests/Feature/Filament/AppointmentCalendarTest.php`:

```php
it('la pagina calendario è accessibile a admin', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/appointment-calendar')
        ->assertSuccessful();
});

it('la pagina calendario è accessibile a staff', function () {
    $staff = User::factory()->create()->assignRole('staff');

    $this->actingAs($staff)
        ->get('/admin/appointment-calendar')
        ->assertSuccessful();
});
```

- [ ] **Step 2: Eseguire i test — devono fallire**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php --filter "pagina calendario"
```

Expected: FAIL — 404 Not Found.

- [ ] **Step 3: Aggiungere `getOptions()` al widget per configurare le viste**

Aggiungere al corpo di `AppointmentCalendarWidget`:

```php
protected function getOptions(): array
{
    return [
        'initialView'  => 'dayGridMonth',
        'headerToolbar' => [
            'left'   => 'prev,next today',
            'center' => 'title',
            'right'  => 'dayGridMonth,timeGridWeek,timeGridDay',
        ],
        'locale' => 'it',
    ];
}
```

> **Nota:** Se il package usa `config()` invece di `getOptions()`, aggiornare `config/filament-fullcalendar.php` con gli stessi valori. Verificare il README del package installato.

- [ ] **Step 4: Creare la Page**

```php
<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AppointmentCalendarWidget;
use Filament\Pages\Page;

class AppointmentCalendar extends Page
{
    protected string $view = 'filament.pages.appointment-calendar';

    protected static ?string $navigationLabel = 'Calendario';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Calendario Appuntamenti';

    protected function getHeaderWidgets(): array
    {
        return [AppointmentCalendarWidget::class];
    }
}
```

- [ ] **Step 5: Creare la view Blade**

```blade
<x-filament-panels::page>
    <x-filament-widgets::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="1"
    />
</x-filament-panels::page>
```

- [ ] **Step 6: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: 7 test passano.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/AppointmentCalendar.php resources/views/filament/pages/appointment-calendar.blade.php app/Filament/Widgets/AppointmentCalendarWidget.php tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "feat: add AppointmentCalendar page with month/week/day views"
```

---

### Task 6: Popup — dettagli appuntamento e cambio stato

**Files:**
- Modify: `app/Filament/Widgets/AppointmentCalendarWidget.php`
- Modify: `tests/Feature/Filament/AppointmentCalendarTest.php`

- [ ] **Step 1: Aggiungere test per il cambio stato**

Aggiungere in coda a `tests/Feature/Filament/AppointmentCalendarTest.php`:

```php
it('il cambio stato aggiorna il record nel database', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $staff = User::factory()->create()->assignRole('staff');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'status'   => 'pending',
        'scheduled_date' => now()->addDays(1),
    ]);

    $this->actingAs($admin);

    Livewire::test(AppointmentCalendarWidget::class)
        ->callAction('changeStatus', data: [
            'status' => 'confirmed',
        ], arguments: [
            'appointmentId' => $appointment->id,
        ])
        ->assertHasNoActionErrors();

    expect($appointment->fresh()->status)->toBe('confirmed');
});
```

- [ ] **Step 2: Eseguire il test — deve fallire**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php --filter "cambio stato"
```

Expected: FAIL — action `changeStatus` non trovata.

- [ ] **Step 3: Aggiungere onEventClick e changeStatusAction al widget**

Aggiungere al corpo di `AppointmentCalendarWidget`:

```php
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;

// Aggiungere alla classe:

public function onEventClick(array $event): void
{
    $this->mountAction('changeStatus', arguments: ['appointmentId' => $event['id']]);
}

public function changeStatusAction(): Action
{
    return Action::make('changeStatus')
        ->label('Dettagli prenotazione')
        ->mountUsing(function (Form $form, array $arguments) {
            $appointment = Appointment::with(['user', 'staff'])->find($arguments['appointmentId']);
            $form->fill([
                'appointment_id'  => $appointment->id,
                'customer_name'   => $appointment->user->name,
                'staff_name'      => $appointment->staff->name,
                'scheduled_date'  => $appointment->scheduled_date->format('d/m/Y H:i'),
                'services'        => $appointment->services_label,
                'status'          => $appointment->status,
            ]);
        })
        ->form([
            Hidden::make('appointment_id'),
            \Filament\Forms\Components\TextInput::make('customer_name')
                ->label('Cliente')
                ->disabled(),
            \Filament\Forms\Components\TextInput::make('staff_name')
                ->label('Staff')
                ->disabled(),
            \Filament\Forms\Components\TextInput::make('scheduled_date')
                ->label('Data e ora')
                ->disabled(),
            \Filament\Forms\Components\TextInput::make('services')
                ->label('Servizi')
                ->disabled(),
            Select::make('status')
                ->label('Stato')
                ->options([
                    'pending'   => 'In attesa',
                    'confirmed' => 'Confermato',
                    'completed' => 'Completato',
                    'cancelled' => 'Annullato',
                ])
                ->required(),
        ])
        ->action(function (array $data): void {
            Appointment::find($data['appointment_id'])->update(['status' => $data['status']]);
        })
        ->modalFooterActions(fn (Action $action) => [
            $action->getModalSubmitAction()->label('Salva stato'),
            $action->getModalCancelAction(),
            \Filament\Actions\Action::make('edit')
                ->label('Modifica completa')
                ->url(fn () => \App\Filament\Resources\AppointmentResource::getUrl('edit', [
                    'record' => $action->getArguments()['appointmentId'],
                ]))
                ->color('gray')
                ->openUrlInNewTab(),
        ]);
}
```

> **Nota:** Se la versione del package sovrascrive `onEventClick` con firma diversa (es. `onEventClick(array $info)`), adattare di conseguenza. Consultare il README del package installato.

- [ ] **Step 4: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: 8 test passano.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/AppointmentCalendarWidget.php tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "feat: add event popup with status change action"
```

---

### Task 7: Popup — registrazione pagamento

**Files:**
- Modify: `app/Filament/Widgets/AppointmentCalendarWidget.php`
- Modify: `tests/Feature/Filament/AppointmentCalendarTest.php`

- [ ] **Step 1: Aggiungere test per registrazione pagamento**

Aggiungere in coda a `tests/Feature/Filament/AppointmentCalendarTest.php`:

```php
it('registra un pagamento in contanti dal popup calendario', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $staff = User::factory()->create()->assignRole('staff');

    $appointment = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'final_price'    => '50.00',
        'scheduled_date' => now()->addDays(1),
    ]);

    $this->actingAs($admin);

    Livewire::test(AppointmentCalendarWidget::class)
        ->callAction('registerPayment', data: [
            'method' => 'cash',
            'amount' => '50.00',
        ], arguments: [
            'appointmentId' => $appointment->id,
        ])
        ->assertHasNoActionErrors();

    $payment = $appointment->fresh()->payment;

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('completed')
        ->and($payment->payment_method)->toBe('cash');
});
```

- [ ] **Step 2: Eseguire il test — deve fallire**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php --filter "pagamento"
```

Expected: FAIL — action `registerPayment` non trovata.

- [ ] **Step 3: Aggiungere registerPaymentAction al widget**

Aggiungere al corpo di `AppointmentCalendarWidget`:

```php
use App\Exceptions\BookingException;
use App\Services\PaymentService;
use Filament\Forms\Components\TextInput;

// Aggiungere alla classe:

public function registerPaymentAction(): Action
{
    return Action::make('registerPayment')
        ->label('Registra pagamento')
        ->icon('heroicon-o-banknotes')
        ->color('success')
        ->mountUsing(function (Form $form, array $arguments) {
            $appointment = Appointment::find($arguments['appointmentId']);
            $form->fill([
                'amount' => $appointment->final_price,
            ]);
        })
        ->form([
            Select::make('method')
                ->label('Metodo di pagamento')
                ->options([
                    'cash' => 'Contanti',
                    'pos'  => 'POS (carta)',
                ])
                ->required(),
            TextInput::make('amount')
                ->label('Importo (€)')
                ->numeric()
                ->minValue(0.01)
                ->required(),
        ])
        ->action(function (array $data, array $arguments): void {
            try {
                app(PaymentService::class)->recordInPersonPayment(
                    $arguments['appointmentId'],
                    $data['method'],
                    (float) $data['amount']
                );
            } catch (BookingException $e) {
                $this->halt();
            }
        });
}
```

Aggiornare `changeStatusAction()` sostituendo il blocco `modalFooterActions` con:

```php
->modalFooterActions(function (Action $action) {
    $appointmentId       = $action->getArguments()['appointmentId'];
    $appointment         = Appointment::with('payment')->find($appointmentId);
    $hasCompletedPayment = $appointment->payment?->status === 'completed';

    $footerActions = [
        $action->getModalSubmitAction()->label('Salva stato'),
    ];

    if (! $hasCompletedPayment) {
        $footerActions[] = \Filament\Actions\Action::make('openRegisterPayment')
            ->label('Registra pagamento')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->action(fn () => $this->mountAction('registerPayment', arguments: ['appointmentId' => $appointmentId]));
    }

    $footerActions[] = \Filament\Actions\Action::make('edit')
        ->label('Modifica completa')
        ->url(\App\Filament\Resources\AppointmentResource::getUrl('edit', ['record' => $appointmentId]))
        ->color('gray')
        ->openUrlInNewTab();

    $footerActions[] = $action->getModalCancelAction();

    return $footerActions;
})
```

> **Nota:** In Filament 4, le nested footer actions potrebbero richiedere una sintassi diversa. Se `mountAction()` dall'interno di un footer action non funziona, alternativa: rendere il pulsante un link a `AppointmentResource::edit` con `?openPayment=1` come query param, oppure usare un secondo `onEventClick`-style button separato.
> Verificare la versione Filament 4 dei docs per `modalFooterActions`.

- [ ] **Step 4: Eseguire tutti i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: tutti i test passano.

- [ ] **Step 5: Eseguire la suite completa per verificare nessuna regressione**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: tutti i test passano.

- [ ] **Step 6: Commit finale**

```bash
git add app/Filament/Widgets/AppointmentCalendarWidget.php tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "feat: add register payment action in calendar event popup"
```
