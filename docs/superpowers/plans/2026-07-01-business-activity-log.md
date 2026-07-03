# Business Activity Log — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere un sistema di log centralizzato per salone che registra model events e errori 500, visualizzabile nel superadmin dentro BusinessResource e in una pagina globale Platform Logs.

**Architecture:** Si estende la tabella `activity_log` di `spatie/laravel-activitylog` con colonne custom (`business_id`, `type`, `level`, `source`, `ip_address`, `user_agent`, `url`, `method`). Il modello custom `ActivityLog` estende quello Spatie. La logica di reporting delle eccezioni è estratta in `ExceptionActivityLogger` (non inline in bootstrap). La UI è un RelationManager Filament 4 su BusinessResource + una pagina globale Platform Logs.

**Tech Stack:** `spatie/laravel-activitylog` (versione determinata da Composer in base a Laravel 13 / PHP 8.4, può essere v4 o v5), Filament 4, Pest 4, PHP 8.4

## Global Constraints

- Tutti i comandi PHP/artisan vanno eseguiti dentro Docker: `docker-compose run --rm app <cmd>`
- Test sempre con `-e DB_DATABASE=booking_app_test` per non toccare il DB principale
- Non loggare dati sensibili: password, token, stripe_response, authorization header, cookie, CVV
- Non aggiungere `BelongsToBusiness` a `ActivityLog` — il superadmin deve vedere tutti i log senza global scope
- **Version-aware:** prima di installare il package, verificare la versione risolta da Composer. Se v4 → namespace `Spatie\Activitylog\Traits\LogsActivity`, `Spatie\Activitylog\LogOptions` e metodo `dontSubmitEmptyLogs()`. Se v5 → `Spatie\Activitylog\Models\Concerns\LogsActivity`, `Spatie\Activitylog\Support\LogOptions` e metodo `dontLogEmptyChanges()`. Il Task 1 ha un passaggio esplicito di verifica.
- `ActivityLog` estende `Spatie\Activitylog\Models\Activity`, usa la tabella `activity_log` già definita da Spatie
- Usare PHP 8 attribute syntax `#[Fillable]` per i modelli, non `$fillable`
- Query scopes ritornano `Builder`

---

## File Map

### Nuovi file
- `app/Models/ActivityLog.php` — modello custom che estende Spatie Activity, aggiunge relazione `business()`
- `app/Support/Logging/ExceptionActivityLogger.php` — logica di reporting eccezioni estratta da bootstrap
- `database/migrations/XXXX_create_activity_log_table.php` — pubblicata da Spatie
- `database/migrations/XXXX_add_custom_columns_to_activity_log.php` — aggiunge colonne custom
- `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/ActivityLogRelationManager.php`
- `app/Filament/SuperAdmin/Pages/PlatformLogsPage.php`
- `resources/views/filament/superadmin/pages/platform-logs.blade.php`
- `tests/Feature/Logging/ActivityLogTest.php`
- `tests/Feature/Logging/ErrorLoggingTest.php`

### File modificati
- `composer.json` — aggiunge `spatie/laravel-activitylog`
- `config/activitylog.php` — pubblicato da Spatie, punta al modello custom
- `app/Models/Appointment.php` — aggiunge `LogsActivity`, `getActivitylogOptions()`, `tapActivity()`
- `app/Models/Payment.php` — idem
- `app/Models/ProductOrder.php` — idem
- `app/Models/Service.php` — idem
- `app/Models/Business.php` — aggiunge `activityLogs()` HasMany
- `bootstrap/app.php` — registra `ExceptionActivityLogger` in `withExceptions`
- `app/Filament/SuperAdmin/Resources/BusinessResource.php` — registra RelationManager in `getRelations()`

---

## Task 1: Installazione Spatie + migrazione base + colonne custom

