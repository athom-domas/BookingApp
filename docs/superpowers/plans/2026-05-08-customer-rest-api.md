# Customer REST API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the REST API layer that lets web and mobile clients list services, book appointments, manage them, and pay via Stripe — all authenticated with Laravel Sanctum.

**Architecture:** Three controllers (`ServiceController`, `AppointmentController`, `PaymentController`) under `App\Http\Controllers\Api\`, each delegating business logic to the existing services (`AppointmentService`, `PaymentService`). Routes are protected by `auth:sanctum` where auth is required. Form Requests validate all incoming data. `PaymentService` gains a `confirmPayment()` method used by the payment endpoint.

**Tech Stack:** Laravel 13, Laravel Sanctum (token auth), Stripe PHP SDK v20, Pest 4, Mockery

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `routes/api.php` | Create (via artisan, then replace) | All API route definitions |
| `bootstrap/app.php` | Modify | Add `api:` routing entry |
| `app/Models/User.php` | Modify | Add `HasApiTokens` trait |
| `config/services.php` | Modify | Add `stripe.secret` key |
| `app/Http/Controllers/Api/ServiceController.php` | Create | `GET /services`, `GET /services/{service}/slots` |
| `app/Http/Controllers/Api/AppointmentController.php` | Create | All 5 appointment endpoints |
| `app/Http/Controllers/Api/PaymentController.php` | Create | `POST /appointments/{appointment}/payment` |
| `app/Http/Requests/Api/SlotsRequest.php` | Create | Validates `date` + `staff_id` query params |
| `app/Http/Requests/Api/BookAppointmentRequest.php` | Create | Validates `service_id`, `staff_id`, `scheduled_date`, `notes` |
| `app/Http/Requests/Api/UpdateAppointmentRequest.php` | Create | Validates optional `scheduled_date`, `notes` |
| `app/Services/PaymentService.php` | Modify | Add `confirmPayment(int $appointmentId): Payment` |
| `tests/Feature/Api/ServiceControllerTest.php` | Create | Tests for service endpoints |
| `tests/Feature/Api/AppointmentControllerTest.php` | Create | Tests for all appointment endpoints |
| `tests/Feature/Api/PaymentControllerTest.php` | Create | Tests for payment confirm endpoint |

---

## Domain Context

**Existing services:**
- `AppointmentService::bookAppointment(int $userId, int $serviceId, int $staffId, Carbon $scheduledDate): Appointment`
- `AppointmentService::cancelAppointment(int $appointmentId, ?string $reason): void` — throws `BookingException` if < 24h or not cancellable
- `AppointmentService::validateAvailability(int $staffId, int $serviceId, Carbon $dateTime): bool`
- `AppointmentService::getAvailableSlots(int $serviceId, int $staffId, string $date): array` — returns `[['start_time'=>'09:00:00','end_time'=>'09:30:00'], ...]`
- `PaymentService::initiateStripePayment(int $appointmentId, int $amountCents): Payment` — returns Payment with `stripe_transaction_id`

**Models:**
- `Service`: `id`, `name`, `description`, `duration_minutes`, `price`, `active`; scope `active()`
- `Appointment`: `id`, `user_id`, `staff_id`, `service_id`, `scheduled_date`, `status`, `final_price`, `notes`; method `canBeCancelled(): bool`
- `Payment`: `id`, `appointment_id`, `user_id`, `amount`, `status`, `stripe_transaction_id`; statuses: `pending`, `completed`, `refunded`, `failed`, `cancelled`
- `User`: has `isCustomer()`, `isStaff()`, `isAdmin()` helpers; roles: `admin`, `staff`, `customer`

**Exceptions:** `App\Exceptions\BookingException extends \RuntimeException` — maps to HTTP 422 in controllers.

**Test conventions (from CLAUDE.md):**
- `RefreshDatabase` is global for all Feature tests (set in `tests/Pest.php`)
- When writing tests that call `assignRole()`, role must exist first: `Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web'])`
- Use `actingAs($user)` for authenticated requests in feature tests
- Run tests: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/`

---

## Task 1: Sanctum Setup + API Scaffolding

**Files:**
- Run: `composer require laravel/sanctum` (in Docker)
- Run: `php artisan install:api --no-interaction`
- Modify: `app/Models/User.php`
- Modify: `config/services.php`
- Modify: `bootstrap/app.php` (verify `install:api` added it; add manually if not)
- Run: `php artisan migrate`

- [ ] **Step 1: Install Sanctum**

```bash
docker-compose run --rm --no-deps app composer require laravel/sanctum
```

Expected output: Sanctum added to composer.json, `vendor/laravel/sanctum` created.

- [ ] **Step 2: Run install:api**

```bash
docker-compose run --rm app php artisan install:api --no-interaction
```

Expected: Creates `routes/api.php`, creates `personal_access_tokens` migration, modifies `bootstrap/app.php` to add `api:` route entry.

- [ ] **Step 3: Verify bootstrap/app.php has api routing**

Read `bootstrap/app.php`. It should contain:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

If `api:` is missing, add it manually.

- [ ] **Step 4: Add HasApiTokens to User model**

Read `app/Models/User.php`. Add `use Laravel\Sanctum\HasApiTokens;` to imports and add `HasApiTokens` to the `use` statement in the class body.

```php
use Laravel\Sanctum\HasApiTokens;
// ...
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;
```

- [ ] **Step 5: Add stripe.secret to config/services.php**

Read `config/services.php` and add the Stripe entry at the end of the array (before the closing bracket):

```php
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'public' => env('STRIPE_PUBLIC_KEY'),
    ],
