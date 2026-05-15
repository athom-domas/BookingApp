# Booking Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sostituire il form sidebar della homepage con un wizard accordion a 5 step su una pagina `/prenota` dedicata, usando Alpine.js per l'interattività.

**Architecture:** La landing page (`/`) diventa una vetrina statica; la prenotazione si sposta su `/prenota` con un nuovo metodo `BookingController::create()`. Un nuovo metodo `Booking\AppointmentService::bookDirect()` gestisce prenotazioni multi-servizio e pagamento in salone senza il flusso hold. Il wizard usa Alpine.js per la gestione dello stato accordion sequenziale e un calendario client-side.

**Tech Stack:** Laravel 13, Blade, Alpine.js 3, Tailwind CSS v4, fetch API nativa

---

### Task 1: Installare Alpine.js

**Files:**
- Modify: `package.json`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Installare Alpine.js via npm**

```bash
docker-compose run --rm --no-deps app npm install alpinejs
```

Expected output: `added 1 package`

- [ ] **Step 2: Inizializzare Alpine in app.js**

Sostituire il contenuto di `resources/js/app.js` mantenendo il codice Stripe esistente. Aggiungere Alpine prima del blocco `ready`:

```js
import Alpine from 'alpinejs';
window.Alpine = Alpine;

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
        return;
    }
    callback();
};

ready(() => {
    Alpine.start();

    // ... codice Stripe esistente (stripeForm block) invariato ...
});
```

- [ ] **Step 3: Build per verificare che non ci siano errori**

```bash
docker-compose run --rm --no-deps app npm run build
```

Expected: exit 0, nessun errore TypeScript/build.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/app.js
git commit -m "feat: add Alpine.js"
```

---

### Task 2: Nuovo endpoint API `GET /api/booking/available-dates`

**Files:**
- Create: `app/Http/Requests/Api/GetAvailableDatesRequest.php`
- Modify: `app/Services/Booking/AppointmentService.php` (aggiungere `getAvailableDates`)
- Modify: `app/Http/Controllers/Api/BookingController.php` (aggiungere `getAvailableDates`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/BookingControllerTest.php`

- [ ] **Step 1: Scrivere il test che fallisce**

Aggiungere alla fine di `tests/Feature/Api/BookingControllerTest.php`:

```php
it('returns available dates in a month for given services and staff', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 60]);
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $service->staff()->attach($staff->id);

    // Staff disponibile il lunedì
    $monday = Carbon::parse('next monday');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'start_time'   => '09:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    $month = $monday->format('Y-m');

    $response = $this->getJson("/api/booking/available-dates?serviceIds[]={$service->id}&staffId={$staff->id}&month={$month}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'data'])
        ->assertJsonFragment(['data' => [$monday->toDateString()]]);
});

it('returns empty array when no staff is available in the month', function () {
    $service = Service::factory()->create(['active' => true]);

    $response = $this->getJson("/api/booking/available-dates?serviceIds[]={$service->id}&month=2026-01");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', []);
});

it('validates required params for available-dates endpoint', function () {
    $this->getJson('/api/booking/available-dates')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['serviceIds', 'month']);
});
```

Aggiungere i use mancanti in cima al file (se non già presenti):
```php
use App\Models\AvailabilityRule;
use Carbon\Carbon;
```

- [ ] **Step 2: Eseguire i test per verificare che falliscano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/BookingControllerTest.php --filter "available dates"
```

Expected: 3 FAIL (route not found / 404)

- [ ] **Step 3: Creare `GetAvailableDatesRequest`**

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailableDatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serviceIds'   => 'required|array|min:1',
            'serviceIds.*' => 'integer|exists:services,id',
            'staffId'      => 'nullable|integer|exists:users,id',
            'month'        => 'required|date_format:Y-m',
        ];
    }

    public function getServiceIds(): array
    {
        return array_map('intval', (array) $this->input('serviceIds'));
    }
}
```

- [ ] **Step 4: Aggiungere `getAvailableDates` a `Booking\AppointmentService`**

Aggiungere il metodo pubblico dopo `getAvailableSlots`:

```php
public function getAvailableDates(array $params): array
{
    $month      = $params['month'];
    $serviceIds = $params['serviceIds'];
    $staffId    = $params['staffId'] ?? null;
    $preference = $staffId ? 'specific' : 'any';

    $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
    $end   = $start->copy()->endOfMonth();
    $today = Carbon::today();

    $available = [];

    for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
        if ($day->lt($today)) {
            continue;
        }

        $slots = $this->slotService->getAvailableSlots([
            'date'            => $day->toDateString(),
            'serviceIds'      => $serviceIds,
            'staffId'         => $staffId,
            'staffPreference' => $preference,
        ]);

        if (! empty($slots)) {
            $available[] = $day->toDateString();
        }
    }

    return $available;
}
```

- [ ] **Step 5: Aggiungere `getAvailableDates` ad `Api\BookingController`**

Aggiungere l'import della nuova request e il metodo:

```php
use App\Http\Requests\Api\GetAvailableDatesRequest;
```

Nuovo metodo dopo `getAvailableSlots`:

```php
/**
 * GET /api/booking/available-dates
 *
 * Returns dates in a month that have at least one available slot.
 * Public endpoint — no auth required.
 */
public function getAvailableDates(GetAvailableDatesRequest $request): JsonResponse
{
    try {
        $dates = $this->appointmentService->getAvailableDates([
            'month'      => $request->input('month'),
            'serviceIds' => $request->getServiceIds(),
            'staffId'    => $request->input('staffId'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $dates,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
    }
}
```