**Files:**
- Modify: `composer.json`
- Create: `config/activitylog.php` (via vendor:publish)
- Create: `database/migrations/XXXX_create_activity_log_table.php` (via vendor:publish)
- Create: `database/migrations/XXXX_add_custom_columns_to_activity_log.php`

**Interfaces:**
- Produces: tabella `activity_log` con colonne standard Spatie + `business_id`, `type`, `level`, `source`, `ip_address`, `user_agent`, `url`, `method`

- [ ] **Step 1: Installa il package (versione determinata da Composer)**

```bash
docker-compose run --rm --no-deps app composer require spatie/laravel-activitylog
```

Output atteso: `Package operations: 1 install`

- [ ] **Step 2: Verifica versione installata e annota i namespace corretti**

```bash
docker-compose run --rm --no-deps app composer show spatie/laravel-activitylog | grep "^versions"
```

- Se versione **4.x**: i namespace sono `Spatie\Activitylog\Traits\LogsActivity` e `Spatie\Activitylog\LogOptions`; per non salvare log vuoti usa `dontSubmitEmptyLogs()`
- Se versione **5.x**: i namespace sono `Spatie\Activitylog\Models\Concerns\LogsActivity` e `Spatie\Activitylog\Support\LogOptions`; per non salvare log vuoti usa `dontLogEmptyChanges()`

**Annota la versione** — tutti i `use` nei Task 3 e 4 devono usare i namespace corrispondenti.

- [ ] **Step 3: Pubblica migration e config**

```bash
docker-compose run --rm app php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
docker-compose run --rm app php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
```

Output atteso: file pubblicati in `database/migrations/` e `config/activitylog.php`

- [ ] **Step 4: Crea la migration per le colonne custom**

```bash
docker-compose run --rm app php artisan make:migration add_custom_columns_to_activity_log
```

Apri il file generato e sostituisci il contenuto con:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete()->after('id');
            $table->string('type', 20)->nullable()->after('properties');   // activity, error, system
            $table->string('level', 20)->nullable()->after('type');        // info, warning, error, critical
            $table->string('source', 30)->nullable()->after('level');      // model_event, exception_reporter, manual, webhook
            $table->string('ip_address', 45)->nullable()->after('source');
            $table->string('user_agent', 500)->nullable()->after('ip_address');
            $table->string('url', 2000)->nullable()->after('user_agent');
            $table->string('method', 10)->nullable()->after('url');

            $table->index('business_id');
            $table->index(['type', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropIndex(['business_id']);
            $table->dropIndex(['type', 'level']);
            $table->dropColumn(['business_id', 'type', 'level', 'source', 'ip_address', 'user_agent', 'url', 'method']);
        });
    }
};
```

- [ ] **Step 5: Esegui le migrazioni**

```bash
docker-compose run --rm app php artisan migrate
```

Output atteso: migration eseguite senza errori

- [ ] **Step 6: Verifica manuale**

```bash
docker-compose run --rm app php artisan tinker --execute="echo implode(', ', array_column(Schema::getColumns('activity_log'), 'name'));"
```

Output atteso: le colonne `business_id`, `type`, `level`, `source`, `ip_address`, `user_agent`, `url`, `method` compaiono nella lista

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/activitylog.php database/migrations/
git commit -m "feat(logging): install spatie/laravel-activitylog and extend table with business_id, type, level, source, request columns"
```

---

## Task 2: Modello ActivityLog custom + config

**Files:**
- Create: `app/Models/ActivityLog.php`
- Modify: `config/activitylog.php`

**Interfaces:**
- Produces: `ActivityLog` class; `activity()` helper restituisce istanze di `ActivityLog`
- Consumes: `businesses` table (Task 1)

- [ ] **Step 1: Scrivi il test**

Crea `tests/Feature/Logging/ActivityLogTest.php`:

