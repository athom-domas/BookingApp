# WhatsApp Notifications Implementation Plan (rev. 2 — basata sul branch `feature/whatsapp-ai-booking`)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Notifiche WhatsApp operative (conferma, promemoria, annullamento, riprogrammazione) come feature premium per-business controllata dal superadmin, con logging, limiti mensili e UI — riusando l'infrastruttura WhatsApp già presente sul branch `feature/whatsapp-ai-booking`.

**Architecture:** Le credenziali Meta restano in `IntegrationSetting` (unica fonte di verità, già usata da webhook e AI). Il gating premium (flag + limite mensile) sono colonne nuove su `integration_settings`, editabili solo dal superadmin. Il log degli invii riusa la tabella `whatsapp_messages` esistente (righe `direction=outbound, type=template`) con colonne nuove `appointment_id`/`template_name`/`status`/`sent_at` — così i delivery status del webhook si agganciano gratis via `wamid`. `WhatsAppNotificationService` fa da gate (enabled? creds? limite? telefono? canale? duplicato?) e crea il record prima di dispatchare `SendWhatsAppNotificationJob`, che invia tramite il `WhatsAppService` esistente.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Pest, `#[Fillable]`, `protected function casts(): array`, `#[ListensTo]` per i listener, `Http::fake` nei test.

## Global Constraints

- PHP 8 attribute syntax: `#[Fillable([...])]` — mai `$fillable`/`$hidden`
- `protected function casts(): array` — mai `$casts` property
- Query scopes devono restituire `Builder`
- Test: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest` (SEMPRE con `-e DB_DATABASE=booking_app_test`)
- `RefreshDatabase` attivo globalmente in `tests/Pest.php` — non aggiungerlo nei test
- Ruoli devono esistere in DB prima di `assignRole()`: `Role::firstOrCreate([...])`
- Filament 4: `Filament\Schemas\Components\Section`, `Filament\Schemas\Schema` (non `Filament\Forms\Form`)
- Nessun commento a meno che il WHY non sia non ovvio
- Le funzioni helper nei file Pest sono globali: nomi univoci per file (mai riusare `makeAppointment` ecc.)

## Contesto branch (leggere prima di iniziare)

Il branch `feature/whatsapp-ai-booking` contiene già (NON ricreare):
- `App\Services\WhatsAppService` — client Meta Graph API (`sendTemplate`, `sendTextWithinWindow`), credenziali da `IntegrationSetting`
- `App\Services\PhoneNormalizer` — normalizza in `+39...`
- Tabella `whatsapp_messages` (migration `2026_06_24_000001`) + model `App\Models\WhatsAppMessage` (senza `BelongsToBusiness`, quindi senza global scope) — log conversazioni AI inbound/outbound
- Tabella `whatsapp_message_statuses` + model — delivery status salvati dal webhook per `provider_message_id` (= wamid)
- `WhatsAppWebhookController`, `ProcessWhatsAppMessageJob`, `WhatsAppConversationService`, `WhatsAppToolDispatcher`, `WhatsAppConversationState`
- Sezione WhatsApp/AI nella pagina Filament tenant `IntegrationSettings` (token, phone_id, template, toggle AI)
- `SendAppointmentReminder` invia già il promemoria via `WhatsAppService::sendTemplateDefault` con fallback email

Fuori scope di questo piano: rimozione Twilio/`NotificationService` (cleanup separato).

---

## File Map

**Nuovi file:**
- `database/migrations/2026_07_03_120000_add_whatsapp_notification_fields.php`
- `app/Services/WhatsAppNotificationService.php`
- `app/Jobs/SendWhatsAppNotificationJob.php`
- `app/Listeners/SendWhatsAppAppointmentNotification.php`
- `app/Console/Commands/ResetMonthlyWhatsAppCounters.php`
- `tests/Feature/WhatsApp/NotificationModelsTest.php`
- `tests/Feature/WhatsApp/NotificationServiceTest.php`
- `tests/Feature/WhatsApp/SendWhatsAppNotificationJobTest.php`
- `tests/Feature/WhatsApp/AppointmentWhatsAppIntegrationTest.php`
- `tests/Feature/Filament/WhatsAppNotificationsAdminTest.php`

**File modificati:**
- `app/Models/WhatsAppMessage.php` — fillable/casts nuovi, `appointment()`, scope `forAppointmentTemplate`
- `app/Models/IntegrationSetting.php` — fillable/casts nuovi, helper gating
- `app/Services/WhatsAppService.php` — `sendTemplate` ritorna `?string` (wamid); rimozione `sendTemplateDefault` (Task 4)
- `app/Jobs/SendAppointmentReminder.php` — usa `WhatsAppNotificationService`
- `app/Services/AppointmentRescheduleService.php` — notifica reschedule dopo la transaction
- `routes/console.php` — schedule reset mensile
- `app/Filament/SuperAdmin/Resources/BusinessResource.php` — action tabella "WhatsApp"
- `app/Filament/Pages/IntegrationSettings.php` — placeholder stato notifiche/contatore
- `tests/Feature/WhatsApp/WhatsAppServiceTest.php` — nuovo return type
- `tests/Feature/Jobs/NotificationJobsTest.php` — test reminder riscritti

---

## Task 1: Migration + model (colonne notifiche e gating)

**Files:**
- Create: `database/migrations/2026_07_03_120000_add_whatsapp_notification_fields.php`
- Modify: `app/Models/WhatsAppMessage.php`
- Modify: `app/Models/IntegrationSetting.php`
- Test: `tests/Feature/WhatsApp/NotificationModelsTest.php`

**Interfaces:**
- Produces: colonne `whatsapp_messages.appointment_id|template_name|status|sent_at`
- Produces: colonne `integration_settings.whatsapp_notifications_enabled|whatsapp_monthly_limit|whatsapp_monthly_sent`
- Produces: `IntegrationSetting::hasWhatsAppNotificationsEnabled(): bool`, `IntegrationSetting::hasWhatsAppMonthlyCapacity(): bool` (metodi d'istanza)
- Produces: `WhatsAppMessage::scopeForAppointmentTemplate(Builder, int $appointmentId, string $templateName): Builder`, `WhatsAppMessage::appointment(): BelongsTo`

- [ ] **Step 1: Scrivere i test**

```php
<?php
// tests/Feature/WhatsApp/NotificationModelsTest.php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
});

