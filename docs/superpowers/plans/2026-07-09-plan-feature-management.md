# Plan Feature Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded `config/plans.php` feature map with a DB-backed `plan_features` table managed by a superadmin Filament page, and gate all six features at their relevant UI and backend service entry points.

**Architecture:** A `PlanFeature` model stores each feature's key, label, and `min_plan` ('base'|'plus'|null=disabled). `PlanFeatureGate` reads from DB with a `'__disabled__'` sentinel so null/missing features are cached and don't cause repeated queries. Cache is flushed automatically via model `booted()` hooks. A superadmin page provides inline editing with confirmation for sensitive features.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, MySQL 8, Redis (via `Cache::remember`/`Cache::forget`)

## Global Constraints

- All commands inside Docker: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest`
- PHP 8 attribute syntax: `#[Fillable([...])]` — not `$fillable` property
- Use `protected function casts(): array` — not `$casts` property
- Model factory docblock: `/** @use HasFactory<\Database\Factories\FooFactory> */` on the `use HasFactory` line
- Factory class docblock: `/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Foo> */`
- `RefreshDatabase` is global for Feature tests (set in `tests/Pest.php`) — no need to declare it per file
- SuperAdmin pages: namespace `App\Filament\SuperAdmin\Pages`, `canAccess()` checks `hasRole('super_admin')`
- SuperAdmin page views: follow `PlatformLogsPage` pattern — use `protected string $view = 'filament.pages.<name>'` (no `superadmin` in path — that's how the project does it)
- Cache key pattern: `plan_feature_{key}` (e.g. `plan_feature_whatsapp_ai`)
- Sentinel for disabled/missing features: the string `'__disabled__'` (never store this in DB — it's runtime only)
- Feature keys: `whatsapp_notifications`, `whatsapp_ai`, `google_calendar`, `online_payments`, `loyalty_program`, `waitlist`
- Initial `min_plan` seed: `whatsapp_notifications` → `'plus'`, `whatsapp_ai` → `'plus'`; all others → `'base'`
- Branch: `feature/whatsapp-ai-booking`

---

### Task 1: Migration + PlanFeature model + factory

**Files:**
- Create: `database/migrations/2026_07_09_NNNNNN_create_plan_features_table.php`
- Create: `app/Models/PlanFeature.php`
- Create: `database/factories/PlanFeatureFactory.php`
- Test: `tests/Feature/Models/PlanFeatureTest.php`

**Interfaces:**
- Produces: `PlanFeature::where('key', $feature)->value('min_plan')` — returns `?string` ('base'|'plus'|null)
- Produces: `PlanFeature` model with fillable `key`, `label`, `description`, `min_plan`
- Produces: `PlanFeatureFactory` for use in tests

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Models/PlanFeatureTest.php

use App\Models\PlanFeature;

it('seeds all six features in the migration', function () {
    $keys = PlanFeature::pluck('key')->sort()->values()->all();
    expect($keys)->toBe([
        'google_calendar',
        'loyalty_program',
        'online_payments',
        'waitlist',
        'whatsapp_ai',
        'whatsapp_notifications',
    ]);
});

it('whatsapp_ai is seeded as plus', function () {
    expect(PlanFeature::where('key', 'whatsapp_ai')->value('min_plan'))->toBe('plus');
});

it('whatsapp_notifications is seeded as plus', function () {
    expect(PlanFeature::where('key', 'whatsapp_notifications')->value('min_plan'))->toBe('plus');
});

it('google_calendar is seeded as base', function () {
    expect(PlanFeature::where('key', 'google_calendar')->value('min_plan'))->toBe('base');
});

it('can update min_plan', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => 'plus']);
    expect(PlanFeature::where('key', 'waitlist')->value('min_plan'))->toBe('plus');
});

it('factory creates a valid PlanFeature', function () {
    $feature = PlanFeature::factory()->create(['key' => 'test_feature', 'min_plan' => 'base']);
    expect($feature->key)->toBe('test_feature');
    expect($feature->min_plan)->toBe('base');
});
```

- [ ] **Step 2: Run test to confirm failure**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/PlanFeatureTest.php
```

Expected: FAIL (class not found)

- [ ] **Step 3: Create the migration**

```bash
docker-compose run --rm --no-deps app php artisan make:migration create_plan_features_table
```