```php
<?php

use App\Models\ActivityLog;
use App\Models\Business;

it('activity helper returns ActivityLog instance', function () {
    $log = activity()->log('test event');

    expect($log)->toBeInstanceOf(ActivityLog::class);
});

it('ActivityLog has business relation', function () {
    $business = Business::factory()->create();

    $log = activity()
        ->tap(function (ActivityLog $a) use ($business) {
            $a->business_id = $business->id;
        })
        ->log('test');

    expect($log->fresh()->business->id)->toBe($business->id);
});
```

- [ ] **Step 2: Esegui il test — deve fallire**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Logging/ActivityLogTest.php
```

Output atteso: FAIL — `ActivityLog` non esiste ancora

- [ ] **Step 3: Crea il modello custom**

Crea `app/Models/ActivityLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity;

#[Fillable([
    'log_name', 'description', 'subject_type', 'subject_id', 'event',
    'causer_type', 'causer_id', 'properties', 'attribute_changes', 'batch_uuid',
    'business_id', 'type', 'level', 'source',
    'ip_address', 'user_agent', 'url', 'method',
])]
class ActivityLog extends Activity
{
    protected $table = 'activity_log';

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
```

- [ ] **Step 4: Aggiorna config/activitylog.php**

Apri `config/activitylog.php` e modifica la chiave `activity_model`:

```php
'activity_model' => \App\Models\ActivityLog::class,
```

- [ ] **Step 5: Esegui il test — deve passare**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Logging/ActivityLogTest.php
```

Output atteso: 2 test PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/ActivityLog.php config/activitylog.php tests/Feature/Logging/ActivityLogTest.php
git commit -m "feat(logging): add custom ActivityLog model extending Spatie Activity"
```

---

## Task 3: LogsActivity sui modelli + relazione Business

**Files:**
- Modify: `app/Models/Appointment.php`
- Modify: `app/Models/Payment.php`
- Modify: `app/Models/ProductOrder.php`
- Modify: `app/Models/Service.php`
- Modify: `app/Models/Business.php`
- Test: `tests/Feature/Logging/ActivityLogTest.php`

**Interfaces:**
- Consumes: `ActivityLog` (Task 2), versione Spatie annotata in Task 1 Step 2
- Produces: ogni create/update/delete su Appointment, Payment, ProductOrder, Service genera un record in `activity_log` con `business_id` e `source='model_event'` corretti

**Nota namespace:** usa i `use` della versione Spatie annotata in Task 1 Step 2:
- v4: `Spatie\Activitylog\Traits\LogsActivity`, `Spatie\Activitylog\LogOptions`
- v5: `Spatie\Activitylog\Models\Concerns\LogsActivity`, `Spatie\Activitylog\Support\LogOptions`

Per `tapActivity()` usa `Spatie\Activitylog\Contracts\Activity`, compatibile con il modello custom configurato.

- [ ] **Step 1: Aggiungi i test ai model events**

Aggiungi a `tests/Feature/Logging/ActivityLogTest.php`:

```php
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;

it('logs appointment creation with correct business_id and source', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);

    Appointment::factory()->create([
        'business_id' => $business->id,
        'staff_id'    => $staff->id,
        'user_id'     => $customer->id,
    ]);

    $log = ActivityLog::where('subject_type', Appointment::class)
        ->where('event', 'created')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id)
        ->and($log->type)->toBe('activity')
        ->and($log->level)->toBe('info')
        ->and($log->source)->toBe('model_event');
});

it('logs appointment status update', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);

    $appointment = Appointment::factory()->create([
        'business_id' => $business->id,
        'staff_id'    => $staff->id,
        'user_id'     => $customer->id,
        'status'      => 'pending',
    ]);

    ActivityLog::truncate();
    $appointment->update(['status' => 'confirmed']);

    $log = ActivityLog::where('subject_type', Appointment::class)
        ->where('event', 'updated')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id);
});

it('logs service creation with correct business_id', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Service::factory()->create(['business_id' => $business->id]);

    $log = ActivityLog::where('subject_type', Service::class)
        ->where('event', 'created')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id);
});