it('notifications are disabled by default', function () {
    $business = Business::factory()->create();
    $setting  = IntegrationSetting::create(['business_id' => $business->id]);

    expect($setting->hasWhatsAppNotificationsEnabled())->toBeFalse();
});

it('hasWhatsAppMonthlyCapacity returns true when limit is null', function () {
    $business = Business::factory()->create();
    $setting  = IntegrationSetting::create([
        'business_id'           => $business->id,
        'whatsapp_monthly_limit' => null,
        'whatsapp_monthly_sent' => 9999,
    ]);

    expect($setting->hasWhatsAppMonthlyCapacity())->toBeTrue();
});

it('hasWhatsAppMonthlyCapacity returns false when limit reached', function () {
    $business = Business::factory()->create();
    $setting  = IntegrationSetting::create([
        'business_id'            => $business->id,
        'whatsapp_monthly_limit' => 100,
        'whatsapp_monthly_sent'  => 100,
    ]);

    expect($setting->hasWhatsAppMonthlyCapacity())->toBeFalse();
});

it('forAppointmentTemplate scope finds notification rows', function () {
    $business    = Business::factory()->create();
    $appointment = Appointment::factory()->create(['business_id' => $business->id]);

    WhatsAppMessage::create([
        'business_id'      => $business->id,
        'appointment_id'   => $appointment->id,
        'phone'            => '+393331234567',
        'phone_normalized' => '+393331234567',
        'direction'        => 'outbound',
        'type'             => 'template',
        'template_name'    => 'appointment_confirmed',
        'payload'          => ['parameters' => ['Mario']],
        'status'           => 'sent',
    ]);

    $exists = WhatsAppMessage::where('business_id', $business->id)
        ->forAppointmentTemplate($appointment->id, 'appointment_confirmed')
        ->whereIn('status', ['queued', 'sent'])
        ->exists();

    expect($exists)->toBeTrue();
    expect(WhatsAppMessage::first()->appointment->id)->toBe($appointment->id);
});
```

- [ ] **Step 2: Verificare che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/NotificationModelsTest.php
```
Expected: FAIL — colonne/metodi mancanti.

- [ ] **Step 3: Creare la migration**

```php
<?php
// database/migrations/2026_07_03_120000_add_whatsapp_notification_fields.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('business_id')
                ->constrained()->nullOnDelete();
            $table->string('template_name', 100)->nullable()->after('type');
            $table->string('status', 20)->nullable()->after('template_name');
            $table->timestamp('sent_at')->nullable()->after('processed_at');

            $table->index(['business_id', 'appointment_id', 'template_name'], 'wa_messages_notification_idx');
        });

        Schema::table('integration_settings', function (Blueprint $table) {
            $table->boolean('whatsapp_notifications_enabled')->default(false);
            $table->unsignedInteger('whatsapp_monthly_limit')->nullable();
            $table->unsignedInteger('whatsapp_monthly_sent')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_messages_notification_idx');
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropColumn(['template_name', 'status', 'sent_at']);
        });

        Schema::table('integration_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_notifications_enabled',
                'whatsapp_monthly_limit',
                'whatsapp_monthly_sent',
            ]);
        });
    }
};
```

- [ ] **Step 4: Aggiornare `WhatsAppMessage`**

Sostituire il contenuto di `app/Models/WhatsAppMessage.php` con:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id', 'appointment_id', 'wamid', 'idempotency_key', 'phone', 'phone_normalized',
    'wa_id', 'profile_name', 'direction', 'type', 'template_name', 'status', 'payload',
    'conversation_id', 'processed_at', 'sent_at', 'failed_at', 'error_code', 'error_message',
])]
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
            'sent_at'      => 'datetime',
            'failed_at'    => 'datetime',
        ];
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(WhatsAppMessageStatus::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeForAppointmentTemplate(Builder $query, int $appointmentId, string $templateName): Builder
    {
        return $query->where('appointment_id', $appointmentId)
                     ->where('template_name', $templateName);
    }

    public static function findByWamid(string $wamid): ?self
    {
        return self::where('wamid', $wamid)->first();
    }
}
```

- [ ] **Step 5: Aggiornare `IntegrationSetting`**

In `app/Models/IntegrationSetting.php`:

Nel blocco `#[Fillable([...])]` aggiungere in coda alla lista:
```php
    'whatsapp_notifications_enabled', 'whatsapp_monthly_limit', 'whatsapp_monthly_sent',
```

In `casts()` aggiungere:
```php
            'whatsapp_notifications_enabled' => 'boolean',
```

In fondo alla classe aggiungere:
```php
    public function hasWhatsAppNotificationsEnabled(): bool
    {
        return (bool) $this->whatsapp_notifications_enabled;
    }

    public function hasWhatsAppMonthlyCapacity(): bool
    {
        if ($this->whatsapp_monthly_limit === null) {
            return true;
        }

        return $this->whatsapp_monthly_sent < $this->whatsapp_monthly_limit;
    }
```

- [ ] **Step 6: Migrare ed eseguire i test**

```bash
docker-compose run --rm app php artisan migrate
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/NotificationModelsTest.php
```
Expected: 4 passed.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_03_120000_add_whatsapp_notification_fields.php \
        app/Models/WhatsAppMessage.php app/Models/IntegrationSetting.php \
        tests/Feature/WhatsApp/NotificationModelsTest.php