Fill the generated file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->string('min_plan')->nullable(); // 'base' | 'plus' | null = disabled
            $table->timestamps();
        });

        DB::table('plan_features')->insert([
            ['key' => 'whatsapp_notifications', 'label' => 'Notifiche WhatsApp',     'description' => 'Promemoria appuntamenti via WhatsApp (Meta API)',    'min_plan' => 'plus',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'whatsapp_ai',            'label' => 'Assistente AI WhatsApp', 'description' => 'Bot AI per prenotazioni e cancellazioni via chat',  'min_plan' => 'plus',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'google_calendar',        'label' => 'Google Calendar',        'description' => 'Sincronizzazione appuntamenti con Google Calendar', 'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'online_payments',        'label' => 'Pagamenti online',       'description' => 'Pagamenti via Stripe Connect',                      'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'loyalty_program',        'label' => 'Programma fedeltà',      'description' => 'Punti fedeltà e sconti per clienti ricorrenti',     'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'waitlist',               'label' => 'Lista d\'attesa',        'description' => 'Gestione lista d\'attesa per slot esauriti',        'min_plan' => 'base',  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php
// app/Models/PlanFeature.php

namespace App\Models;

use Database\Factories\PlanFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'label', 'description', 'min_plan'])]
class PlanFeature extends Model
{
    /** @use HasFactory<PlanFeatureFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn (self $feature) => Cache::forget("plan_feature_{$feature->key}"));
        static::deleted(fn (self $feature) => Cache::forget("plan_feature_{$feature->key}"));
    }
}
```

- [ ] **Step 5: Create the factory**

```php
<?php
// database/factories/PlanFeatureFactory.php

namespace Database\Factories;

use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanFeature> */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    public function definition(): array
    {
        return [
            'key'         => $this->faker->unique()->slug(2),
            'label'       => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'min_plan'    => 'base',
        ];
    }
}
```

- [ ] **Step 6: Run the migration**

```bash
docker-compose run --rm app php artisan migrate
```

- [ ] **Step 7: Run tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/PlanFeatureTest.php
```

Expected: 6 passed

- [ ] **Step 8: Commit**

```bash
git add database/migrations/ app/Models/PlanFeature.php database/factories/PlanFeatureFactory.php tests/Feature/Models/PlanFeatureTest.php
git commit -m "feat: add plan_features table with seeded feature definitions and factory"
```

---

### Task 2: PlanFeatureGate — DB-backed lookup + config cleanup

**Files:**
- Modify: `app/Services/PlanFeatureGate.php`
- Modify: `config/plans.php` (remove `features` key)
- Modify: `tests/Unit/Models/BusinessPlanTest.php` (remove canUseFeature tests — now in feature test)
- Create: `tests/Feature/Services/PlanFeatureGateTest.php`

**Interfaces:**
- Consumes: `PlanFeature::where('key', $feature)->value('min_plan')` from Task 1
- Consumes: `Cache::forget("plan_feature_{$feature->key}")` is called by model booted() — no need to call it in the gate
- Consumes: `Business::effectivePlan()` — returns `'base'`|`'plus'`
- Produces: `PlanFeatureGate::allows(Business $business, string $feature): bool`

Gate logic:
- `'base'` → true for any plan
- `'plus'` → true only if `effectivePlan() === 'plus'`
- `null` (DB), feature not found, or invalid value → cached as `'__disabled__'` → false

- [ ] **Step 1: Write the failing feature tests**