it('Business activityLogs relation returns only its own logs', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    activity()->tap(fn(ActivityLog $a) => $a->business_id = $b1->id)->log('b1 log');
    activity()->tap(fn(ActivityLog $a) => $a->business_id = $b2->id)->log('b2 log');
    activity()->log('no business log');

    expect($b1->activityLogs)->toHaveCount(1)
        ->and($b1->activityLogs->first()->description)->toBe('b1 log');
});
```

- [ ] **Step 2: Esegui i test — devono fallire**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Logging/ActivityLogTest.php --filter "logs appointment"
```

Output atteso: FAIL — `LogsActivity` non aggiunto ancora

- [ ] **Step 3: Aggiorna Appointment**

In `app/Models/Appointment.php` aggiungi (adatta namespace alla versione Spatie annotata):

```php
// v4:
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
// v5:
// use Spatie\Activitylog\Support\LogOptions;
// use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Contracts\Activity;
```

Dentro la classe, dopo `use HasFactory, BelongsToBusiness;`:

```php
use LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    $options = LogOptions::defaults()
        ->logOnly(['status', 'scheduled_date', 'staff_id', 'final_price', 'notes'])
        ->logOnlyDirty()
        ->setDescriptionForEvent(fn(string $event) => "appuntamento {$event}");

    return method_exists($options, 'dontLogEmptyChanges')
        ? $options->dontLogEmptyChanges()
        : $options->dontSubmitEmptyLogs();
}

public function tapActivity(Activity $activity, string $eventName): void
{
    $activity->business_id = $this->business_id;
    $activity->type        = 'activity';
    $activity->level       = 'info';
    $activity->source      = 'model_event';
}
```

- [ ] **Step 4: Aggiorna Payment**

In `app/Models/Payment.php` (stessi `use` della versione corretta):

```php
use Spatie\Activitylog\LogOptions;   // o Support\LogOptions per v5
use Spatie\Activitylog\Traits\LogsActivity;  // o Models\Concerns\LogsActivity per v5
use Spatie\Activitylog\Contracts\Activity;
```

Dopo `use BelongsToBusiness, HasFactory;`:

```php
use LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    $options = LogOptions::defaults()
        ->logOnly(['status', 'amount', 'payment_method'])
        ->logOnlyDirty()
        ->setDescriptionForEvent(fn(string $event) => "pagamento {$event}");

    return method_exists($options, 'dontLogEmptyChanges')
        ? $options->dontLogEmptyChanges()
        : $options->dontSubmitEmptyLogs();
}

public function tapActivity(Activity $activity, string $eventName): void
{
    $activity->business_id = $this->business_id;
    $activity->type        = 'activity';
    $activity->level       = $eventName === 'deleted' ? 'warning' : 'info';
    $activity->source      = 'model_event';
}
```

- [ ] **Step 5: Aggiorna ProductOrder**

Stesso pattern. Dopo `use BelongsToBusiness, HasFactory;`:

```php
use LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    $options = LogOptions::defaults()
        ->logOnly(['status', 'payment_status', 'payment_method'])
        ->logOnlyDirty()
        ->setDescriptionForEvent(fn(string $event) => "ordine prodotto {$event}");

    return method_exists($options, 'dontLogEmptyChanges')
        ? $options->dontLogEmptyChanges()
        : $options->dontSubmitEmptyLogs();
}

public function tapActivity(Activity $activity, string $eventName): void
{
    $activity->business_id = $this->business_id;
    $activity->type        = 'activity';
    $activity->level       = 'info';
    $activity->source      = 'model_event';
}
```

- [ ] **Step 6: Aggiorna Service**

Stesso pattern. Dopo `use BelongsToBusiness, HasFactory;`:

```php
use LogsActivity;

public function getActivitylogOptions(): LogOptions
{
    $options = LogOptions::defaults()
        ->logOnly(['name', 'price', 'active', 'duration_minutes'])
        ->logOnlyDirty()
        ->setDescriptionForEvent(fn(string $event) => "servizio {$event}");

    return method_exists($options, 'dontLogEmptyChanges')
        ? $options->dontLogEmptyChanges()
        : $options->dontSubmitEmptyLogs();
}

public function tapActivity(Activity $activity, string $eventName): void
{
    $activity->business_id = $this->business_id;
    $activity->type        = 'activity';
    $activity->level       = 'info';
    $activity->source      = 'model_event';
}
```

- [ ] **Step 7: Aggiungi relazione activityLogs su Business**

In `app/Models/Business.php` aggiungi il `use` (se non già presente):

```php
use App\Models\ActivityLog;
```

E il metodo:

```php
public function activityLogs(): HasMany
{
    return $this->hasMany(ActivityLog::class);
}
```

Verifica che `use Illuminate\Database\Eloquent\Relations\HasMany;` sia già presente nell'import block.

- [ ] **Step 8: Esegui tutti i test del task**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Logging/ActivityLogTest.php
```

Output atteso: tutti i test PASS

- [ ] **Step 9: Suite completa per regression check**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Output atteso: nessun test rotto

- [ ] **Step 10: Commit**

```bash
git add app/Models/Appointment.php app/Models/Payment.php app/Models/ProductOrder.php app/Models/Service.php app/Models/Business.php tests/Feature/Logging/ActivityLogTest.php
git commit -m "feat(logging): add LogsActivity to Appointment, Payment, ProductOrder, Service with business_id and source context"
```

---

## Task 4: ExceptionActivityLogger + reporter

**Files:**
- Create: `app/Support/Logging/ExceptionActivityLogger.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/Logging/ErrorLoggingTest.php`

**Interfaces:**
- Consumes: `ActivityLog` (Task 2), `app('current_business_id')` se bindato
- Produces: ogni eccezione con HTTP status >= 500 (o non-HTTP) viene scritta in `activity_log` con `type='error'`, `source='exception_reporter'`, `business_id` risolto dal contesto o `null`

- [ ] **Step 1: Scrivi i test**

Crea `tests/Feature/Logging/ErrorLoggingTest.php`:

```php
<?php

use App\Models\ActivityLog;
use App\Models\Business;

it('logs exception with business_id when tenant context is bound', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    report(new RuntimeException('Tenant error test'));

    $log = ActivityLog::where('type', 'error')->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id)
        ->and($log->level)->toBe('error')
        ->and($log->source)->toBe('exception_reporter')
        ->and($log->description)->toBe('Tenant error test');
});

it('logs exception with null business_id when no tenant context', function () {
    if (app()->bound('current_business_id')) {
        app()->forgetInstance('current_business_id');
    }

    report(new RuntimeException('Platform error test'));

    $log = ActivityLog::where('type', 'error')
        ->where('description', 'Platform error test')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBeNull();
});

it('does not log 404 HTTP exceptions', function () {
    $before = ActivityLog::where('type', 'error')->count();

    report(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found'));

    expect(ActivityLog::where('type', 'error')->count())->toBe($before);
});

it('logs HTTP 500 exceptions', function () {
    $before = ActivityLog::where('type', 'error')->count();

    report(new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Server error'));

    expect(ActivityLog::where('type', 'error')->count())->toBeGreaterThan($before);
});

it('stores exception class in properties', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    report(new RuntimeException('Props test'));

    $log = ActivityLog::where('description', 'Props test')->latest()->first();

    expect($log->properties)->toHaveKey('exception')
        ->and($log->properties['exception'])->toBe('RuntimeException');
});

it('does not store sensitive data in properties', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    request()->merge([
        'email' => 'customer@example.test',
        'password' => 'secret',
        '_token' => 'csrf-token',
        'nested' => ['authorization' => 'Bearer secret'],
    ]);

    report(new RuntimeException('Sensitive test'));

    $log = ActivityLog::where('description', 'Sensitive test')->latest()->first();

    $props = json_encode($log->properties ?? []);
    expect($props)
        ->toContain('customer@example.test')
        ->not->toContain('password')
        ->not->toContain('secret')
        ->not->toContain('_token')
        ->not->toContain('authorization');
});
```

- [ ] **Step 2: Esegui i test — devono fallire**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Logging/ErrorLoggingTest.php
```