git commit -m "feat(whatsapp): add notification columns to whatsapp_messages and gating fields to integration_settings"
```

---

## Task 2: `WhatsAppService::sendTemplate` ritorna il wamid

**Files:**
- Modify: `app/Services/WhatsAppService.php:63-110`
- Modify: `tests/Feature/WhatsApp/WhatsAppServiceTest.php`

**Interfaces:**
- Produces: `WhatsAppService::sendTemplate(string $phone, string $templateName, string $language, string $category, array $params, int $businessId): ?string` — wamid on success, `null` on failure
- `sendTemplateDefault` resta per ora (bool), viene rimosso nel Task 4

- [ ] **Step 1: Aggiornare i test**

In `tests/Feature/WhatsApp/WhatsAppServiceTest.php` sostituire il test `'sends template with language and category'` con:

```php
it('sends template and returns wamid', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.2']]], 200)]);

    $result = app(WhatsAppService::class)->sendTemplate(
        '+393401234567',
        'appointment_confirmation',
        'it',
        'UTILITY',
        ['Mario Rossi', 'domani', '15:00'],
        app('current_business_id'),
    );

    expect($result)->toBe('wamid.2');
});

it('sendTemplate returns null on api error', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);

    $result = app(WhatsAppService::class)->sendTemplate(
        '+393401234567',
        'appointment_confirmation',
        'it',
        'UTILITY',
        ['Mario Rossi'],
        app('current_business_id'),
    );

    expect($result)->toBeNull();
});
```

- [ ] **Step 2: Verificare che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/WhatsAppServiceTest.php
```
Expected: FAIL — `sendTemplate` ritorna `true`, non `'wamid.2'`.

- [ ] **Step 3: Modificare `sendTemplate`**

In `app/Services/WhatsAppService.php` cambiare la firma e il finale di `sendTemplate`:

```php
    public function sendTemplate(string $phone, string $templateName, string $language, string $category, array $params, int $businessId): ?string
    {
        $setting = $this->getSettings($businessId);
        $token   = $setting->meta_whatsapp_token;
        $phoneId = $setting->meta_whatsapp_phone_id;

        if (! $token || ! $phoneId) {
            return null;
        }

        $response = Http::withToken($token)
            ->post($this->graphUrl($phoneId), [
                'messaging_product' => 'whatsapp',
                'to'                => $this->normalizePhoneForApi($phone),
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => $language],
                    'category'   => $category,
                    'components' => [
                        [
                            'type'       => 'body',
                            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params),
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('WhatsApp sendTemplate error', ['status' => $response->status(), 'body' => $response->json()]);
            return null;
        }

        return $response->json('messages.0.id', '');
    }
```

E in `sendTemplateDefault` cambiare l'ultima riga in:

```php
        return $this->sendTemplate($phone, $template, 'it', 'UTILITY', $parameters, $businessId) !== null;
```

- [ ] **Step 4: Eseguire i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/WhatsAppServiceTest.php
```
Expected: 4 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/WhatsAppService.php tests/Feature/WhatsApp/WhatsAppServiceTest.php
git commit -m "feat(whatsapp): sendTemplate returns wamid for delivery tracking"
```

---

## Task 3: `WhatsAppNotificationService` + `SendWhatsAppNotificationJob`

**Files:**
- Create: `app/Services/WhatsAppNotificationService.php`
- Create: `app/Jobs/SendWhatsAppNotificationJob.php`
- Test: `tests/Feature/WhatsApp/NotificationServiceTest.php`
- Test: `tests/Feature/WhatsApp/SendWhatsAppNotificationJobTest.php`

**Interfaces:**
- Consumes: helper gating di `IntegrationSetting` (Task 1), scope `forAppointmentTemplate` (Task 1), `WhatsAppService::sendTemplate(): ?string` (Task 2), `App\Services\PhoneNormalizer::normalize()` (esistente)
- Produces: `WhatsAppNotificationService::dispatchForAppointment(Appointment $appointment, string $templateName, array $parameters): ?WhatsAppMessage` — `null` se bloccato dai gate, record `status=queued` se dispatchato
- Produces: `WhatsAppNotificationService::appointmentParams(Appointment $appointment): array` (static — nome cliente, servizi, data, ora, nome staff)
- Produces: `SendWhatsAppNotificationJob(int $whatsappMessageId)` — aggiorna `status` a `sent`/`failed`, salva `wamid`, incrementa `whatsapp_monthly_sent`

- [ ] **Step 1: Scrivere i test del service**

```php
<?php
// tests/Feature/WhatsApp/NotificationServiceTest.php

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Queue::fake();
});

function makeNotifAppointment(Business $business, string $phone = '+393331234567', string $channel = 'whatsapp'): Appointment
{
    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);
    UserPreference::factory()->create([
        'user_id'              => $customer->id,
        'business_id'          => $business->id,
        'phone_number'         => $phone,
        'notification_channel' => $channel,
    ]);
    $service = Service::factory()->create(['business_id' => $business->id]);

    return Appointment::factory()->create([
        'business_id'    => $business->id,
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->addDays(2),
        'status'         => 'confirmed',
    ]);
}

function enableNotifSettings(Business $business, array $extra = []): IntegrationSetting
{
    return IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        array_merge([
            'whatsapp_notifications_enabled' => true,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => '1234567890',
        ], $extra),
    );
}

it('creates queued whatsapp_message and dispatches job', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);

    $appointment = makeNotifAppointment($business);
    $message     = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']);

    expect($message)->not->toBeNull();
    expect($message->status)->toBe('queued');
    expect($message->direction)->toBe('outbound');
    expect($message->type)->toBe('template');
    expect($message->template_name)->toBe('appointment_confirmed');
    expect($message->phone_normalized)->toBe('+393331234567');
    Queue::assertPushed(SendWhatsAppNotificationJob::class);
});

it('returns null when notifications not enabled', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business, ['whatsapp_notifications_enabled' => false]);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

it('returns null when meta credentials missing', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business, ['meta_whatsapp_token' => null]);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when monthly limit reached', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business, ['whatsapp_monthly_limit' => 10, 'whatsapp_monthly_sent' => 10]);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when customer channel is not whatsapp', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business, channel: 'email'), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when customer has no phone', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(makeNotifAppointment($business, phone: ''), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
});

it('returns null when same template already queued or sent for appointment', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);
    $appointment = makeNotifAppointment($business);

    $svc = app(WhatsAppNotificationService::class);
    expect($svc->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']))->not->toBeNull();
    expect($svc->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']))->toBeNull();
    expect(WhatsAppMessage::where('appointment_id', $appointment->id)->count())->toBe(1);
});

it('allows different templates for same appointment', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    enableNotifSettings($business);
    $appointment = makeNotifAppointment($business);

    $svc = app(WhatsAppNotificationService::class);
    $svc->dispatchForAppointment($appointment, 'appointment_confirmed', ['Mario']);

    expect($svc->dispatchForAppointment($appointment, 'appointment_reminder', ['Mario']))->not->toBeNull();
});
```