- [ ] **Step 6: Registrare la route in `routes/api.php`**

Aggiungere dentro il prefix `booking`:

```php
Route::get('/available-dates', [BookingController::class, 'getAvailableDates']);
```

Il blocco diventa:

```php
Route::prefix('booking')->group(function () {
    Route::get('/slots', [BookingController::class, 'getAvailableSlots']);
    Route::get('/available-dates', [BookingController::class, 'getAvailableDates']);

    Route::middleware('auth:sanctum')->group(function () {
        // ... hold routes invariate ...
    });
});
```

- [ ] **Step 7: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/BookingControllerTest.php --filter "available dates"
```

Expected: 3 PASS

- [ ] **Step 8: Eseguire l'intera suite per verificare nessuna regressione**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: tutti i test passano.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/Api/GetAvailableDatesRequest.php \
        app/Services/Booking/AppointmentService.php \
        app/Http/Controllers/Api/BookingController.php \
        routes/api.php \
        tests/Feature/Api/BookingControllerTest.php
git commit -m "feat: add GET /api/booking/available-dates endpoint"
```

---

### Task 3: Aggiungere `bookDirect()` a `Booking\AppointmentService`

**Files:**
- Modify: `app/Services/Booking/AppointmentService.php`
- Test: `tests/Feature/Services/BookingAppointmentServiceTest.php`

- [ ] **Step 1: Scrivere i test che falliscono**

Aggiungere alla fine di `tests/Feature/Services/BookingAppointmentServiceTest.php`:

```php
describe('bookDirect', function () {
    function makeBookDirectSetup(): array
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $service = Service::factory()->create(['active' => true, 'duration_minutes' => 60, 'price' => 50.00]);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $service->staff()->attach($staff->id);

        $monday = Carbon::parse('next monday')->setTime(10, 0);

        AvailabilityRule::factory()->create([
            'user_id'      => $staff->id,
            'day_of_week'  => 1,
            'start_time'   => '09:00:00',
            'end_time'     => '17:00:00',
            'is_available' => true,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return [$service, $staff, $customer, $monday];
    }

    it('creates a pending appointment when confirmImmediately is false', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'             => $customer->id,
            'serviceIds'         => [$service->id],
            'staffId'            => $staff->id,
            'scheduledDate'      => $monday,
            'confirmImmediately' => false,
        ]);

        expect($appointment->status)->toBe('pending');
        expect($appointment->staff_id)->toBe($staff->id);
        expect($appointment->service_id)->toBe($service->id);
        expect((float) $appointment->final_price)->toBe(50.0);
    });

    it('creates a confirmed appointment when confirmImmediately is true', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'             => $customer->id,
            'serviceIds'         => [$service->id],
            'staffId'            => $staff->id,
            'scheduledDate'      => $monday,
            'confirmImmediately' => true,
        ]);

        expect($appointment->status)->toBe('confirmed');
    });

    it('sums prices for multiple services', function () {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $service1 = Service::factory()->create(['active' => true, 'duration_minutes' => 30, 'price' => 20.00]);
        $service2 = Service::factory()->create(['active' => true, 'duration_minutes' => 20, 'price' => 15.00]);

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $service1->staff()->attach($staff->id);
        $service2->staff()->attach($staff->id);

        $monday = Carbon::parse('next monday')->setTime(10, 0);

        AvailabilityRule::factory()->create([
            'user_id'      => $staff->id,
            'day_of_week'  => 1,
            'start_time'   => '09:00:00',
            'end_time'     => '17:00:00',
            'is_available' => true,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'        => $customer->id,
            'serviceIds'    => [$service1->id, $service2->id],
            'staffId'       => $staff->id,
            'scheduledDate' => $monday,
        ]);

        expect((float) $appointment->final_price)->toBe(35.0);
    });

    it('throws when the slot is not available', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        // Occupy the slot
        \App\Models\Appointment::factory()->create([
            'staff_id'       => $staff->id,
            'service_id'     => $service->id,
            'scheduled_date' => $monday,
            'status'         => 'confirmed',
        ]);

        expect(fn () => app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'        => $customer->id,
            'serviceIds'    => [$service->id],
            'staffId'       => $staff->id,
            'scheduledDate' => $monday,
        ]))->toThrow(\RuntimeException::class, 'Slot non disponibile');
    });

    it('assigns any available operator when staffId is null', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'        => $customer->id,
            'serviceIds'    => [$service->id],
            'staffId'       => null,
            'scheduledDate' => $monday,
        ]);

        expect($appointment->staff_id)->toBe($staff->id);
    });
});
```

Aggiungere gli use mancanti in cima al file se non presenti:
```php
use App\Models\AvailabilityRule;
use Carbon\Carbon;
```