```php
<?php
// tests/Feature/Services/PlanFeatureGateTest.php

use App\Models\Business;
use App\Models\PlanFeature;
use App\Services\PlanFeatureGate;
use Illuminate\Support\Facades\Cache;

it('allows base feature for base-plan business', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'base']);
    $business = Business::factory()->create(['trial_ends_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'google_calendar'))->toBeTrue();
});

it('allows base feature for plus-plan business', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'base']);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'google_calendar'))->toBeTrue();
});

it('denies plus feature for base-plan business', function () {
    PlanFeature::where('key', 'whatsapp_ai')->update(['min_plan' => 'plus']);
    $business = Business::factory()->create(['trial_ends_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'whatsapp_ai'))->toBeFalse();
});

it('allows plus feature for plus-plan business', function () {
    PlanFeature::where('key', 'whatsapp_ai')->update(['min_plan' => 'plus']);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'whatsapp_ai'))->toBeTrue();
});

it('allows plus feature during trial', function () {
    PlanFeature::where('key', 'whatsapp_ai')->update(['min_plan' => 'plus']);
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);

    expect(app(PlanFeatureGate::class)->allows($business, 'whatsapp_ai'))->toBeTrue();
});

it('denies disabled feature (null min_plan)', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => null]);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'waitlist'))->toBeFalse();
});

it('denies unknown feature', function () {
    $business = Business::factory()->create();
    expect(app(PlanFeatureGate::class)->allows($business, 'nonexistent'))->toBeFalse();
});

it('denies feature with invalid min_plan value in DB', function () {
    PlanFeature::factory()->create(['key' => 'bad_feature', 'min_plan' => 'premium']);
    $business = Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null]);

    expect(app(PlanFeatureGate::class)->allows($business, 'bad_feature'))->toBeFalse();
});

it('caches the result and does not re-query on second call', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'base']);
    $business = Business::factory()->create(['trial_ends_at' => null]);
    $gate     = app(PlanFeatureGate::class);

    $gate->allows($business, 'google_calendar'); // warm cache
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'plus']); // change in DB

    // still reads cached 'base' → returns true for base-plan business
    expect($gate->allows($business, 'google_calendar'))->toBeTrue();
});

it('reflects DB change after model save flushes cache automatically', function () {
    $feature  = PlanFeature::where('key', 'google_calendar')->first();
    $business = Business::factory()->create(['trial_ends_at' => null]);
    $gate     = app(PlanFeatureGate::class);

    $gate->allows($business, 'google_calendar'); // warm cache with 'base'

    $feature->update(['min_plan' => 'plus']); // model saved() hook flushes cache

    expect($gate->allows($business, 'google_calendar'))->toBeFalse();
});

it('caches disabled/null features and does not re-query', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => null]);
    $business = Business::factory()->create();
    $gate     = app(PlanFeatureGate::class);

    $gate->allows($business, 'waitlist'); // caches as __disabled__
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => 'base']); // change in DB

    // cache still returns false (not re-queried)
    expect($gate->allows($business, 'waitlist'))->toBeFalse();
});
```

- [ ] **Step 2: Confirm tests fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PlanFeatureGateTest.php
```

Expected: FAIL

- [ ] **Step 3: Update PlanFeatureGate**

```php
<?php
// app/Services/PlanFeatureGate.php

namespace App\Services;

use App\Models\Business;
use App\Models\PlanFeature;
use Illuminate\Support\Facades\Cache;

class PlanFeatureGate
{
    private const DISABLED = '__disabled__';

    public function allows(Business $business, string $feature): bool
    {
        $minPlan = Cache::remember("plan_feature_{$feature}", 60, function () use ($feature) {
            return PlanFeature::where('key', $feature)->value('min_plan') ?? self::DISABLED;
        });

        return match ($minPlan) {
            'base'         => true,
            'plus'         => $business->effectivePlan() === 'plus',
            self::DISABLED => false,
            default        => false,
        };
    }
}
```

- [ ] **Step 4: Remove the `features` key from config/plans.php**

The final `config/plans.php` (only display-purpose strings remain — no feature gate logic):

```php
<?php

return [
    'base' => [
        'price_id' => env('STRIPE_PRICE_ID_BASE', env('STRIPE_PRICE_ID')),
        'label'    => 'Base',
        'features' => [
            'Gestione appuntamenti',
            'Notifiche email',
            'Portale clienti',
            'Google Calendar sync',
        ],
    ],
    'plus' => [
        'price_id' => env('STRIPE_PRICE_ID_PLUS'),
        'label'    => 'Plus',
        'features' => [
            'Tutto il piano Base',
            'Assistente AI WhatsApp',
            'Prenotazioni via WhatsApp',
            'Cancellazioni via WhatsApp',
        ],
    ],
];
```

- [ ] **Step 5: Remove canUseFeature tests from BusinessPlanTest**

In `tests/Unit/Models/BusinessPlanTest.php`, delete the four `canUseFeature` tests (they are now covered by the feature test above). The tests to remove are titled: `trial business can use whatsapp_ai`, `base-plan business cannot use whatsapp_ai`, `unknown feature is denied`, `plus override business can use whatsapp_ai`. Also remove the `use App\Services\PlanFeatureGate;` import if it's no longer referenced after deletion.

- [ ] **Step 6: Run tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PlanFeatureGateTest.php tests/Unit/Models/BusinessPlanTest.php
```

Expected: all pass