- [ ] **Step 2: Verificare che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/NotificationServiceTest.php
```
Expected: FAIL — classi non trovate.

- [ ] **Step 3: Creare `WhatsAppNotificationService`**

```php
<?php
// app/Services/WhatsAppNotificationService.php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Appointment;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    public function dispatchForAppointment(Appointment $appointment, string $templateName, array $parameters): ?WhatsAppMessage
    {
        $settings = IntegrationSetting::withoutGlobalScope('business')
            ->where('business_id', $appointment->business_id)
            ->first();

        if (! $settings?->hasWhatsAppNotificationsEnabled()) {
            return null;
        }

        if (empty($settings->meta_whatsapp_token) || empty($settings->meta_whatsapp_phone_id)) {
            return null;
        }

        if (! $settings->hasWhatsAppMonthlyCapacity()) {
            Log::info('WhatsApp monthly limit reached', ['business_id' => $appointment->business_id]);
            return null;
        }

        $prefs = $appointment->user?->preferences;

        if ($prefs?->notification_channel !== 'whatsapp' || empty($prefs->phone_number)) {
            return null;
        }

        $alreadySent = WhatsAppMessage::where('business_id', $appointment->business_id)
            ->forAppointmentTemplate($appointment->id, $templateName)
            ->whereIn('status', ['queued', 'sent'])
            ->exists();

        if ($alreadySent) {
            return null;
        }

        $message = WhatsAppMessage::create([
            'business_id'      => $appointment->business_id,
            'appointment_id'   => $appointment->id,
            'phone'            => $prefs->phone_number,
            'phone_normalized' => PhoneNormalizer::normalize($prefs->phone_number),
            'direction'        => 'outbound',
            'type'             => 'template',
            'template_name'    => $templateName,
            'payload'          => ['parameters' => $parameters],
            'status'           => 'queued',
        ]);

        SendWhatsAppNotificationJob::dispatch($message->id);

        return $message;
    }

    public static function appointmentParams(Appointment $appointment): array
    {
        return [
            $appointment->user->name,
            $appointment->services_label,
            $appointment->scheduled_date->format('d/m/Y'),
            $appointment->scheduled_date->format('H:i'),
            $appointment->staff->name,
        ];
    }
}
```

- [ ] **Step 4: Eseguire i test del service**

Nota: il job non esiste ancora, quindi creare prima lo scheletro del job (Step 5) se il dispatch fallisce; altrimenti procedere.

- [ ] **Step 5: Scrivere i test del job**

```php
<?php
// tests/Feature/WhatsApp/SendWhatsAppNotificationJobTest.php

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;

function makeQueuedNotification(Business $business, array $overrides = []): WhatsAppMessage
{
    return WhatsAppMessage::create(array_merge([
        'business_id'      => $business->id,
        'phone'            => '+393331234567',
        'phone_normalized' => '+393331234567',
        'direction'        => 'outbound',
        'type'             => 'template',
        'template_name'    => 'appointment_confirmed',
        'payload'          => ['parameters' => ['Mario']],
        'status'           => 'queued',
    ], $overrides));
}

function makeNotifJobSettings(Business $business): IntegrationSetting
{
    return IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        [
            'whatsapp_notifications_enabled' => true,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => '1234567890',
            'whatsapp_monthly_sent'          => 5,
        ],
    );
}

it('marks message as sent, stores wamid and increments counter on success', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ok1']]], 200)]);

    $business = Business::factory()->create();
    $settings = makeNotifJobSettings($business);
    $message  = makeQueuedNotification($business);

    (new SendWhatsAppNotificationJob($message->id))->handle(app(\App\Services\WhatsAppService::class));

    $message->refresh();
    expect($message->status)->toBe('sent');
    expect($message->wamid)->toBe('wamid.ok1');
    expect($message->sent_at)->not->toBeNull();
    expect($settings->fresh()->whatsapp_monthly_sent)->toBe(6);
});

it('marks message as failed on api error without incrementing counter', function () {
    Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 400)]);

    $business = Business::factory()->create();
    $settings = makeNotifJobSettings($business);
    $message  = makeQueuedNotification($business);

    (new SendWhatsAppNotificationJob($message->id))->handle(app(\App\Services\WhatsAppService::class));

    $message->refresh();
    expect($message->status)->toBe('failed');
    expect($message->failed_at)->not->toBeNull();
    expect($message->error_message)->not->toBeNull();
    expect($settings->fresh()->whatsapp_monthly_sent)->toBe(5);
});

it('skips message that is not queued', function () {
    Http::fake();

    $business = Business::factory()->create();
    makeNotifJobSettings($business);
    $message = makeQueuedNotification($business, ['status' => 'sent']);

    (new SendWhatsAppNotificationJob($message->id))->handle(app(\App\Services\WhatsAppService::class));

    Http::assertNothingSent();
});