Output atteso: FAIL — reporter non registrato

- [ ] **Step 3: Crea ExceptionActivityLogger**

Crea `app/Support/Logging/ExceptionActivityLogger.php`:

```php
<?php

namespace App\Support\Logging;

use App\Models\ActivityLog;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExceptionActivityLogger
{
    public function report(Throwable $e): void
    {
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return;
        }

        try {
            $businessId = app()->bound('current_business_id') ? app('current_business_id') : null;
            $request    = request();

            activity()
                ->tap(function (ActivityLog $activity) use ($businessId, $request, $e) {
                    $activity->business_id = $businessId;
                    $activity->type        = 'error';
                    $activity->level       = $e instanceof \Error ? 'critical' : 'error';
                    $activity->source      = 'exception_reporter';
                    $activity->ip_address  = $request->ip();
                    $activity->user_agent  = substr((string) ($request->userAgent() ?? ''), 0, 500);
                    $activity->url         = substr($request->fullUrl(), 0, 2000);
                    $activity->method      = $request->method();
                })
                ->causedBy(auth()->user())
                ->withProperties([
                    'exception'     => class_basename($e),
                    'file'          => $e->getFile(),
                    'line'          => $e->getLine(),
                    'trace'         => collect(explode("\n", $e->getTraceAsString()))->take(10)->join("\n"),
                    'request_input' => $this->sanitizeContext($request->input()),
                ])
                ->log($e->getMessage() ?: get_class($e));
        } catch (Throwable) {
            // Mai far crashare l'app per un errore nel logger
        }
    }

    private function sanitizeContext(array $data): array
    {
        $blocked = [
            'password',
            'password_confirmation',
            '_token',
            'token',
            'authorization',
            'cookie',
            'stripe_response',
            'card',
            'card_number',
            'cvv',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizeContext($value);
            }
        }

        return $data;
    }
}
```

- [ ] **Step 4: Registra in bootstrap/app.php**

Apri `bootstrap/app.php`. Aggiungi il `use` dopo `use Illuminate\Foundation\Configuration\Exceptions;`:

```php
use Throwable;
```

Sostituisci:

```php
->withExceptions(function (Exceptions $exceptions): void {
    //
})->create();
```

Con:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->report(function (Throwable $e): void {
        app(\App\Support\Logging\ExceptionActivityLogger::class)->report($e);
    });
})->create();
```

Non restituire `false` dal callback: il reporter custom deve aggiungere il record in `activity_log`, ma il normale logging Laravel su `laravel.log` deve continuare.

- [ ] **Step 5: Esegui i test — devono passare**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Logging/ErrorLoggingTest.php
```

Output atteso: 6 test PASS

- [ ] **Step 6: Suite completa**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Output atteso: nessun test rotto

- [ ] **Step 7: Commit**

```bash
git add app/Support/Logging/ExceptionActivityLogger.php bootstrap/app.php tests/Feature/Logging/ErrorLoggingTest.php
git commit -m "feat(logging): extract ExceptionActivityLogger and register exception reporter for activity_log"
```

---