```

- [ ] **Step 6: Run migrations**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: `personal_access_tokens` table created.

- [ ] **Step 7: Verify existing tests still pass**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all 68 tests pass (no regressions).

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock bootstrap/app.php app/Models/User.php config/services.php routes/api.php database/migrations/
git commit -m "feat: install Sanctum and scaffold API routing"
```

---

## Task 2: ServiceController

**Files:**
- Create: `app/Http/Controllers/Api/ServiceController.php`
- Create: `app/Http/Requests/Api/SlotsRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ServiceControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/ServiceControllerTest.php`:

```php
<?php

use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\AppointmentService;

it('GET /api/services returns active services', function () {
    Service::factory()->count(3)->create(['active' => true]);
    Service::factory()->create(['active' => false]);

    $response = $this->getJson('/api/services');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'description', 'duration_minutes', 'price']]]);
});

it('GET /api/services/{service}/slots returns available slots', function () {
    $staff = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30, 'active' => true]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('getAvailableSlots')
        ->with($service->id, $staff->id, '2026-06-01')
        ->andReturn([
            ['start_time' => '09:00:00', 'end_time' => '09:30:00'],
            ['start_time' => '09:30:00', 'end_time' => '10:00:00'],
        ]);

    $response = $this->getJson("/api/services/{$service->id}/slots?date=2026-06-01&staff_id={$staff->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['start_time', 'end_time']]]);
});

it('GET /api/services/{service}/slots validates required params', function () {
    $service = Service::factory()->create();

    $response = $this->getJson("/api/services/{$service->id}/slots");

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'staff_id']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/ServiceControllerTest.php
```

Expected: FAIL — routes/controllers do not exist yet.

- [ ] **Step 3: Create SlotsRequest**

Create `app/Http/Requests/Api/SlotsRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'     => ['required', 'date'],
            'staff_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
```

- [ ] **Step 4: Create ServiceController**

Create `app/Http/Controllers/Api/ServiceController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SlotsRequest;
use App\Models\Service;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(): JsonResponse
    {
        $services = Service::active()->get();

        return response()->json(['data' => $services]);
    }

    public function slots(SlotsRequest $request, Service $service): JsonResponse
    {
        $slots = $this->appointmentService->getAvailableSlots(
            serviceId: $service->id,
            staffId:   $request->integer('staff_id'),
            date:      $request->string('date'),
        );

        return response()->json(['data' => $slots]);
    }
}
```