- [ ] **Step 7: Commit**

```bash
git add app/Services/PlanFeatureGate.php config/plans.php tests/Feature/Services/PlanFeatureGateTest.php tests/Unit/Models/BusinessPlanTest.php
git commit -m "feat: PlanFeatureGate reads from DB (cached 60s, sentinel for null/missing)"
```

---

### Task 3: SuperAdmin — plan feature management page

**Files:**
- Create: `app/Filament/SuperAdmin/Pages/PlanFeaturesPage.php`
- Create: `resources/views/filament/pages/plan-features.blade.php`
- Create: `tests/Feature/SuperAdmin/PlanFeaturesPageTest.php`

**Interfaces:**
- Consumes: `PlanFeature` model from Task 1 (cache flush is automatic via `booted()`)
- Produces: superadmin page at `/superadmin/piani-feature`

**Pattern reference:** Follow `PlatformLogsPage` exactly — `Page implements HasTable`, `use InteractsWithTable`, view references `{{ $this->table }}`. Do NOT add `HasSchemas`/`InteractsWithSchemas` — the existing PlatformLogsPage works without them and that's the project pattern.

The `whatsapp_ai` and `whatsapp_notifications` options display a warning hint in the select label (cost-sensitive) and require `requiresConfirmation()`. The model `booted()` hook handles cache flush automatically — do NOT add `Cache::forget()` inside the action.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SuperAdmin/PlanFeaturesPageTest.php

use App\Models\PlanFeature;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function makeSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    return $user;
}

it('super_admin can see the plan features page', function () {
    $this->actingAs(makeSuperAdmin());

    livewire(\App\Filament\SuperAdmin\Pages\PlanFeaturesPage::class)
        ->assertSuccessful();
});

it('page shows all six feature labels', function () {
    $this->actingAs(makeSuperAdmin());

    livewire(\App\Filament\SuperAdmin\Pages\PlanFeaturesPage::class)
        ->assertSee('Assistente AI WhatsApp')
        ->assertSee('Google Calendar')
        ->assertSee('Lista d\'attesa');
});

it('non-superadmin cannot access', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get('/superadmin/piani-feature')->assertForbidden();
});

it('updating min_plan via action updates DB and flushes cache', function () {
    $this->actingAs(makeSuperAdmin());

    Cache::put('plan_feature_waitlist', 'base', 60);
    $feature = PlanFeature::where('key', 'waitlist')->first();

    livewire(\App\Filament\SuperAdmin\Pages\PlanFeaturesPage::class)
        ->callTableAction('edit_min_plan', $feature, data: ['min_plan' => 'plus'])
        ->assertSuccessful();

    expect(PlanFeature::where('key', 'waitlist')->value('min_plan'))->toBe('plus');
    expect(Cache::has('plan_feature_waitlist'))->toBeFalse(); // flushed by model booted()
});
```

- [ ] **Step 2: Confirm failure**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/SuperAdmin/PlanFeaturesPageTest.php
```

Expected: FAIL (class not found)

- [ ] **Step 3: Create the page**

```php
<?php
// app/Filament/SuperAdmin/Pages/PlanFeaturesPage.php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\PlanFeature;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class PlanFeaturesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Feature dei piani';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'piani-feature';

    protected string $view = 'filament.pages.plan-features';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        $costSensitive = ['whatsapp_ai', 'whatsapp_notifications'];

        return $table
            ->query(PlanFeature::query()->orderBy('key'))
            ->columns([
                TextColumn::make('label')
                    ->label('Feature')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Descrizione')
                    ->wrap(),

                TextColumn::make('min_plan')
                    ->label('Piano minimo')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'base'  => 'success',
                        'plus'  => 'primary',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'base'  => 'Base',
                        'plus'  => 'Plus',
                        default => 'Disabilitata',
                    }),
            ])
            ->actions([
                TableAction::make('edit_min_plan')
                    ->label('Modifica')
                    ->icon('heroicon-o-pencil')
                    ->requiresConfirmation(fn (PlanFeature $record) => in_array($record->key, $costSensitive))
                    ->modalHeading('Attenzione: feature con costo variabile')
                    ->modalDescription('Questa feature genera costi (AI/WhatsApp). Assicurati di volerla rendere disponibile nel piano selezionato.')
                    ->form([
                        Select::make('min_plan')
                            ->label('Piano minimo')
                            ->options([
                                'base' => 'Base (tutti i piani)',
                                'plus' => 'Plus (solo piano Plus)',
                                ''     => 'Disabilitata',
                            ])
                            ->required(false),
                    ])
                    ->fillForm(fn (PlanFeature $record) => ['min_plan' => $record->min_plan ?? ''])
                    ->action(function (PlanFeature $record, array $data): void {
                        $record->update(['min_plan' => $data['min_plan'] === '' ? null : $data['min_plan']]);
                        // Cache flush is automatic via PlanFeature::booted() saved hook

                        Notification::make()
                            ->title("Feature '{$record->label}' aggiornata.")
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false);
    }
}
```