## Task 5: Filament UI — RelationManager + PlatformLogsPage

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/ActivityLogRelationManager.php`
- Modify: `app/Filament/SuperAdmin/Resources/BusinessResource.php`
- Create: `app/Filament/SuperAdmin/Pages/PlatformLogsPage.php`
- Create: `resources/views/filament/superadmin/pages/platform-logs.blade.php`

**Interfaces:**
- Consumes: `Business::activityLogs()` HasMany (Task 3), `ActivityLog` (Task 2)
- Produces: tab "Log" visibile in `/superadmin/businesses/{id}/edit`, pagina "Platform Logs" nel menu superadmin

- [ ] **Step 1: Crea ActivityLogRelationManager**

Crea `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/ActivityLogRelationManager.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Log';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'error'    => 'danger',
                        'activity' => 'primary',
                        'system'   => 'gray',
                        default    => 'gray',
                    }),

                TextColumn::make('level')
                    ->label('Livello')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'critical' => 'danger',
                        'error'    => 'warning',
                        'warning'  => 'warning',
                        'info'     => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(80)
                    ->tooltip(fn (TextColumn $column): ?string => strlen($column->getState() ?? '') > 80 ? $column->getState() : null),

                TextColumn::make('source')
                    ->label('Sorgente')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subject_type')
                    ->label('Modello')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),

                TextColumn::make('causer.name')
                    ->label('Utente')
                    ->placeholder('—'),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('method')
                    ->label('Metodo')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'activity' => 'Attività',
                        'error'    => 'Errore',
                        'system'   => 'Sistema',
                    ]),

                SelectFilter::make('level')
                    ->label('Livello')
                    ->options([
                        'info'     => 'Info',
                        'warning'  => 'Warning',
                        'error'    => 'Error',
                        'critical' => 'Critical',
                    ]),

                SelectFilter::make('subject_type')
                    ->label('Modello')
                    ->options([
                        'App\\Models\\Appointment'  => 'Appuntamento',
                        'App\\Models\\Payment'      => 'Pagamento',
                        'App\\Models\\ProductOrder' => 'Ordine prodotto',
                        'App\\Models\\Service'      => 'Servizio',
                    ]),

                Filter::make('date_from')
                    ->label('Da data')
                    ->form([\Filament\Forms\Components\DatePicker::make('date_from')->label('Da')])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['date_from'] ?? null,
                        fn ($q) => $q->whereDate('created_at', '>=', $data['date_from'] ?? null)
                    )),

                Filter::make('date_to')
                    ->label('A data')
                    ->form([\Filament\Forms\Components\DatePicker::make('date_to')->label('A')])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['date_to'] ?? null,
                        fn ($q) => $q->whereDate('created_at', '<=', $data['date_to'] ?? null)
                    )),
            ])
            ->recordAction(null)
            ->paginated([25, 50, 100]);
    }
}
```

- [ ] **Step 2: Registra il RelationManager in BusinessResource**

In `app/Filament/SuperAdmin/Resources/BusinessResource.php`, modifica `getRelations()`:

```php
public static function getRelations(): array
{
    return [
        \App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers\BusinessAdminsRelationManager::class,
        \App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers\ActivityLogRelationManager::class,
    ];
}
```

- [ ] **Step 3: Crea PlatformLogsPage**

Crea `app/Filament/SuperAdmin/Pages/PlatformLogsPage.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\ActivityLog;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlatformLogsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Platform Logs';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.superadmin.pages.platform-logs';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query()->whereNull('business_id'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'error'    => 'danger',
                        'activity' => 'primary',
                        'system'   => 'gray',
                        default    => 'gray',
                    }),

                TextColumn::make('level')
                    ->label('Livello')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'critical' => 'danger',
                        'error'    => 'warning',
                        'warning'  => 'warning',
                        'info'     => 'success',
                        default    => 'gray',
                    }),

                TextColumn::make('description')
                    ->label('Descrizione')
                    ->limit(100)
                    ->tooltip(fn (TextColumn $column): ?string => strlen($column->getState() ?? '') > 100 ? $column->getState() : null),

                TextColumn::make('source')
                    ->label('Sorgente')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(60)
                    ->placeholder('—'),

                TextColumn::make('method')
                    ->label('Metodo')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('causer.name')
                    ->label('Utente')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('level')
                    ->label('Livello')
                    ->options([
                        'info'     => 'Info',
                        'warning'  => 'Warning',
                        'error'    => 'Error',
                        'critical' => 'Critical',
                    ]),

                Filter::make('date_from')
                    ->label('Da data')
                    ->form([\Filament\Forms\Components\DatePicker::make('date_from')->label('Da')])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['date_from'] ?? null,
                        fn ($q) => $q->whereDate('created_at', '>=', $data['date_from'] ?? null)
                    )),

                Filter::make('date_to')
                    ->label('A data')
                    ->form([\Filament\Forms\Components\DatePicker::make('date_to')->label('A')])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['date_to'] ?? null,
                        fn ($q) => $q->whereDate('created_at', '<=', $data['date_to'] ?? null)
                    )),
            ])
            ->recordAction(null)
            ->paginated([25, 50, 100]);
    }
}
```

- [ ] **Step 4: Crea la blade view**

```bash
docker-compose run --rm --no-deps app mkdir -p resources/views/filament/superadmin/pages
```

Crea `resources/views/filament/superadmin/pages/platform-logs.blade.php`:

```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 5: Verifica manuale**