- [ ] **Step 5: Add routes to routes/api.php**

Replace the contents of `routes/api.php` with:

```php
<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{service}/slots', [ServiceController::class, 'slots']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::post('/appointments/{appointment}/payment', [PaymentController::class, 'confirm']);
});
```

- [ ] **Step 6: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/ServiceControllerTest.php
```

Expected: 3/3 pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/ServiceController.php app/Http/Requests/Api/SlotsRequest.php routes/api.php tests/Feature/Api/ServiceControllerTest.php
git commit -m "feat: add ServiceController with GET /services and GET /services/{service}/slots"
```

---

## Task 3: AppointmentController (store + read)

**Files:**
- Create: `app/Http/Controllers/Api/AppointmentController.php`
- Create: `app/Http/Requests/Api/BookAppointmentRequest.php`
- Test: `tests/Feature/Api/AppointmentControllerTest.php` (first part)

**Important:** `store()` calls both `AppointmentService::bookAppointment()` AND `PaymentService::initiateStripePayment()`. In tests, mock **both** services.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/AppointmentControllerTest.php`:

```php
<?php

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Mockery;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('POST /api/appointments books appointment and returns payment_intent_id', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['price' => 60.00, 'duration_minutes' => 30]);

    $appointment = Appointment::factory()->create([
        'user_id'     => $user->id,
        'service_id'  => $service->id,
        'staff_id'    => $staff->id,
        'final_price' => 60.00,
    ]);

    $payment = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'user_id'               => $user->id,
        'stripe_transaction_id' => 'pi_test_book_123',
        'status'                => 'pending',
        'amount'                => 60.00,
    ]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('bookAppointment')
        ->with($user->id, $service->id, $staff->id, Mockery::on(fn ($d) => $d instanceof Carbon))
        ->andReturn($appointment);

    $this->mock(PaymentService::class)
        ->shouldReceive('initiateStripePayment')
        ->with($appointment->id, 6000)
        ->andReturn($payment);

    $response = $this->actingAs($user)->postJson('/api/appointments', [
        'service_id'     => $service->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => '2026-06-10 10:00:00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_intent_id', 'pi_test_book_123')
        ->assertJsonPath('data.appointment.id', $appointment->id);
});

it('POST /api/appointments returns 422 on BookingException', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create();

    $this->mock(AppointmentService::class)
        ->shouldReceive('bookAppointment')
        ->andThrow(new BookingException('Staff non disponibile.'));

    $response = $this->actingAs($user)->postJson('/api/appointments', [
        'service_id'     => $service->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => '2026-06-10 10:00:00',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Staff non disponibile.');
});

it('POST /api/appointments validates required fields', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $response = $this->actingAs($user)->postJson('/api/appointments', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['service_id', 'staff_id', 'scheduled_date']);
});

it('POST /api/appointments requires auth', function () {
    $this->postJson('/api/appointments', [])->assertUnauthorized();
});

it('GET /api/appointments returns authenticated user appointments', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');
    Appointment::factory()->count(2)->create(['user_id' => $user->id]);

    $other = User::factory()->create();
    Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->getJson('/api/appointments');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/appointments requires auth', function () {
    $this->getJson('/api/appointments')->assertUnauthorized();
});

it('GET /api/appointments/{id} returns appointment detail', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/appointments/{$appointment->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $appointment->id);
});