it('failed hook marks message as failed', function () {
    $business = Business::factory()->create();
    $message  = makeQueuedNotification($business);

    (new SendWhatsAppNotificationJob($message->id))->failed(new \Exception('boom'));

    $message->refresh();
    expect($message->status)->toBe('failed');
    expect($message->error_message)->toBe('boom');
});
```

- [ ] **Step 6: Creare `SendWhatsAppNotificationJob`**

```php
<?php
// app/Jobs/SendWhatsAppNotificationJob.php

namespace App\Jobs;

use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int   $tries   = 3;
    public array $backoff = [30, 60, 300];
    public int   $timeout = 60;

    public function __construct(public readonly int $whatsappMessageId) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $message = WhatsAppMessage::find($this->whatsappMessageId);

        if (! $message || $message->status !== 'queued') {
            return;
        }

        $settings = IntegrationSetting::withoutGlobalScope('business')
            ->where('business_id', $message->business_id)
            ->first();

        $wamid = $whatsApp->sendTemplate(
            $message->phone_normalized,
            $message->template_name,
            $settings?->getWhatsAppAiLanguage() ?? 'it',
            'UTILITY',
            $message->payload['parameters'] ?? [],
            $message->business_id,
        );

        if ($wamid !== null) {
            $message->update([
                'status'  => 'sent',
                'wamid'   => $wamid !== '' ? $wamid : null,
                'sent_at' => now(),
            ]);

            IntegrationSetting::withoutGlobalScope('business')
                ->where('business_id', $message->business_id)
                ->increment('whatsapp_monthly_sent');
        } else {
            $message->update([
                'status'        => 'failed',
                'failed_at'     => now(),
                'error_message' => 'Meta API send failed',
            ]);

            Log::warning('WhatsApp notification send failed', [
                'message_id' => $message->id,
                'template'   => $message->template_name,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        WhatsAppMessage::where('id', $this->whatsappMessageId)->update([
            'status'        => 'failed',
            'failed_at'     => now(),
            'error_message' => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 7: Eseguire entrambi i file di test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/NotificationServiceTest.php tests/Feature/WhatsApp/SendWhatsAppNotificationJobTest.php
```
Expected: 12 passed.

- [ ] **Step 8: Commit**

```bash
git add app/Services/WhatsAppNotificationService.php app/Jobs/SendWhatsAppNotificationJob.php \
        tests/Feature/WhatsApp/NotificationServiceTest.php tests/Feature/WhatsApp/SendWhatsAppNotificationJobTest.php
git commit -m "feat(whatsapp): add WhatsAppNotificationService with gates and SendWhatsAppNotificationJob"
```

---

## Task 4: Trigger — eventi, reschedule, promemoria

**Files:**
- Create: `app/Listeners/SendWhatsAppAppointmentNotification.php`
- Modify: `app/Services/AppointmentRescheduleService.php:16-100`
- Modify: `app/Jobs/SendAppointmentReminder.php`
- Modify: `app/Services/WhatsAppService.php` — rimuovere `sendTemplateDefault`
- Modify: `tests/Feature/Jobs/NotificationJobsTest.php` — riscrivere i test reminder
- Test: `tests/Feature/WhatsApp/AppointmentWhatsAppIntegrationTest.php`

**Interfaces:**
- Consumes: `WhatsAppNotificationService::dispatchForAppointment()` e `::appointmentParams()` (Task 3)
- Template names fissi: `appointment_confirmed`, `appointment_cancelled`, `appointment_rescheduled`; il promemoria usa `IntegrationSetting::getMetaWhatsAppTemplate()` (configurabile dal tenant, default `appointment_reminder`)
- Nota: gli eventi esistono già — `AppointmentConfirmed($appointment)`, `AppointmentCancelled($appointment, ?string $reason)`

- [ ] **Step 1: Scrivere i test di integrazione**

```php
<?php
// tests/Feature/WhatsApp/AppointmentWhatsAppIntegrationTest.php

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Queue::fake();
});

function makeWaEventAppointment(bool $enabled = true): Appointment
{
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        [
            'whatsapp_notifications_enabled' => $enabled,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => '1234567890',
        ],
    );

    $staff    = User::factory()->create(['business_id' => $business->id]);
    $customer = User::factory()->create(['business_id' => $business->id]);
    UserPreference::factory()->create([
        'user_id'              => $customer->id,
        'business_id'          => $business->id,
        'phone_number'         => '+393331234567',
        'notification_channel' => 'whatsapp',
    ]);
    $service = Service::factory()->create(['business_id' => $business->id]);

    return Appointment::factory()->create([
        'business_id'    => $business->id,
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->addDays(2),
        'status'         => 'confirmed',
    ]);
}

it('sends appointment_confirmed whatsapp when AppointmentConfirmed fired', function () {
    $appointment = makeWaEventAppointment();

    AppointmentConfirmed::dispatch($appointment);

    Queue::assertPushed(SendWhatsAppNotificationJob::class);
    expect(WhatsAppMessage::forAppointmentTemplate($appointment->id, 'appointment_confirmed')->exists())->toBeTrue();
});

it('sends appointment_cancelled whatsapp when AppointmentCancelled fired', function () {
    $appointment = makeWaEventAppointment();

    AppointmentCancelled::dispatch($appointment, 'cliente ha disdetto');

    Queue::assertPushed(SendWhatsAppNotificationJob::class);
    expect(WhatsAppMessage::forAppointmentTemplate($appointment->id, 'appointment_cancelled')->exists())->toBeTrue();
});

it('does not send whatsapp when notifications disabled', function () {
    $appointment = makeWaEventAppointment(enabled: false);

    AppointmentConfirmed::dispatch($appointment);

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

it('does not crash on appointment without customer', function () {
    $appointment = makeWaEventAppointment();
    $appointment->updateQuietly(['user_id' => null]);

    AppointmentConfirmed::dispatch($appointment->fresh());

    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});
```

- [ ] **Step 2: Verificare che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/AppointmentWhatsAppIntegrationTest.php
```
Expected: FAIL — listener non esistente, nessun messaggio creato.

- [ ] **Step 3: Creare il listener**

```php
<?php
// app/Listeners/SendWhatsAppAppointmentNotification.php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Services\WhatsAppNotificationService;
use Illuminate\Events\Attributes\ListensTo;

#[ListensTo(AppointmentConfirmed::class)]
#[ListensTo(AppointmentCancelled::class)]
class SendWhatsAppAppointmentNotification
{
    public function __construct(private readonly WhatsAppNotificationService $whatsApp) {}

    public function handle(AppointmentConfirmed|AppointmentCancelled $event): void
    {
        $appointment = $event->appointment;

        if (! $appointment->user_id || ! $appointment->staff_id) {
            return;
        }

        $appointment->loadMissing('user.preferences', 'staff');

        $template = $event instanceof AppointmentConfirmed
            ? 'appointment_confirmed'
            : 'appointment_cancelled';

        $this->whatsApp->dispatchForAppointment(
            $appointment,
            $template,
            WhatsAppNotificationService::appointmentParams($appointment),
        );
    }
}
```

- [ ] **Step 4: Notifica reschedule in `AppointmentRescheduleService`**

In `app/Services/AppointmentRescheduleService.php`, il metodo `reschedule()` attualmente fa `return DB::transaction(...)`. Cambiarlo così (la notifica va DOPO la transaction, non dentro):

```php
    public function reschedule(
        Appointment $appointment,
        Carbon $newDateTime,
        User $actor,
    ): Appointment {
        $updated = DB::transaction(function () use ($appointment, $newDateTime, $actor): Appointment {
            // ... corpo esistente invariato ...
        });

        $this->notifyReschedule($updated);

        return $updated;
    }

    private function notifyReschedule(Appointment $appointment): void
    {
        if (! $appointment->user_id || ! $appointment->staff_id) {
            return;
        }

        $appointment->loadMissing('user.preferences', 'staff');

        app(WhatsAppNotificationService::class)->dispatchForAppointment(
            $appointment,
            'appointment_rescheduled',
            WhatsAppNotificationService::appointmentParams($appointment),
        );
    }
```

Aggiungere il `use App\Services\WhatsAppNotificationService;` non serve (stesso namespace `App\Services`).

Nota: l'idempotenza per `appointment_rescheduled` blocca una seconda notifica se l'appuntamento viene spostato due volte — accettabile per ora (il primo messaggio queued/sent vince); non gestire diversamente.

- [ ] **Step 5: Rifattorizzare `SendAppointmentReminder`**

Sostituire l'intero contenuto di `app/Jobs/SendAppointmentReminder.php` con:

```php
<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\AppointmentReminder;
use App\Models\IntegrationSetting;
use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly AppointmentReminder $reminder) {}

    public function handle(WhatsAppNotificationService $whatsApp): void
    {
        app()->instance('current_business_id', $this->reminder->business_id);

        if ($this->reminder->status === 'sent') {
            return;
        }

        $reminder    = $this->reminder->load('appointment.user.preferences', 'appointment.staff');
        $appointment = $reminder->appointment;

        $message = $whatsApp->dispatchForAppointment(
            $appointment,
            IntegrationSetting::getMetaWhatsAppTemplate(),
            WhatsAppNotificationService::appointmentParams($appointment),
        );

        if (! $message) {
            Mail::to($appointment->user->email)->send(new AppointmentReminderMail($appointment));
        }

        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        $this->reminder->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
```

Nota semantica: prima il fallback email scattava se l'invio sincrono falliva; ora scatta solo se i gate bloccano il dispatch (l'invio vero è async con 3 retry). Comportamento accettato dal design.

- [ ] **Step 6: Rimuovere `sendTemplateDefault` da `WhatsAppService`**

Eliminare l'intero metodo `sendTemplateDefault` (righe finali di `app/Services/WhatsAppService.php`) — non ha più chiamanti. Verificare:

```bash
grep -rn "sendTemplateDefault" app/ tests/
```
Expected: nessun risultato.

- [ ] **Step 7: Riscrivere i test reminder in `NotificationJobsTest`**

In `tests/Feature/Jobs/NotificationJobsTest.php` sostituire i quattro test `SendAppointmentReminder` che usano `WhatsAppService` (`sends email to customer`, `sends WhatsApp when user has whatsapp preference enabled`, `WhatsApp exception propagates`, `is a no-op when already sent`) con:

```php
use App\Jobs\SendWhatsAppNotificationJob;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Queue;

it('SendAppointmentReminder sends email when whatsapp gates block', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'type'           => 'email',
        'status'         => 'pending',
    ]);

    (new SendAppointmentReminder($reminder))->handle(app(WhatsAppNotificationService::class));

    Mail::assertSent(AppointmentReminderMail::class, fn ($mail) =>
        $mail->appointment->id === $appointment->id
    );
    expect($reminder->fresh()->status)->toBe('sent');
    expect($reminder->fresh()->sent_at)->not->toBeNull();
});

it('SendAppointmentReminder dispatches whatsapp job when enabled', function () {
    Queue::fake();

    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'              => $user->id,
        'notification_channel' => 'whatsapp',
        'phone_number'         => '+39123456789',
    ]);
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $appointment->business_id],
        [
            'whatsapp_notifications_enabled' => true,
            'meta_whatsapp_token'            => 'test-token',
            'meta_whatsapp_phone_id'         => 'test-phone-id',
        ],
    );

    (new SendAppointmentReminder($reminder))->handle(app(WhatsAppNotificationService::class));

    Queue::assertPushed(SendWhatsAppNotificationJob::class);
    Mail::assertNotSent(AppointmentReminderMail::class);
    expect($reminder->fresh()->status)->toBe('sent');
});

it('SendAppointmentReminder exception propagates to failed hook path', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'status'         => 'pending',
    ]);

    $mockWhatsApp = $this->mock(WhatsAppNotificationService::class);
    $mockWhatsApp->shouldReceive('dispatchForAppointment')
        ->andThrow(new \Exception('WhatsApp error'));

    expect(fn () => (new SendAppointmentReminder($reminder))->handle($mockWhatsApp))
        ->toThrow(\Exception::class);
});

it('SendAppointmentReminder is a no-op when already sent', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create([
        'appointment_id' => $appointment->id,
        'type'           => 'email',
        'status'         => 'sent',
    ]);

    (new SendAppointmentReminder($reminder))->handle(app(WhatsAppNotificationService::class));

    Mail::assertNothingSent();
});
```

Rimuovere `use App\Services\WhatsAppService;` dagli import se non più usato nel file.

- [ ] **Step 8: Eseguire i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/AppointmentWhatsAppIntegrationTest.php tests/Feature/Jobs/NotificationJobsTest.php
```
Expected: tutti passed (4 integrazione + 7 NotificationJobs).

- [ ] **Step 9: Commit**

```bash
git add app/Listeners/SendWhatsAppAppointmentNotification.php \
        app/Services/AppointmentRescheduleService.php \
        app/Jobs/SendAppointmentReminder.php \
        app/Services/WhatsAppService.php \
        tests/Feature/WhatsApp/AppointmentWhatsAppIntegrationTest.php \
        tests/Feature/Jobs/NotificationJobsTest.php
git commit -m "feat(whatsapp): wire confirmed/cancelled/rescheduled/reminder notifications through WhatsAppNotificationService"
```

---

## Task 5: Reset contatori mensili

**Files:**
- Create: `app/Console/Commands/ResetMonthlyWhatsAppCounters.php`
- Modify: `routes/console.php`

**Interfaces:**
- Produces: comando Artisan `whatsapp:reset-monthly-counters`

- [ ] **Step 1: Creare il comando**

```php
<?php
// app/Console/Commands/ResetMonthlyWhatsAppCounters.php

namespace App\Console\Commands;

use App\Models\IntegrationSetting;
use Illuminate\Console\Command;

class ResetMonthlyWhatsAppCounters extends Command
{
    protected $signature   = 'whatsapp:reset-monthly-counters';
    protected $description = 'Reset monthly WhatsApp notification counters for all businesses';

    public function handle(): void
    {
        $count = IntegrationSetting::withoutGlobalScope('business')
            ->where('whatsapp_monthly_sent', '>', 0)
            ->update(['whatsapp_monthly_sent' => 0]);

        $this->info("Reset counters for {$count} businesses.");
    }
}
```

- [ ] **Step 2: Aggiungere lo schedule**

In `routes/console.php` aggiungere in fondo:

```php
Schedule::command('whatsapp:reset-monthly-counters')
    ->monthlyOn(1, '00:00')
    ->description('Reset monthly WhatsApp notification counters');
```

- [ ] **Step 3: Verificare manualmente**

```bash
docker-compose run --rm app php artisan whatsapp:reset-monthly-counters
```
Expected: `Reset counters for 0 businesses.`

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/ResetMonthlyWhatsAppCounters.php routes/console.php
git commit -m "feat(whatsapp): add monthly counter reset command scheduled on 1st of each month"
```

---

## Task 6: UI — gating superadmin + stato tenant

**Files:**
- Modify: `app/Filament/SuperAdmin/Resources/BusinessResource.php` — nuova action tabella
- Modify: `app/Filament/Pages/IntegrationSettings.php` — placeholder stato
- Test: `tests/Feature/Filament/WhatsAppNotificationsAdminTest.php`

**Interfaces:**
- Consumes: `Business::integrationSetting(): HasOne` (esistente), colonne gating (Task 1)
- Il superadmin edita `whatsapp_notifications_enabled` e `whatsapp_monthly_limit`; il tenant li vede in sola lettura
- ATTENZIONE: NON aggiungere questi campi al form del tenant — `IntegrationSettings::save()` fa `update($data)` sullo state del form, quindi qualsiasi campo editabile diventerebbe scrivibile dal tenant

- [ ] **Step 1: Scrivere i test**

```php
<?php
// tests/Feature/Filament/WhatsAppNotificationsAdminTest.php

use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\ListBusinesses;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
});

it('superadmin can enable whatsapp notifications with monthly limit', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $business = Business::factory()->create();

    Livewire::test(ListBusinesses::class)
        ->callTableAction('whatsappNotifications', $business, data: [
            'whatsapp_notifications_enabled' => true,
            'whatsapp_monthly_limit'         => 300,
        ])
        ->assertHasNoTableActionErrors();

    $setting = IntegrationSetting::withoutGlobalScope('business')
        ->where('business_id', $business->id)
        ->first();

    expect($setting->whatsapp_notifications_enabled)->toBeTrue();
    expect($setting->whatsapp_monthly_limit)->toBe(300);
});

it('tenant admin sees notification status in integration settings', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    IntegrationSetting::current()->update([
        'whatsapp_notifications_enabled' => true,
        'whatsapp_monthly_limit'         => 300,
        'whatsapp_monthly_sent'          => 12,
    ]);

    $admin = User::factory()->create(['business_id' => $business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($business->id);

    $this->actingAs($admin)
        ->get("/admin/{$business->subdomain}/integration-settings")
        ->assertOk()
        ->assertSee('Notifiche WhatsApp')
        ->assertSee('12 / 300');
});
```

- [ ] **Step 2: Verificare che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Filament/WhatsAppNotificationsAdminTest.php
```
Expected: FAIL — action non esistente.

- [ ] **Step 3: Aggiungere l'action su `BusinessResource`**

In `app/Filament/SuperAdmin/Resources/BusinessResource.php`, dentro `->actions([...])`, prima di `EditAction::make()`, aggiungere:

```php
                Action::make('whatsappNotifications')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->fillForm(function (Business $record): array {
                        $setting = $record->integrationSetting;

                        return [
                            'whatsapp_notifications_enabled' => (bool) $setting?->whatsapp_notifications_enabled,
                            'whatsapp_monthly_limit'         => $setting?->whatsapp_monthly_limit,
                        ];
                    })
                    ->form([
                        \Filament\Forms\Components\Toggle::make('whatsapp_notifications_enabled')
                            ->label('Notifiche WhatsApp abilitate'),
                        \Filament\Forms\Components\TextInput::make('whatsapp_monthly_limit')
                            ->label('Limite messaggi mensile')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('Illimitato'),
                        \Filament\Forms\Components\Placeholder::make('sent_info')
                            ->label('Inviati questo mese')
                            ->content(fn (Business $record): string => (string) ($record->integrationSetting?->whatsapp_monthly_sent ?? 0)),
                    ])
                    ->action(function (Business $record, array $data): void {
                        $record->integrationSetting()->updateOrCreate([], [
                            'whatsapp_notifications_enabled' => (bool) ($data['whatsapp_notifications_enabled'] ?? false),
                            'whatsapp_monthly_limit'         => filled($data['whatsapp_monthly_limit'] ?? null)
                                ? (int) $data['whatsapp_monthly_limit']
                                : null,
                        ]);

                        Notification::make()
                            ->title('Impostazioni WhatsApp aggiornate.')
                            ->success()
                            ->send();
                    }),
```

- [ ] **Step 4: Placeholder stato nella pagina tenant**

In `app/Filament/Pages/IntegrationSettings.php`, nella sezione di stato WhatsApp esistente (quella con `status_last_outbound` / `status_last_inbound` / `status_last_error`), aggiungere PRIMA di `status_last_outbound`:

```php
                        Placeholder::make('status_notifications_enabled')
                            ->label('Notifiche WhatsApp (gestite dalla piattaforma)')
                            ->content(fn () => IntegrationSetting::current()->whatsapp_notifications_enabled ? 'abilitate' : 'non abilitate'),

                        Placeholder::make('status_monthly_usage')
                            ->label('Messaggi notifica questo mese')
                            ->content(function () {
                                $s     = IntegrationSetting::current();
                                $limit = $s->whatsapp_monthly_limit ? (string) $s->whatsapp_monthly_limit : '∞';

                                return ($s->whatsapp_monthly_sent ?? 0) . ' / ' . $limit;
                            }),
```

Se la sezione di stato non contiene già la stringa "Notifiche WhatsApp", verificare che il label del primo placeholder la contenga (il test asserisce `assertSee('Notifiche WhatsApp')`).

- [ ] **Step 5: Eseguire i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Filament/WhatsAppNotificationsAdminTest.php
```
Expected: 2 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/SuperAdmin/Resources/BusinessResource.php \
        app/Filament/Pages/IntegrationSettings.php \
        tests/Feature/Filament/WhatsAppNotificationsAdminTest.php
git commit -m "feat(whatsapp): superadmin gating action and tenant notification status UI"
```

---

## Task 7: Suite completa

- [ ] **Step 1: Eseguire l'intera suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```
Expected: tutti i test passano.

- [ ] **Step 2: Failure noti da controllare se emergono**

- `tests/Feature/Jobs/NotificationJobsTest.php` — i vecchi test reminder mockavano `WhatsAppService::sendTemplate` ma il job (già sul branch) chiama `sendTemplateDefault`: potrebbero essere GIÀ rossi prima di questo piano. Il Task 4 li riscrive; qualsiasi residuo va allineato alla nuova firma `handle(WhatsAppNotificationService $whatsApp)`.
- `tests/Feature/WhatsApp/WebhookControllerTest.php` e `ProcessWhatsAppMessageJobTest.php` — non toccati dal piano; se falliscono, verificare che le colonne nuove su `whatsapp_messages` siano tutte nullable (lo sono).
- Test che creano `IntegrationSetting` — il default `whatsapp_notifications_enabled = false` non deve cambiare comportamenti esistenti.

- [ ] **Step 3: Commit finale (solo se sono servite fix)**

```bash
git add -A && git commit -m "fix: resolve test suite regressions after WhatsApp notifications integration"
```

---

## Self-Review

### Spec coverage check

| Requisito | Task |
|-----------|------|
| Feature premium per-business, default off | Task 1 (default false) + Task 6 (solo superadmin) |
| Credenziali uniche (no duplicazione) | riuso `IntegrationSetting` — nessun nuovo store |
| Log messaggi con stato | Task 1 (colonne su `whatsapp_messages`) + Task 3 (job) |
| Delivery status | gratis via webhook esistente + `wamid` (Task 3 salva wamid) |
| Limite mensile + contatore | Task 1 + Task 3 (increment) |
| Non invia se disabled/limite/no telefono/canale ≠ whatsapp | Task 3 (gate) |
| Idempotenza appointment+template | Task 3 |
| Invio async con retry | Task 3 (job, tries 3, backoff) |
| Trigger conferma | Task 4 (listener) |
| Trigger annullamento | Task 4 (listener) |
| Trigger riprogrammazione | Task 4 (reschedule service) |
| Promemoria 24h con fallback email | Task 4 (reminder refactor) |
| Reset mensile contatori | Task 5 |
| UI superadmin enable/limit | Task 6 |
| UI tenant stato + contatore (sola lettura) | Task 6 |
| Nessuna doppia tabella `whatsapp_messages` | risolto — colonne additive sulla tabella del branch |
| Nessun doppio PhoneNormalizer | risolto — riuso `App\Services\PhoneNormalizer` |
| Rimozione Twilio | fuori scope (cleanup separato) |