- [ ] **Step 2: Eseguire i test per verificare che falliscano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/BookingAppointmentServiceTest.php --filter "bookDirect"
```

Expected: 5 FAIL (method not found)

- [ ] **Step 3: Implementare `bookDirect` in `Booking\AppointmentService`**

Aggiungere gli import necessari all'inizio del file:

```php
use App\Jobs\SyncGoogleCalendar;
use App\Models\AppointmentReminder;
```

Aggiungere il metodo pubblico dopo `calculateTotalPrice`:

```php
public function bookDirect(array $params): Appointment
{
    $userId             = $params['userId'];
    $serviceIds         = $params['serviceIds'];
    $staffId            = $params['staffId'] ?? null;
    $scheduledDate      = Carbon::parse($params['scheduledDate']);
    $confirmImmediately = $params['confirmImmediately'] ?? false;
    $notes              = $params['notes'] ?? null;
    $staffPreference    = $staffId ? 'specific' : 'any';

    return DB::transaction(function () use ($userId, $serviceIds, $staffId, $scheduledDate, $confirmImmediately, $notes, $staffPreference) {
        $date     = $scheduledDate->copy()->startOfDay();
        $slotTime = $scheduledDate->format('H:i');

        $slots = $this->slotService->getAvailableSlots([
            'date'            => $date,
            'serviceIds'      => $serviceIds,
            'staffId'         => $staffId,
            'staffPreference' => $staffPreference,
        ]);

        $matchingSlot = collect($slots)->first(fn ($s) => $s['start'] === $slotTime);

        if (! $matchingSlot) {
            throw new \RuntimeException('Slot non disponibile.');
        }

        if ($staffPreference === 'any') {
            $duration = $this->slotService->calculateTotalDuration($serviceIds);
            $staffId  = $this->pickBestOperator($date, $serviceIds, $scheduledDate, $duration);

            if (! $staffId) {
                throw new \RuntimeException('Nessun operatore disponibile.');
            }
        }

        $appointment = Appointment::create([
            'user_id'        => $userId,
            'service_id'     => $serviceIds[0],
            'staff_id'       => $staffId,
            'scheduled_date' => $scheduledDate,
            'status'         => $confirmImmediately ? 'confirmed' : 'pending',
            'final_price'    => $this->calculateTotalPrice($serviceIds),
            'notes'          => $notes,
        ]);

        AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'type'           => 'email',
            'scheduled_for'  => $scheduledDate->copy()->subDay(),
            'status'         => 'pending',
        ]);

        SyncGoogleCalendar::dispatch($appointment, 'create');

        if ($confirmImmediately) {
            AppointmentConfirmed::dispatch($appointment);
        }

        return $appointment;
    });
}
```

- [ ] **Step 4: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/BookingAppointmentServiceTest.php --filter "bookDirect"
```

Expected: 5 PASS

- [ ] **Step 5: Eseguire l'intera suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: tutti i test passano.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Booking/AppointmentService.php \
        tests/Feature/Services/BookingAppointmentServiceTest.php
git commit -m "feat: add bookDirect to Booking\AppointmentService"
```

---

### Task 4: Aggiornare `StoreBookingRequest` e `BookingController@store`

**Files:**
- Modify: `app/Http/Requests/Portal/StoreBookingRequest.php`
- Modify: `app/Http/Controllers/Portal/BookingController.php`
- Test: `tests/Feature/Portal/BookingPortalTest.php`

- [ ] **Step 1: Scrivere i test che falliscono**

Aggiungere alla fine di `tests/Feature/Portal/BookingPortalTest.php`:

```php
it('creates a confirmed appointment and redirects to show when payment is in_salon', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();

    $response = $this->actingAs($customer)->post('/portal/bookings', [
        'service_ids'    => [$service->id],
        'staff_id'       => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
        'payment_method' => 'in_salon',
    ]);

    $appointment = Appointment::where('user_id', $customer->id)->first();

    expect($appointment)->not->toBeNull();
    expect($appointment->status)->toBe('confirmed');
    $response->assertRedirect(route('portal.appointments.show', $appointment));
});

it('creates a pending appointment and goes to payment when payment is online', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();

    $this->mock(PaymentService::class)
        ->shouldReceive('initiateStripePayment')
        ->once()
        ->andReturnUsing(fn (int $appointmentId) => Payment::factory()->create([
            'appointment_id'        => $appointmentId,
            'user_id'               => $customer->id,
            'amount'                => 75.00,
            'status'                => 'pending',
            'stripe_transaction_id' => 'pi_wizard_123',
            'stripe_response'       => ['client_secret' => 'pi_wizard_123_secret'],
        ]));

    $response = $this->actingAs($customer)->post('/portal/bookings', [
        'service_ids'    => [$service->id],
        'staff_id'       => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
        'payment_method' => 'online',
    ]);

    $appointment = Appointment::where('user_id', $customer->id)->first();

    expect($appointment)->not->toBeNull();
    expect($appointment->status)->toBe('pending');
    $response->assertRedirect(route('portal.appointments.payment', $appointment));
});

it('rejects store request when payment_method is missing', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();

    $this->actingAs($customer)->post('/portal/bookings', [
        'service_ids'    => [$service->id],
        'staff_id'       => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
    ])->assertSessionHasErrors('payment_method');
});
```

- [ ] **Step 2: Eseguire i test per verificare che falliscano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/BookingPortalTest.php --filter "payment"
```

Expected: 3 FAIL

- [ ] **Step 3: Aggiornare `StoreBookingRequest`**