1. `docker-compose up -d`
2. Apri `/superadmin/businesses/{id}/edit` — verifica tab "Log"
3. Crea un appuntamento → ricarica tab Log → compare riga `type=activity, source=model_event`
4. Apri `/superadmin/platform-logs` — deve apparire nel menu e caricare senza errori
5. Se la tabella non renderizza, confrontare `PlatformLogsPage` con `app/Filament/Pages/SiteBuilderPage.php`, che usa lo stesso pattern `Page implements HasTable` + `InteractsWithTable`

- [ ] **Step 6: Suite completa finale**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Output atteso: tutti i test PASS

- [ ] **Step 7: Commit**

```bash
git add app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/ActivityLogRelationManager.php \
        app/Filament/SuperAdmin/Resources/BusinessResource.php \
        app/Filament/SuperAdmin/Pages/PlatformLogsPage.php \
        resources/views/filament/superadmin/pages/platform-logs.blade.php
git commit -m "feat(logging): add ActivityLogRelationManager and PlatformLogsPage for superadmin"
```

---

## Self-Review

### Spec coverage

| Requisito | Task |
|-----------|------|
| Estendi `activity_log` con colonne custom incluso `source` | Task 1 |
| Modello custom `ActivityLog` + config | Task 2 |
| LogsActivity su Appointment, Payment, ProductOrder, Service | Task 3 |
| `business_id` e `source='model_event'` risolti in `tapActivity` | Task 3 |
| `activityLogs()` su `Business` | Task 3 |
| Exception reporter estratto in classe dedicata `ExceptionActivityLogger` | Task 4 |
| Escludi HTTP < 500, logga HTTP >= 500 | Task 4 |
| `source='exception_reporter'` sugli errori | Task 4 |
| `sanitizeContext` per dati sensibili | Task 4 |
| `business_id = null` come fallback | Task 4 |
| Tab Log in BusinessResource con filtri | Task 5 |
| Platform Logs page (business_id IS NULL) | Task 5 |
| `Page implements HasTable` + `InteractsWithTable` su PlatformLogsPage | Task 5 |
| Version-awareness Spatie v4/v5 | Task 1 Step 2 + nota nei Task 3/4 |
| Test model events con `source` | Task 3 |
| Test exception reporter con 404 escluso e 500 incluso | Task 4 |
| Test dati sensibili | Task 4 |

### Note non implementate (decisione consapevole)

- **Import vecchi laravel.log**: escluso su richiesta esplicita.
- **pxlrbt/filament-activity-log**: non adatto — è per timeline di un singolo record, non per vista business-wide.
- **Risoluzione business da subdomain nell'exception handler**: se `current_business_id` non è bindato quando esplode l'eccezione, il `SubdomainMiddleware` non è passato — l'errore è di piattaforma a tutti gli effetti.