it('GET /api/appointments/{id} returns 403 for another user appointment', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $other = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/AppointmentControllerTest.php
```

Expected: FAIL — controller does not exist.

- [ ] **Step 3: Create BookAppointmentRequest**

Create `app/Http/Requests/Api/BookAppointmentRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BookAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id'     => ['required', 'integer', 'exists:services,id'],
            'staff_id'       => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date', 'after:now'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 4: Create AppointmentController with store, index, show**

Create `app/Http/Controllers/Api/AppointmentController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BookAppointmentRequest;
use App\Http\Requests\Api\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(): JsonResponse
    {
        $appointments = Appointment::where('user_id', auth()->id())
            ->with(['service', 'staff', 'payment'])
            ->latest('scheduled_date')
            ->get();

        return response()->json(['data' => $appointments]);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $appointment->load('service', 'staff', 'payment')]);
    }

    public function store(BookAppointmentRequest $request): JsonResponse
    {
        try {
            $appointment = $this->appointmentService->bookAppointment(
                userId:        $request->user()->id,
                serviceId:     $request->integer('service_id'),
                staffId:       $request->integer('staff_id'),
                scheduledDate: Carbon::parse($request->string('scheduled_date')),
            );
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($request->filled('notes')) {
            $appointment->update(['notes' => $request->string('notes')]);
        }

        $amountCents = (int) round($appointment->final_price * 100);
        $payment = $this->paymentService->initiateStripePayment($appointment->id, $amountCents);

        return response()->json([
            'data' => [
                'appointment'      => $appointment->load('service', 'staff'),
                'payment_intent_id' => $payment->stripe_transaction_id,
            ],
        ], 201);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($appointment->status !== 'pending') {
            return response()->json(['message' => 'Solo appuntamenti pending possono essere modificati.'], 422);
        }

        if ($request->has('scheduled_date')) {
            $newDate = Carbon::parse($request->string('scheduled_date'));

            if (! $this->appointmentService->validateAvailability($appointment->staff_id, $appointment->service_id, $newDate)) {
                return response()->json(['message' => 'Staff non disponibile per questa data e ora.'], 422);
            }

            $appointment->update(['scheduled_date' => $newDate]);
        }

        if ($request->has('notes')) {
            $appointment->update(['notes' => $request->string('notes')]);
        }

        return response()->json(['data' => $appointment->fresh()->load('service', 'staff')]);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $this->appointmentService->cancelAppointment($appointment->id);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Appuntamento cancellato con successo.']);
    }
}
```

Note: `UpdateAppointmentRequest` is referenced here but created in Task 4. For now, create a placeholder:

Create `app/Http/Requests/Api/UpdateAppointmentRequest.php` (full content, not a placeholder):

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_date' => ['sometimes', 'date', 'after:now'],
            'notes'          => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
```

- [ ] **Step 5: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/AppointmentControllerTest.php
```

Expected: 8/8 pass.

- [ ] **Step 6: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all previous tests + 8 new = pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/AppointmentController.php app/Http/Requests/Api/BookAppointmentRequest.php app/Http/Requests/Api/UpdateAppointmentRequest.php tests/Feature/Api/AppointmentControllerTest.php
git commit -m "feat: add AppointmentController store/index/show endpoints"
```

---

## Task 4: AppointmentController (update + destroy tests)

**Files:**
- Modify: `tests/Feature/Api/AppointmentControllerTest.php` (add update + delete tests)

The `update()` and `destroy()` methods are already implemented in Task 3's controller. This task adds tests for them.

- [ ] **Step 1: Add update and destroy tests to AppointmentControllerTest.php**

Append these tests to the existing `tests/Feature/Api/AppointmentControllerTest.php`:

```php
it('PUT /api/appointments/{id} updates notes on pending appointment', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $appointment = Appointment::factory()->create([
        'user_id'   => $user->id,
        'staff_id'  => $staff->id,
        'service_id'=> $service->id,
        'status'    => 'pending',
        'notes'     => 'original note',
    ]);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'notes' => 'updated note',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.notes', 'updated note');
});

it('PUT /api/appointments/{id} returns 422 if not pending', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create([
        'user_id' => $user->id,
        'status'  => 'confirmed',
    ]);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'notes' => 'changed',
    ]);

    $response->assertUnprocessable();
});

it('PUT /api/appointments/{id} validates availability when changing date', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $appointment = Appointment::factory()->create([
        'user_id'    => $user->id,
        'staff_id'   => $staff->id,
        'service_id' => $service->id,
        'status'     => 'pending',
    ]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('validateAvailability')
        ->andReturn(false);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'scheduled_date' => now()->addDays(5)->toDateTimeString(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Staff non disponibile per questa data e ora.');
});

it('DELETE /api/appointments/{id} cancels appointment', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('cancelAppointment')
        ->with($appointment->id, null)
        ->once();

    $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'Appuntamento cancellato con successo.');
});