```php
<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'service_ids'    => ['required', 'array', 'min:1'],
            'service_ids.*'  => ['integer', 'exists:services,id'],
            'staff_id'       => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date', 'after:now'],
            'payment_method' => ['required', 'in:online,in_salon'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 4: Aggiornare `BookingController` (portal)**

Cambiare gli import e riscrivere il controller:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreBookingRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(): View
    {
        $services = Service::active()->orderBy('name')->get();

        return view('welcome', ['services' => $services]);
    }

    public function create(): View
    {
        $services = Service::active()
            ->with(['staff' => fn ($q) => $q
                ->whereHas('roles', fn ($r) => $r->where('name', 'staff')->where('guard_name', 'web'))
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $staff = User::whereHas('roles', fn ($q) => $q->where('name', 'staff')->where('guard_name', 'web'))
            ->whereHas('services', fn ($q) => $q->active())
            ->with(['services' => fn ($q) => $q->active()->select('services.id', 'services.name')])
            ->orderBy('name')
            ->get();

        return view('portal.booking', [
            'services' => $services,
            'staff'    => $staff,
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $appointment = $this->appointmentService->bookDirect([
            'userId'             => $request->user()->id,
            'serviceIds'         => $request->input('service_ids'),
            'staffId'            => $request->filled('staff_id') ? $request->integer('staff_id') : null,
            'scheduledDate'      => Carbon::parse($request->string('scheduled_date')),
            'confirmImmediately' => $request->input('payment_method') === 'in_salon',
            'notes'              => $request->input('notes'),
        ]);

        if ($request->input('payment_method') === 'in_salon') {
            return redirect()
                ->route('portal.appointments.show', $appointment)
                ->with('status', 'Prenotazione confermata. Ci vediamo in salone!');
        }

        $amountCents = (int) round((float) $appointment->final_price * 100);
        $this->paymentService->initiateStripePayment($appointment->id, $amountCents);

        return redirect()
            ->route('portal.appointments.payment', $appointment)
            ->with('status', 'Prenotazione creata. Completa il pagamento per confermarla.');
    }
}
```

- [ ] **Step 5: Aggiornare i test esistenti in `BookingPortalTest.php`**

Il test `'creates a pending booking and payment intent...'` usa il vecchio formato. Aggiornarlo:

```php
it('creates a pending booking and payment intent for an authenticated customer', function () {
    $customer = makePortalCustomer();
    [$service, $staff, $date] = makePortalBookableSetup();

    $this->mock(PaymentService::class)
        ->shouldReceive('initiateStripePayment')
        ->once()
        ->with(Mockery::type('int'), 7500)
        ->andReturnUsing(fn (int $appointmentId) => Payment::factory()->create([
            'appointment_id'        => $appointmentId,
            'user_id'               => $customer->id,
            'amount'                => 75.00,
            'status'                => 'pending',
            'stripe_transaction_id' => 'pi_portal_123',
            'stripe_response'       => ['client_secret' => 'pi_portal_123_secret_test'],
        ]));

    $response = $this->actingAs($customer)->post('/portal/bookings', [
        'service_ids'    => [$service->id],
        'staff_id'       => $staff->id,
        'scheduled_date' => $date->toDateTimeString(),
        'payment_method' => 'online',
        'notes'          => 'Prima visita',
    ]);

    $appointment = Appointment::where('user_id', $customer->id)->first();

    $response->assertRedirect(route('portal.appointments.payment', $appointment));
    expect($appointment)->not->toBeNull();
    expect($appointment->status)->toBe('pending');
    expect($appointment->notes)->toBe('Prima visita');
});
```

Aggiornare anche gli altri test che usano il vecchio formato (`'rejects inactive services'`, `'rejects staff not assigned...'`, `'rejects users without staff role...'`, `'rejects bookings when slot is taken'`, `'rejects past booking dates'`) aggiungendo `'service_ids' => [$service->id]` al posto di `'service_id' => $service->id` e aggiungendo `'payment_method' => 'online'`:

```php
// Ogni test esistente che fa POST a /portal/bookings:
// Sostituire 'service_id' => $service->id, con 'service_ids' => [$service->id],
// Aggiungere 'payment_method' => 'online',
```

- [ ] **Step 6: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/BookingPortalTest.php
```

Expected: tutti i test passano.

- [ ] **Step 7: Eseguire l'intera suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: tutti i test passano.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Portal/StoreBookingRequest.php \
        app/Http/Controllers/Portal/BookingController.php \
        tests/Feature/Portal/BookingPortalTest.php
git commit -m "feat: update portal booking for multi-service and in_salon payment"
```

---

### Task 5: Aggiungere route `/prenota` e aggiornare `welcome.blade.php`

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/welcome.blade.php`
- Test: `tests/Feature/Portal/BookingPortalTest.php`

- [ ] **Step 1: Scrivere il test aggiornato**

Aggiornare in `BookingPortalTest.php` il test esistente `'shows the public booking page...'` e aggiungere test per la landing:

```php
// Cambiare il test esistente:
it('shows the booking wizard page with active services', function () {
    Service::factory()->create(['name' => 'Taglio', 'active' => true]);
    Service::factory()->create(['name' => 'Servizio nascosto', 'active' => false]);

    $response = $this->get('/prenota');

    $response->assertOk()
        ->assertSee('Taglio')
        ->assertDontSee('Servizio nascosto');
});