- [ ] **Step 4: Create the view**

```blade
{{-- resources/views/filament/pages/plan-features.blade.php --}}
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 5: Run tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/SuperAdmin/PlanFeaturesPageTest.php
```

Expected: 4 passed

- [ ] **Step 6: Commit**

```bash
git add app/Filament/SuperAdmin/Pages/PlanFeaturesPage.php resources/views/filament/pages/plan-features.blade.php tests/Feature/SuperAdmin/PlanFeaturesPageTest.php
git commit -m "feat: superadmin page for managing plan features"
```

---

### Task 4: Gate feature entry points — UI and backend services

**Files:**
- Modify: `app/Services/WhatsAppNotificationService.php` (gate `whatsapp_notifications`)
- Modify: `app/Models/Business.php` (gate `online_payments` in `canAcceptOnlinePayments`)
- Modify: `app/Jobs/SyncGoogleCalendar.php` (gate `google_calendar`)
- Modify: `app/Services/LoyaltyService.php` (gate `loyalty_program` in `accrue`)
- Modify: `app/Http/Controllers/Portal/WaitlistController.php` (gate `waitlist` in `store`)
- Modify: `app/Filament/Pages/IntegrationSettings.php` (gate `whatsapp_notifications` section + `google_calendar` section in UI)
- Modify: `app/Filament/Pages/SystemSettings.php` (gate `loyalty_program` section in UI)
- Modify: `app/Filament/Resources/WaitlistEntryResource.php` (gate `waitlist` in `canAccess`)
- Create: `tests/Feature/Services/FeatureGateIntegrationTest.php`

**Interfaces:**
- Consumes: `Business::canUseFeature(string $feature): bool` — already exists on Business model
- The gate returns false for features outside the business plan, causing: service methods to return early/null, controllers to abort, `canAccess()` to return false

**Gate behavior by entry point:**
- `WhatsAppNotificationService::dispatchForAppointment()` → return null early if `!canUseFeature('whatsapp_notifications')`
- `Business::canAcceptOnlinePayments()` → return false early if `!canUseFeature('online_payments')`
- `SyncGoogleCalendar::handle()` → return early if `!canUseFeature('google_calendar')`
- `LoyaltyService::accrue()` → return early if `!canUseFeature('loyalty_program')`
- `WaitlistController::store()` → abort(403) if `!canUseFeature('waitlist')`
- `WaitlistEntryResource::canAccess()` → return false if `!canUseFeature('waitlist')`
- UI sections in IntegrationSettings/SystemSettings → disabled + upgrade notice (same as existing `whatsapp_ai` pattern)

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Services/FeatureGateIntegrationTest.php

use App\Jobs\SendWhatsAppNotificationJob;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\PlanFeature;
use App\Models\Service;
use App\Models\StripeConnectAccount;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\LoyaltyService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Queue::fake();
});

function gatingBusiness(string $plan = 'base'): Business
{
    return $plan === 'plus'
        ? Business::factory()->create(['plan_override' => 'plus', 'plan_override_expires_at' => null])
        : Business::factory()->create(['trial_ends_at' => null]);
}

function gatingAppointment(Business $business): Appointment
{
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

function enableWhatsAppSettings(Business $business): void
{
    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        ['whatsapp_notifications_enabled' => true, 'meta_whatsapp_token' => 'tok', 'meta_whatsapp_phone_id' => '123'],
    );
}

// --- whatsapp_notifications ---