it('DELETE /api/appointments/{id} returns 422 on BookingException', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('cancelAppointment')
        ->andThrow(new BookingException('Impossibile cancellare meno di 24 ore prima.'));

    $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Impossibile cancellare meno di 24 ore prima.');
});

it('DELETE /api/appointments/{id} returns 403 for another user appointment', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $other = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail first (update/delete tests are new)**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/AppointmentControllerTest.php
```

Expected: previous 8 pass, new 6 might fail if controller has bugs — verify all 14 pass.

- [ ] **Step 3: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Api/AppointmentControllerTest.php
git commit -m "feat: add tests for AppointmentController update and destroy endpoints"
```

---

## Task 5: PaymentController + PaymentService::confirmPayment

**Files:**
- Modify: `app/Services/PaymentService.php` (add `confirmPayment`)
- Create: `app/Http/Controllers/Api/PaymentController.php`
- Test: `tests/Feature/Api/PaymentControllerTest.php`

**Flow:** Client confirms payment with Stripe.js, then calls `POST /api/appointments/{id}/payment`. Backend retrieves the PaymentIntent from Stripe; if `status == 'succeeded'`, marks the Payment record as `completed`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Api/PaymentControllerTest.php`:

```php
<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('POST /api/appointments/{id}/payment confirms payment and returns completed payment', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $payment     = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'user_id'               => $user->id,
        'stripe_transaction_id' => 'pi_confirmed_123',
        'status'                => 'completed',
        'amount'                => 60.00,
    ]);

    $this->mock(PaymentService::class)
        ->shouldReceive('confirmPayment')
        ->with($appointment->id)
        ->andReturn($payment->fresh());

    $response = $this->actingAs($user)->postJson("/api/appointments/{$appointment->id}/payment");

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.stripe_transaction_id', 'pi_confirmed_123');
});

it('POST /api/appointments/{id}/payment returns 403 for another user appointment', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $other = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->postJson("/api/appointments/{$appointment->id}/payment");

    $response->assertForbidden();
});

it('POST /api/appointments/{id}/payment requires auth', function () {
    $appointment = Appointment::factory()->create();

    $this->postJson("/api/appointments/{$appointment->id}/payment")->assertUnauthorized();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/PaymentControllerTest.php
```

Expected: FAIL — controller does not exist.

- [ ] **Step 3: Add confirmPayment to PaymentService**

Read `app/Services/PaymentService.php` and add the following method after `refundPayment`:

```php
public function confirmPayment(int $appointmentId): Payment
{
    $appointment = Appointment::findOrFail($appointmentId);
    $payment     = $appointment->payment;

    if (! $payment) {
        throw new BookingException('Nessun pagamento trovato per questo appuntamento.');
    }

    $paymentIntent = $this->stripe->paymentIntents->retrieve($payment->stripe_transaction_id);

    if ($paymentIntent->status === 'succeeded') {
        $payment->update(['status' => 'completed']);
    }

    return $payment->fresh();
}
```

Add `use App\Models\Appointment;` to the imports at the top of `PaymentService.php` if not already present.

- [ ] **Step 4: Create PaymentController**

Create `app/Http/Controllers/Api/PaymentController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function confirm(Appointment $appointment): JsonResponse
    {
        if ($appointment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $payment = $this->paymentService->confirmPayment($appointment->id);
        } catch (BookingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $payment]);
    }
}
```

- [ ] **Step 5: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Api/PaymentControllerTest.php
```

Expected: 3/3 pass.

- [ ] **Step 6: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass (68 existing + new API tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/PaymentService.php app/Http/Controllers/Api/PaymentController.php tests/Feature/Api/PaymentControllerTest.php
git commit -m "feat: add PaymentController confirm endpoint and PaymentService::confirmPayment"
```