// Aggiungere:
it('shows the landing page at /', function () {
    Service::factory()->create(['name' => 'Taglio', 'active' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Taglio')
        ->assertSee('Prenota ora');
});
```

- [ ] **Step 2: Eseguire i test per verificare che falliscano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/BookingPortalTest.php --filter "landing|booking wizard"
```

Expected: FAIL (route /prenota not found)

- [ ] **Step 3: Aggiungere la route `/prenota` in `routes/web.php`**

Aggiungere dopo la route `booking.index`:

```php
Route::get('/prenota', [BookingController::class, 'create'])->name('booking.create');
```

- [ ] **Step 4: Aggiornare `welcome.blade.php` come landing page**

Sostituire il contenuto di `resources/views/welcome.blade.php` con una landing page che mostra i servizi in vetrina e un CTA verso `/prenota`:

```blade
@extends('layouts.app')

@section('title', 'Benvenuto')

@section('content')
    <section class="space-y-12">
        <div class="text-center space-y-4 py-12">
            <p class="text-sm font-semibold uppercase tracking-widest text-blue-700">Booking App</p>
            <h1 class="text-4xl font-bold text-gray-950 sm:text-5xl">
                Prenota il tuo appuntamento
            </h1>
            <p class="mx-auto max-w-xl text-base leading-7 text-gray-600">
                Scegli tra i nostri servizi, seleziona il professionista e trova l'orario che fa per te.
            </p>
            <a href="{{ route('booking.create') }}"
               class="inline-block rounded-md bg-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Prenota ora
            </a>
        </div>

        <div class="space-y-4">
            <h2 class="text-xl font-semibold text-gray-950">I nostri servizi</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($services as $service)
                    <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="text-base font-semibold text-gray-950">{{ $service->name }}</h3>
                            <span class="shrink-0 rounded-md bg-blue-50 px-2.5 py-1 text-sm font-semibold text-blue-700">
                                {{ number_format((float) $service->price, 2, ',', '.') }} €
                            </span>
                        </div>
                        @if ($service->description)
                            <p class="mt-2 text-sm leading-6 text-gray-600">{{ $service->description }}</p>
                        @endif
                        <p class="mt-3 text-sm text-gray-500">Durata: {{ $service->duration_minutes }} min</p>
                    </article>
                @empty
                    <p class="col-span-full text-sm text-gray-500">Nessun servizio attivo al momento.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 5: Eseguire i test**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/BookingPortalTest.php
```

Expected: tutti i test passano.

- [ ] **Step 6: Commit**

```bash
git add routes/web.php resources/views/welcome.blade.php tests/Feature/Portal/BookingPortalTest.php
git commit -m "feat: add /prenota route and update welcome to landing page"
```

---

### Task 6: Creare la view `portal/booking/index.blade.php`

**Files:**
- Create: `resources/views/portal/booking/index.blade.php`

Questa è la view principale del wizard. Contiene il tag `x-data` Alpine che referenzia il componente `bookingWizard(...)`, i dati JSON serializzati in PHP, e i 5 accordion. I dati Alpine vengono passati dal PHP tramite un blocco `<script>` JSON + `x-data`.

- [ ] **Step 1: Creare la directory**

```bash
mkdir -p resources/views/portal/booking
```

- [ ] **Step 2: Creare `resources/views/portal/booking/index.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Nuova prenotazione')

@section('content')
    @php
        $servicesJson = $services->map(fn ($s) => [
            'id'          => $s->id,
            'name'        => $s->name,
            'description' => $s->description ?? '',
            'duration'    => $s->duration_minutes,
            'price'       => (float) $s->price,
            'staff_ids'   => $s->staff->pluck('id')->values()->all(),
        ])->values()->all();

        $staffJson = $staff->map(fn ($m) => [
            'id'          => $m->id,
            'name'        => $m->name,
            'service_ids' => $m->services->pluck('id')->values()->all(),
        ])->values()->all();
    @endphp

    <div class="mx-auto max-w-2xl space-y-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-gray-950">Nuova prenotazione</h1>
            <p class="text-sm text-gray-600">Completa i passi in ordine per prenotare il tuo appuntamento.</p>
        </div>

        <div
            x-data="bookingWizard({{ json_encode($servicesJson) }}, {{ json_encode($staffJson) }})"
            class="space-y-3"
        >
            {{-- CSRF + hidden inputs per il form submit --}}
            <form method="POST" action="{{ route('portal.bookings.store') }}" x-ref="bookingForm">
                @csrf
                <template x-for="id in selectedServiceIds" :key="id">
                    <input type="hidden" name="service_ids[]" :value="id">
                </template>
                <input type="hidden" name="staff_id" :value="staffId ?? ''">
                <input type="hidden" name="scheduled_date" :value="scheduledDateTime">
                <input type="hidden" name="payment_method" :value="paymentMethod ?? ''">
                <input type="hidden" name="notes" :value="notes">
            </form>

            {{-- Step 1: Servizi --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(1) && !isOpen(1) ? goTo(1) : null"
                    :class="isCompleted(1) && !isOpen(1) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(1) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(1)">1</span>
                            <svg x-show="isCompleted(1)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Scegli i servizi</p>
                            <p x-show="isCompleted(1) && !isOpen(1)" class="text-xs text-gray-500" x-text="servicesSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(1)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <template x-for="service in allServices" :key="service.id">
                            <button
                                type="button"
                                @click="toggleService(service.id)"
                                class="rounded-lg border p-4 text-left transition-colors"
                                :class="isSelectedService(service.id)
                                    ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-900" x-text="service.name"></span>
                                    <span class="shrink-0 rounded bg-blue-50 px-1.5 py-0.5 text-xs font-semibold text-blue-700"
                                          x-text="'€ ' + service.price.toFixed(2).replace('.', ',')"></span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500" x-text="service.description"></p>
                                <p class="mt-2 text-xs text-gray-400" x-text="service.duration + ' min'"></p>
                            </button>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <p x-show="selectedServiceIds.length > 0" class="text-xs text-gray-500"
                           x-text="'Totale: ' + totalDuration + ' min · € ' + totalPrice.toFixed(2).replace('.', ',')"></p>
                        <button
                            type="button"
                            @click="completeStep(1)"
                            :disabled="selectedServiceIds.length === 0"
                            class="ml-auto rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 2: Operatore --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(1) && !isOpen(2) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(2) && !isOpen(2) ? goTo(2) : null"
                    :class="isCompleted(2) && !isOpen(2) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(2) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(2)">2</span>
                            <svg x-show="isCompleted(2)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Scegli l'operatore</p>
                            <p x-show="isCompleted(2) && !isOpen(2)" class="text-xs text-gray-500" x-text="staffSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(2)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    <div class="space-y-2">
                        {{-- Qualsiasi operatore --}}
                        <button
                            type="button"
                            @click="staffId = null"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="staffId === null
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                        >
                            <p class="text-sm font-semibold text-gray-900">Qualsiasi operatore disponibile</p>
                            <p class="mt-0.5 text-xs text-gray-500">Il sistema assegnerà il miglior operatore libero</p>
                        </button>

                        {{-- Staff filtrato per servizi selezionati --}}
                        <template x-for="member in filteredStaff" :key="member.id">
                            <button
                                type="button"
                                @click="staffId = member.id"
                                class="w-full rounded-lg border p-4 text-left transition-colors"
                                :class="staffId === member.id
                                    ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                            >
                                <p class="text-sm font-semibold text-gray-900" x-text="member.name"></p>
                            </button>
                        </template>

                        <p x-show="filteredStaff.length === 0" class="text-sm text-gray-500">
                            Nessun operatore disponibile per tutti i servizi selezionati.
                        </p>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(2)"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 3: Data e ora --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(2) && !isOpen(3) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(3) && !isOpen(3) ? goTo(3) : null"
                    :class="isCompleted(3) && !isOpen(3) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(3) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(3)">3</span>
                            <svg x-show="isCompleted(3)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Scegli data e ora</p>
                            <p x-show="isCompleted(3) && !isOpen(3)" class="text-xs text-gray-500" x-text="dateSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(3)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    {{-- Navigazione mese --}}
                    <div class="mb-4 flex items-center justify-between">
                        <button type="button" @click="prevMonth()" class="rounded p-1 hover:bg-gray-100">
                            <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <p class="text-sm font-semibold text-gray-900" x-text="monthLabel"></p>
                        <button type="button" @click="nextMonth()" class="rounded p-1 hover:bg-gray-100">
                            <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    {{-- Griglia calendario --}}
                    <div class="grid grid-cols-7 gap-1 text-center">
                        <template x-for="d in ['Lu','Ma','Me','Gi','Ve','Sa','Do']">
                            <div class="py-1 text-xs font-medium text-gray-400" x-text="d"></div>
                        </template>
                        <template x-for="(cell, i) in calendarGrid" :key="i">
                            <div>
                                <template x-if="cell === null">
                                    <div></div>
                                </template>
                                <template x-if="cell !== null">
                                    <button
                                        type="button"
                                        @click="selectDate(cell)"
                                        :disabled="!isAvailableDate(cell)"
                                        class="w-full rounded-md py-1.5 text-sm transition-colors"
                                        :class="{
                                            'bg-blue-700 text-white font-semibold': date === cell,
                                            'hover:bg-blue-50 text-gray-900': isAvailableDate(cell) && date !== cell,
                                            'text-gray-300 cursor-not-allowed': !isAvailableDate(cell),
                                        }"
                                        x-text="cell.split('-')[2]"
                                    ></button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div x-show="loadingDates" class="mt-3 text-center text-xs text-gray-500">Caricamento disponibilità...</div>

                    {{-- Slot orari --}}
                    <div x-show="date !== null" class="mt-4">
                        <p class="mb-2 text-xs font-medium text-gray-700">Orari disponibili</p>
                        <div x-show="loadingSlots" class="text-xs text-gray-500">Caricamento orari...</div>
                        <div x-show="!loadingSlots && availableSlots.length === 0 && date !== null" class="text-xs text-gray-500">
                            Nessun orario disponibile per questa data.
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="s in availableSlots" :key="s.start">
                                <button
                                    type="button"
                                    @click="slot = s.start"
                                    class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                    :class="slot === s.start
                                        ? 'border-blue-600 bg-blue-700 text-white'
                                        : 'border-gray-300 text-gray-700 hover:border-blue-400 hover:text-blue-700'"
                                    x-text="s.start"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(3)"
                            :disabled="!date || !slot"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Metodo di pagamento --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(3) && !isOpen(4) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(4) && !isOpen(4) ? goTo(4) : null"
                    :class="isCompleted(4) && !isOpen(4) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(4) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(4)">4</span>
                            <svg x-show="isCompleted(4)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Metodo di pagamento</p>
                            <p x-show="isCompleted(4) && !isOpen(4)" class="text-xs text-gray-500" x-text="paymentSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(4)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    <div class="space-y-3">
                        <button
                            type="button"
                            @click="paymentMethod = 'online'"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="paymentMethod === 'online'
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                        >
                            <p class="text-sm font-semibold text-gray-900">Paga ora</p>
                            <p class="mt-0.5 text-xs text-gray-500">Pagamento online con carta — la prenotazione viene confermata solo al completamento del pagamento</p>
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'in_salon'"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="paymentMethod === 'in_salon'
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                        >
                            <p class="text-sm font-semibold text-gray-900">Paga in salone</p>
                            <p class="mt-0.5 text-xs text-gray-500">Paghi direttamente al momento del servizio — la prenotazione è confermata subito</p>
                        </button>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(4)"
                            :disabled="paymentMethod === null"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 5: Riepilogo e conferma --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(4) && !isOpen(5) ? 'opacity-50' : ''">
                <div class="flex items-center gap-3 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">5</span>
                    <p class="text-sm font-semibold text-gray-900">Riepilogo e conferma</p>
                </div>
                <div x-show="isOpen(5)" class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4">
                    @auth
                        {{-- Riepilogo --}}
                        <dl class="space-y-2 rounded-lg bg-gray-50 p-4 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Servizi</dt>
                                <dd class="font-medium text-gray-900" x-text="selectedServiceIds.map(id => serviceById(id)?.name).join(', ')"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Operatore</dt>
                                <dd class="font-medium text-gray-900" x-text="staffId ? (allStaff.find(s => s.id === staffId)?.name ?? '—') : 'Qualsiasi operatore'"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Data e ora</dt>
                                <dd class="font-medium text-gray-900" x-text="dateSummary"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Durata</dt>
                                <dd class="font-medium text-gray-900" x-text="totalDuration + ' min'"></dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2">
                                <dt class="font-semibold text-gray-900">Totale</dt>
                                <dd class="font-bold text-gray-900" x-text="'€ ' + totalPrice.toFixed(2).replace('.', ',')"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Pagamento</dt>
                                <dd class="font-medium text-gray-900" x-text="paymentSummary"></dd>
                            </div>
                        </dl>

                        {{-- Note --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-900">Note (opzionale)</label>
                            <textarea
                                x-model="notes"
                                rows="3"
                                maxlength="1000"
                                class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            ></textarea>
                        </div>

                        {{-- Submit --}}
                        <button
                            type="button"
                            @click="$refs.bookingForm.submit()"
                            class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800"
                            x-text="paymentMethod === 'online' ? 'Prenota e vai al pagamento' : 'Conferma prenotazione'"
                        ></button>
                    @else
                        <p class="text-sm text-gray-600">Accedi o crea un account per completare la prenotazione.</p>
                        <div class="flex gap-3">
                            <a href="{{ route('login') }}" class="flex-1 rounded-md border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-900 hover:bg-gray-50">Accedi</a>
                            <a href="{{ route('register') }}" class="flex-1 rounded-md bg-blue-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-800">Crea account</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </div>
@endsection
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/portal/booking/index.blade.php
git commit -m "feat: add portal/booking accordion wizard view"
```

---

### Task 7: Creare `resources/js/booking-wizard.js` (Alpine component)

**Files:**
- Create: `resources/js/booking-wizard.js`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Creare `resources/js/booking-wizard.js`**

```js
export function bookingWizard(allServices, allStaff) {
    return {
        // navigation
        step: 1,
        completed: [],

        // data
        allServices,
        allStaff,

        // step 1
        selectedServiceIds: [],

        // step 2
        staffId: null,

        // step 3
        date: null,
        slot: null,
        calendarMonth: '',
        availableDates: [],
        loadingDates: false,
        availableSlots: [],
        loadingSlots: false,

        // step 4
        paymentMethod: null,

        // step 5
        notes: '',

        // ── init ──────────────────────────────────────────────────────────
        init() {
            const now = new Date();
            this.calendarMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
            this.$watch('step', (v) => {
                if (v === 3) this.loadAvailableDates();
            });
        },

        // ── navigation ────────────────────────────────────────────────────
        isOpen(n) {
            return this.step === n;
        },

        isCompleted(n) {
            return this.completed.includes(n);
        },

        completeStep(n) {
            if (! this.completed.includes(n)) {
                this.completed.push(n);
            }
            this.step = n + 1;
        },

        goTo(n) {
            // Reset everything after step n
            if (n <= 1) {
                this.staffId = null;
            }
            if (n <= 2) {
                this.date = null;
                this.slot = null;
                this.availableSlots = [];
                this.availableDates = [];
            }
            if (n <= 3) {
                this.paymentMethod = null;
            }
            this.completed = this.completed.filter(s => s < n);
            this.step = n;
        },

        // ── computed ──────────────────────────────────────────────────────
        get totalDuration() {
            return this.selectedServiceIds.reduce((sum, id) => {
                const s = this.allServices.find(s => s.id === id);
                return sum + (s ? s.duration : 0);
            }, 0);
        },

        get totalPrice() {
            return this.selectedServiceIds.reduce((sum, id) => {
                const s = this.allServices.find(s => s.id === id);
                return sum + (s ? s.price : 0);
            }, 0);
        },

        get filteredStaff() {
            if (this.selectedServiceIds.length === 0) return this.allStaff;
            return this.allStaff.filter(member =>
                this.selectedServiceIds.every(sid => member.service_ids.includes(sid))
            );
        },

        get scheduledDateTime() {
            if (! this.date || ! this.slot) return '';
            return `${this.date} ${this.slot}:00`;
        },

        get servicesSummary() {
            if (this.selectedServiceIds.length === 0) return '';
            const names = this.selectedServiceIds.map(id => this.serviceById(id)?.name).filter(Boolean);
            return `${names.join(', ')} · ${this.totalDuration} min · € ${this.totalPrice.toFixed(2).replace('.', ',')}`;
        },

        get staffSummary() {
            if (this.staffId === null) return 'Qualsiasi operatore';
            return this.allStaff.find(s => s.id === this.staffId)?.name ?? '';
        },

        get dateSummary() {
            if (! this.date || ! this.slot) return '';
            const [y, m, d] = this.date.split('-');
            return `${d}/${m}/${y} alle ${this.slot}`;
        },

        get paymentSummary() {
            if (this.paymentMethod === 'online') return 'Paga ora (online)';
            if (this.paymentMethod === 'in_salon') return 'Paga in salone';
            return '';
        },

        // ── service selection ─────────────────────────────────────────────
        serviceById(id) {
            return this.allServices.find(s => s.id === id) ?? null;
        },

        isSelectedService(id) {
            return this.selectedServiceIds.includes(id);
        },

        toggleService(id) {
            const idx = this.selectedServiceIds.indexOf(id);
            if (idx === -1) {
                this.selectedServiceIds.push(id);
            } else {
                this.selectedServiceIds.splice(idx, 1);
            }
        },

        // ── calendar ──────────────────────────────────────────────────────
        get monthLabel() {
            const [year, month] = this.calendarMonth.split('-').map(Number);
            return new Date(year, month - 1, 1).toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
        },

        get calendarGrid() {
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const firstDay = new Date(year, month - 1, 1);
            const lastDay  = new Date(year, month, 0);
            const startPad = (firstDay.getDay() + 6) % 7; // Monday = 0

            const cells = [];
            for (let i = 0; i < startPad; i++) cells.push(null);
            for (let d = 1; d <= lastDay.getDate(); d++) {
                cells.push(`${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`);
            }
            return cells;
        },

        isAvailableDate(dateStr) {
            return this.availableDates.includes(dateStr);
        },

        prevMonth() {
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const d = new Date(year, month - 2, 1);
            const newMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            const today = new Date().toISOString().slice(0, 7);
            if (newMonth < today) return;
            this.calendarMonth = newMonth;
            this.loadAvailableDates();
        },

        nextMonth() {
            const [year, month] = this.calendarMonth.split('-').map(Number);
            const d = new Date(year, month, 1);
            this.calendarMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
            this.loadAvailableDates();
        },

        async loadAvailableDates() {
            if (this.selectedServiceIds.length === 0) return;
            this.loadingDates = true;
            this.availableDates = [];

            const params = new URLSearchParams();
            this.selectedServiceIds.forEach(id => params.append('serviceIds[]', id));
            if (this.staffId) params.append('staffId', this.staffId);
            params.append('month', this.calendarMonth);

            try {
                const res  = await fetch(`/api/booking/available-dates?${params}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.availableDates = data.data ?? [];
            } catch (_) {
                this.availableDates = [];
            } finally {
                this.loadingDates = false;
            }
        },

        async selectDate(dateStr) {
            this.date = dateStr;
            this.slot = null;
            this.availableSlots = [];
            await this.loadAvailableSlots();
        },

        async loadAvailableSlots() {
            if (! this.date || this.selectedServiceIds.length === 0) return;
            this.loadingSlots = true;

            const params = new URLSearchParams();
            this.selectedServiceIds.forEach(id => params.append('serviceIds[]', id));
            if (this.staffId) params.append('staffId', this.staffId);
            params.append('staffPreference', this.staffId ? 'specific' : 'any');
            params.append('date', this.date);

            try {
                const res  = await fetch(`/api/booking/slots?${params}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.availableSlots = (data.data ?? []).map(s => ({ start: s.start, end: s.end }));
            } catch (_) {
                this.availableSlots = [];
            } finally {
                this.loadingSlots = false;
            }
        },
    };
}
```

- [ ] **Step 2: Registrare il componente in `app.js`**

Aggiungere l'import in cima ad `app.js`, dopo l'import di Alpine:

```js
import Alpine from 'alpinejs';
import { bookingWizard } from './booking-wizard.js';

window.Alpine = Alpine;
window.bookingWizard = bookingWizard;
```

- [ ] **Step 3: Build**

```bash
docker-compose run --rm --no-deps app npm run build
```

Expected: exit 0.

- [ ] **Step 4: Commit**

```bash
git add resources/js/booking-wizard.js resources/js/app.js
git commit -m "feat: add Alpine.js booking wizard component"
```

---

### Task 8: Build finale e verifica

**Files:** nessun file aggiuntivo

- [ ] **Step 1: Eseguire l'intera suite di test**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: tutti i test passano.

- [ ] **Step 2: Build assets**

```bash
docker-compose run --rm --no-deps app npm run build
```

Expected: exit 0.

- [ ] **Step 3: Avviare i servizi e verificare manualmente**

```bash
docker-compose up -d
```

Aprire `http://localhost` nel browser e verificare:
- `/` mostra la landing con servizi e bottone "Prenota ora"
- `/prenota` mostra il wizard accordion
- Step 1 aperto di default, step 2-5 bloccati
- Selezione servizi filtra correttamente lo staff nello step 2
- Il calendario carica le date disponibili via API
- Le date senza disponibilità sono disabilitate (grigie)
- La selezione di una data mostra i badge degli orari
- Step 4 non ha selezione di default
- Submit con "paga in salone" → redirect a show con flash di conferma
- Submit con "paga ora" → redirect a pagamento Stripe

- [ ] **Step 4: Commit finale se ci sono modifiche**

```bash
git add -A
git commit -m "chore: final build assets"
```