it('whatsapp_notifications: dispatches job when feature allows (plus plan + plus feature)', function () {
    PlanFeature::where('key', 'whatsapp_notifications')->update(['min_plan' => 'plus']);
    $business = gatingBusiness('plus');
    app()->instance('current_business_id', $business->id);
    enableWhatsAppSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(gatingAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->not->toBeNull();
    Queue::assertPushed(SendWhatsAppNotificationJob::class);
});

it('whatsapp_notifications: returns null when feature is plus and business is base plan', function () {
    PlanFeature::where('key', 'whatsapp_notifications')->update(['min_plan' => 'plus']);
    $business = gatingBusiness('base');
    app()->instance('current_business_id', $business->id);
    enableWhatsAppSettings($business);

    $result = app(WhatsAppNotificationService::class)
        ->dispatchForAppointment(gatingAppointment($business), 'appointment_confirmed', ['Mario']);

    expect($result)->toBeNull();
    Queue::assertNotPushed(SendWhatsAppNotificationJob::class);
});

// --- online_payments ---

it('canAcceptOnlinePayments returns false when online_payments feature is plus and business is base', function () {
    PlanFeature::where('key', 'online_payments')->update(['min_plan' => 'plus']);
    $business = gatingBusiness('base');

    expect($business->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments checks Stripe Connect when feature allows', function () {
    PlanFeature::where('key', 'online_payments')->update(['min_plan' => 'base']);
    $business = gatingBusiness('base');

    // No Stripe Connect → false from Stripe check (not from plan gate)
    expect($business->canAcceptOnlinePayments())->toBeFalse();
});

// --- google_calendar ---

it('SyncGoogleCalendar returns early when google_calendar feature is plus and business is base', function () {
    PlanFeature::where('key', 'google_calendar')->update(['min_plan' => 'plus']);
    $business    = gatingBusiness('base');
    $appointment = gatingAppointment($business);

    IntegrationSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        ['google_calendar_id' => 'cal@group.calendar.google.com'],
    );

    // Should return without calling GoogleCalendarService (no exception)
    (new SyncGoogleCalendar($appointment, 'create'))->handle(
        app(\App\Services\GoogleCalendarService::class)
    );

    // No google_event_id set = job returned early
    expect($appointment->fresh()->google_event_id)->toBeNull();
});

// --- loyalty_program ---

it('LoyaltyService::accrue does nothing when loyalty_program feature is plus and business is base', function () {
    PlanFeature::where('key', 'loyalty_program')->update(['min_plan' => 'plus']);
    $business    = gatingBusiness('base');
    $appointment = gatingAppointment($business);

    app(\App\Models\SystemSetting::class); // ensure system setting exists (current() creates it)
    \App\Models\SystemSetting::withoutGlobalScope('business')->updateOrCreate(
        ['business_id' => $business->id],
        ['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1],
    );

    app()->instance('current_business_id', $business->id);
    app(LoyaltyService::class)->accrue($appointment, 100.0);

    expect(\App\Models\LoyaltyTransaction::where('appointment_id', $appointment->id)->exists())->toBeFalse();
});

// --- waitlist ---

it('WaitlistController::store aborts 403 when waitlist feature is plus and business is base', function () {
    PlanFeature::where('key', 'waitlist')->update(['min_plan' => 'plus']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $business = gatingBusiness('base');
    app()->instance('current_business_id', $business->id);
    $user = User::factory()->create(['business_id' => $business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->post(route('portal.waitlist.store'), [
            'service_ids'         => [Service::factory()->create(['business_id' => $business->id])->id],
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '11:00',
            'preferred_days'      => [now()->addDay()->format('Y-m-d')],
        ])
        ->assertForbidden();
});
```

- [ ] **Step 2: Confirm failure**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/FeatureGateIntegrationTest.php
```

Expected: FAIL

- [ ] **Step 3: Gate WhatsAppNotificationService**

In `app/Services/WhatsAppNotificationService.php`, add the plan gate as the first check inside `dispatchForAppointment()`, before `$settings`:

```php
public function dispatchForAppointment(Appointment $appointment, string $templateName, array $parameters): ?WhatsAppMessage
{
    $business = \App\Models\Business::find($appointment->business_id);

    if (! $business?->canUseFeature('whatsapp_notifications')) {
        return null;
    }

    $settings = IntegrationSetting::withoutGlobalScope('business')
        ->where('business_id', $appointment->business_id)
        ->first();

    if (! $settings?->hasWhatsAppNotificationsEnabled()) {
        return null;
    }
    // ... rest unchanged
```

- [ ] **Step 4: Gate canAcceptOnlinePayments in Business**

In `app/Models/Business.php`, update `canAcceptOnlinePayments()`:

```php
public function canAcceptOnlinePayments(): bool
{
    if (! $this->canUseFeature('online_payments')) {
        return false;
    }
    $account = $this->stripeConnectAccount;
    return $account !== null && $account->isActive();
}
```

- [ ] **Step 5: Gate SyncGoogleCalendar job**

In `app/Jobs/SyncGoogleCalendar.php`, add the plan gate at the start of `handle()`, after `app()->instance('current_business_id', ...)`:

```php
public function handle(GoogleCalendarService $calendarService): void
{
    app()->instance('current_business_id', $this->appointment->business_id);

    $business = \App\Models\Business::find($this->appointment->business_id);
    if (! $business?->canUseFeature('google_calendar')) {
        return;
    }

    if (! in_array($this->action, ['create', 'delete'])) {
        throw new \InvalidArgumentException("Unknown SyncGoogleCalendar action: {$this->action}");
    }
    // ... rest unchanged
```

- [ ] **Step 6: Gate LoyaltyService::accrue**

In `app/Services/LoyaltyService.php`, add the plan gate as the first check in `accrue()`, before the `isLoyaltyEnabled()` check:

```php
public function accrue(Appointment $appointment, float $amount): void
{
    $business = \App\Models\Business::find($appointment->business_id);
    if (! $business?->canUseFeature('loyalty_program')) {
        return;
    }

    if (! SystemSetting::isLoyaltyEnabled()) {
        return;
    }
    // ... rest unchanged
```

- [ ] **Step 7: Gate WaitlistController::store**

In `app/Http/Controllers/Portal/WaitlistController.php`, add the plan gate at the start of `store()`:

```php
public function store(Request $request): RedirectResponse
{
    $business = \App\Models\Business::find(app('current_business_id'));
    if (! $business?->canUseFeature('waitlist')) {
        abort(403);
    }

    // ... existing validation and WaitlistEntry::create unchanged
```

- [ ] **Step 8: Gate WaitlistEntryResource::canAccess()**

In `app/Filament/Resources/WaitlistEntryResource.php`, update `canAccess()`:

```php
public static function canAccess(): bool
{
    if (! (auth()->user()?->isAdmin() || auth()->user()?->isStaff())) {
        return false;
    }

    try {
        $business = \App\Models\Business::findOrFail(\App\Models\Business::currentId());
        return $business->canUseFeature('waitlist');
    } catch (\Throwable) {
        return false;
    }
}
```

- [ ] **Step 9: Gate IntegrationSettings — whatsapp_notifications section**

In `app/Filament/Pages/IntegrationSettings.php`, convert the `Section::make('WhatsApp (Meta Cloud API)')` schema from a plain array to a closure. The section currently contains three fields: `meta_whatsapp_token`, `meta_whatsapp_phone_id`, `meta_whatsapp_template`. Wrap them:

```php
Section::make('WhatsApp (Meta Cloud API)')
    ->description('Credenziali per inviare promemoria via WhatsApp. Richiede un\'app Meta con WhatsApp Business API configurata. Consulta Aiuto → SMS e WhatsApp per la guida completa.')
    ->schema(function (): array {
        $hasPlan = $this->getBusiness()->canUseFeature('whatsapp_notifications');

        $upgradeNotice = $hasPlan ? [] : [
            Placeholder::make('upgrade_notice_notifications')
                ->label('')
                ->hint('Disponibile nel piano Plus.')
                ->hintIcon('heroicon-o-rocket-launch')
                ->hintColor('primary'),
        ];

        return [
            ...$upgradeNotice,

            TextInput::make('meta_whatsapp_token')
                ->label('Access Token')
                ->helperText('Token permanente del System User. Meta Business Suite → Impostazioni → Utenti di sistema → Genera token.')
                ->password()
                ->revealable()
                ->nullable()
                ->disabled(! $hasPlan),

            TextInput::make('meta_whatsapp_phone_id')
                ->label('Phone Number ID')
                ->helperText('Meta for Developers → App → WhatsApp → Configurazione API → Phone Number ID (stringa numerica).')
                ->nullable()
                ->disabled(! $hasPlan),

            TextInput::make('meta_whatsapp_template')
                ->label('Nome template')
                ->helperText('Nome del template approvato da Meta per i promemoria. Default: appointment_reminder.')
                ->nullable()
                ->placeholder('appointment_reminder')
                ->disabled(! $hasPlan),
        ];
    }),
```

- [ ] **Step 10: Gate IntegrationSettings — google_calendar section**

Same pattern for `Section::make('Google Calendar')` (currently last section with `google_calendar_id` and `google_credentials_json`):

```php
Section::make('Google Calendar')
    ->description('Credenziali per sincronizzare gli appuntamenti con Google Calendar. Richiede un Service Account su Google Cloud Console.')
    ->schema(function (): array {
        $hasPlan = $this->getBusiness()->canUseFeature('google_calendar');

        $upgradeNotice = $hasPlan ? [] : [
            Placeholder::make('upgrade_notice_google')
                ->label('')
                ->hint('Disponibile nel piano Plus.')
                ->hintIcon('heroicon-o-rocket-launch')
                ->hintColor('primary'),
        ];

        return [
            ...$upgradeNotice,

            TextInput::make('google_calendar_id')
                ->label('Calendar ID')
                ->helperText('Google Calendar → Impostazioni del calendario → "ID calendario". Es. abc123@group.calendar.google.com. Il Service Account deve avere il ruolo "Modifica eventi" sul calendario.')
                ->nullable()
                ->disabled(! $hasPlan),

            Textarea::make('google_credentials_json')
                ->label('Credenziali Service Account (JSON)')
                ->helperText('console.cloud.google.com → IAM e amministrazione → Account di servizio → seleziona account → Chiavi → Aggiungi chiave → JSON. Incolla qui l\'intero contenuto del file scaricato.')
                ->nullable()
                ->rows(6)
                ->disabled(! $hasPlan),
        ];
    }),
```

- [ ] **Step 11: Gate SystemSettings — loyalty section**

In `app/Filament/Pages/SystemSettings.php`:

**11a.** Add imports at the top of the file:

```php
use App\Models\Business;
use Filament\Schemas\Components\Placeholder;
```

**11b.** Add `getBusiness()` method to the class (before `mount()`):

```php
public function getBusiness(): Business
{
    return once(fn () => Business::findOrFail(Business::currentId()));
}
```

**11c.** Replace `Section::make('Programma fedeltà')` with the gated version:

```php
Section::make('Programma fedeltà')
    ->columns(3)
    ->schema(function (): array {
        $hasPlan = $this->getBusiness()->canUseFeature('loyalty_program');

        $upgradeNotice = $hasPlan ? [] : [
            Placeholder::make('upgrade_notice_loyalty')
                ->label('')
                ->hint('Disponibile nel piano Plus.')
                ->hintIcon('heroicon-o-rocket-launch')
                ->hintColor('primary')
                ->columnSpanFull(),
        ];

        return [
            ...$upgradeNotice,

            Toggle::make('loyalty_enabled')
                ->label('Abilita programma fedeltà')
                ->helperText('I clienti accumulano punti sulla spesa e sbloccano uno sconto')
                ->live()
                ->columnSpanFull()
                ->disabled(! $hasPlan),

            TextInput::make('loyalty_points_per_euro')
                ->label('Punti per euro speso')
                ->integer()
                ->minValue(1)
                ->required()
                ->suffix('punti/€')
                ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled'))
                ->disabled(! $hasPlan),

            TextInput::make('loyalty_reward_threshold')
                ->label('Punti per lo sconto')
                ->integer()
                ->minValue(1)
                ->required()
                ->suffix('punti')
                ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled'))
                ->disabled(! $hasPlan),

            TextInput::make('loyalty_reward_percentage')
                ->label('Sconto sbloccato')
                ->integer()
                ->minValue(1)
                ->maxValue(100)
                ->required()
                ->suffix('%')
                ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled'))
                ->disabled(! $hasPlan),
        ];
    }),
```

- [ ] **Step 12: Run integration tests**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/FeatureGateIntegrationTest.php
```

Expected: all pass

- [ ] **Step 13: Run full suite to confirm no regressions**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: same pass count as before this task, plus new tests.

- [ ] **Step 14: Commit**

```bash
git add app/Services/WhatsAppNotificationService.php app/Models/Business.php app/Jobs/SyncGoogleCalendar.php app/Services/LoyaltyService.php app/Http/Controllers/Portal/WaitlistController.php app/Filament/Pages/IntegrationSettings.php app/Filament/Pages/SystemSettings.php app/Filament/Resources/WaitlistEntryResource.php tests/Feature/Services/FeatureGateIntegrationTest.php
git commit -m "feat: gate all six plan features at UI and backend service entry points"
```
