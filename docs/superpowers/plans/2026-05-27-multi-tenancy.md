# Multi-Tenancy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the single-tenant salon management system into a multi-tenant SaaS with per-subdomain isolation, a super-admin panel to create salons, and anti-leakage tests.

**Architecture:** Single MySQL database, `business_id` on every tenant table, `BelongsToBusiness` Eloquent global scope, `SubdomainMiddleware` resolves the tenant from the request host, Filament 4 `->tenantDomain()` for the admin panel, separate `SuperAdminPanelProvider` at `/superadmin`.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, MySQL 8, Spatie Permission

**Spec:** `docs/superpowers/specs/2026-05-27-multi-tenancy-design.md`

**All commands run inside Docker:** `docker-compose run --rm app <cmd>`

---

## File Map

**New files:**
- `app/Enums/BusinessStatus.php`
- `app/Models/Business.php`
- `app/Models/Concerns/BelongsToBusiness.php`
- `database/factories/BusinessFactory.php`
- `database/migrations/2026_05_27_150000_create_businesses_table.php`
- `database/migrations/2026_05_27_151000_add_must_change_password_to_users.php`
- `database/migrations/2026_05_27_160000_add_business_id_to_tenant_tables.php`
- `database/migrations/2026_05_27_170000_add_multi_tenancy_indexes.php`
- `app/Http/Middleware/SubdomainMiddleware.php`
- `app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php`
- `app/Http/Middleware/EnforceTenantStatus.php`
- `app/Services/BusinessProvisioningService.php`
- `app/Providers/Filament/SuperAdminPanelProvider.php`
- `app/Filament/SuperAdmin/Resources/BusinessResource.php`
- `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/ListBusinesses.php`
- `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/CreateBusiness.php`
- `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/EditBusiness.php`
- `tests/Concerns/WithBusinessContext.php`
- `tests/Feature/Models/BusinessTest.php`
- `tests/Feature/MultiTenancy/MiddlewareTest.php`
- `tests/Feature/MultiTenancy/ModelScopingTest.php`
- `tests/Feature/Services/BusinessProvisioningServiceTest.php`

**Modified files:**
- `app/Models/User.php` — add `business_id`, `HasTenants`, `canAccessPanel` super-admin check
- `app/Models/SystemSetting.php` — `current()` → `Business::currentId()`, add `BelongsToBusiness`
- `app/Models/SalonProfile.php` — same
- `app/Models/IntegrationSetting.php` — same
- `app/Models/Service.php`, `Appointment.php`, `AvailabilityRule.php`, `AppointmentReminder.php`, `Payment.php`, `UserPreference.php`, `WaitlistEntry.php`, `SalonReview.php` — add `BelongsToBusiness`
- `app/Jobs/SendAppointmentReminder.php`, `SyncGoogleCalendar.php`, `SendAppointmentConfirmation.php`, `SendCancellationNotification.php`, `NotifyWaitlistCandidateJob.php` — bind `business_id` at start of `handle()`
- `app/Services/PaymentService.php` — add `business_id` to Stripe metadata
- `app/Http/Controllers/StripeWebhookController.php` — restore business context from metadata
- `app/Providers/AppServiceProvider.php` — `singleton` → `bind` for 3 integration services
- `app/Providers/Filament/AdminPanelProvider.php` — add `->tenantDomain()`
- `bootstrap/app.php` — register middleware aliases + append `SubdomainMiddleware` to web/api
- `bootstrap/providers.php` — add `SuperAdminPanelProvider`
- `config/app.php` — add `base_domain`
- `.env.example` — add `APP_BASE_DOMAIN`, `SESSION_DOMAIN`, `TRUSTED_HOSTS`
- `routes/web.php` — add `tenant.user`, `tenant.status` to auth group; root domain redirect

---

### Task 1: BusinessStatus enum + Business model + BelongsToBusiness trait

**Files:**
- Create: `app/Enums/BusinessStatus.php`
- Create: `app/Models/Business.php`
- Create: `app/Models/Concerns/BelongsToBusiness.php`
- Create: `database/factories/BusinessFactory.php`
- Create: `tests/Feature/Models/BusinessTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Models/BusinessTest.php`:

```php
<?php

use App\Enums\BusinessStatus;
use App\Models\Business;

it('throws RuntimeException when no business context is bound', function () {
    expect(fn() => Business::currentId())->toThrow(\RuntimeException::class, 'No current business context bound.');
});

it('returns the bound business id', function () {
    app()->instance('current_business_id', 42);
    expect(Business::currentId())->toBe(42);
});

it('has active status after creation', function () {
    $business = Business::factory()->create(['status' => BusinessStatus::Active]);
    expect($business->status)->toBe(BusinessStatus::Active);
    expect($business->status->value)->toBe('active');
});

it('BelongsToBusiness global scope filters records by current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    \App\Models\SalonReview::factory()->create(['business_id' => $b1->id]);
    \App\Models\SalonReview::factory()->create(['business_id' => $b2->id]);

    app()->instance('current_business_id', $b1->id);
    expect(\App\Models\SalonReview::count())->toBe(1);

    app()->instance('current_business_id', $b2->id);
    expect(\App\Models\SalonReview::count())->toBe(1);
});

it('BelongsToBusiness auto-fills business_id on create', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $review = \App\Models\SalonReview::create([
        'author_name' => 'Test',
        'rating'      => 5,
        'body'        => 'Great!',
        'published'   => true,
        'sort_order'  => 1,
    ]);

    expect($review->business_id)->toBe($business->id);
});
```

- [ ] **Step 2: Run to verify failure**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/BusinessTest.php
```

Expected: FAIL — `Business` class not found.

- [ ] **Step 3: Create BusinessStatus enum**

Create `app/Enums/BusinessStatus.php`:

```php
<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case Active    = 'active';
    case Suspended = 'suspended';
}
```

- [ ] **Step 4: Create BelongsToBusiness trait**

Create `app/Models/Concerns/BelongsToBusiness.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            if (app()->bound('current_business_id')) {
                $query->where(
                    (new static)->getTable() . '.business_id',
                    app('current_business_id')
                );
            }
        });

        static::creating(function (Model $model) {
            if (app()->bound('current_business_id') && empty($model->business_id)) {
                $model->business_id = app('current_business_id');
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
```

- [ ] **Step 5: Create Business model**

Create `app/Models/Business.php`:

```php
<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'subdomain', 'status'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['status' => BusinessStatus::class];
    }

    public static function currentId(): int
    {
        if (! app()->bound('current_business_id')) {
            throw new \RuntimeException('No current business context bound.');
        }

        return app('current_business_id');
    }

    public function users(): HasMany        { return $this->hasMany(User::class); }
    public function services(): HasMany     { return $this->hasMany(Service::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function systemSetting(): HasOne { return $this->hasOne(SystemSetting::class); }
    public function salonProfile(): HasOne  { return $this->hasOne(SalonProfile::class); }
    public function integrationSetting(): HasOne { return $this->hasOne(IntegrationSetting::class); }
}
```

- [ ] **Step 6: Create Business factory**

Create `database/factories/BusinessFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Business> */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => fake()->company(),
            'subdomain' => fake()->unique()->lexify('salon-????'),
            'status'    => BusinessStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => BusinessStatus::Suspended]);
    }
}
```

- [ ] **Step 7: Run the two non-DB tests now (DB tests need migration from Task 2)**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/BusinessTest.php --filter "throws RuntimeException|returns the bound"
```

Expected: both PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/BusinessStatus.php app/Models/Business.php app/Models/Concerns/BelongsToBusiness.php database/factories/BusinessFactory.php tests/Feature/Models/BusinessTest.php
git commit -m "feat: add Business model, BusinessStatus enum, and BelongsToBusiness trait"
```

---

### Task 2: Migrations — businesses table + must_change_password on users

**Files:**
- Create: `database/migrations/2026_05_27_150000_create_businesses_table.php`
- Create: `database/migrations/2026_05_27_151000_add_must_change_password_to_users.php`

- [ ] **Step 1: Create businesses migration**

Create `database/migrations/2026_05_27_150000_create_businesses_table.php`:

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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain')->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
        });

        DB::table('businesses')->insert([
            'id'         => 1,
            'name'       => 'Salone',
            'subdomain'  => 'salone',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
```

- [ ] **Step 2: Create must_change_password migration**

Create `database/migrations/2026_05_27_151000_add_must_change_password_to_users.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: 2 new migrations complete without error.

- [ ] **Step 4: Run all Business model tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/BusinessTest.php
```

Expected: all 5 PASS (the `BelongsToBusiness` tests need SalonReview to also have `business_id` — those tests will fail until Task 3 migration runs and Task 5 applies the trait. Skip them for now by running only non-DB tests; they are verified fully in Task 5).

> **Note:** The last two tests in `BusinessTest.php` (scope and auto-fill) need the SalonReview table to have `business_id` and the trait applied. They will be verified after Task 5.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_27_150000_create_businesses_table.php database/migrations/2026_05_27_151000_add_must_change_password_to_users.php
git commit -m "feat: create businesses table and add must_change_password to users"
```

---

### Task 3: Migration — add business_id to all tenant tables

**Files:**
- Create: `database/migrations/2026_05_27_160000_add_business_id_to_tenant_tables.php`

- [ ] **Step 1: Create migration**

Create `database/migrations/2026_05_27_160000_add_business_id_to_tenant_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tables that get NOT NULL business_id (all tenant tables except users)
    private array $tenantTables = [
        'services', 'appointments', 'availability_rules', 'time_slots',
        'appointment_reminders', 'payments', 'user_preferences',
        'system_settings', 'salon_profiles', 'salon_reviews',
        'waitlist_entries', 'integration_settings',
    ];

    public function up(): void
    {
        // Step 1: add nullable business_id (no FK yet) so backfill can run
        foreach ($this->tenantTables as $table) {
            Schema::table($table, fn(Blueprint $t) =>
                $t->unsignedBigInteger('business_id')->nullable()->after('id')
            );
        }
        // users: nullable permanently (super-admin users have NULL)
        Schema::table('users', fn(Blueprint $t) =>
            $t->unsignedBigInteger('business_id')->nullable()->after('id')
        );

        // Step 2: backfill all existing rows to business id=1
        foreach ([...$this->tenantTables, 'users'] as $table) {
            DB::table($table)->whereNull('business_id')->update(['business_id' => 1]);
        }

        // Step 3: make NOT NULL + add FK on tenant tables (not users)
        foreach ($this->tenantTables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('business_id')->nullable(false)->change();
                $t->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            });
        }

        // Step 4: add FK on users (nullable — null on delete to allow super-admin users)
        Schema::table('users', fn(Blueprint $t) =>
            $t->foreign('business_id')->references('id')->on('businesses')->nullOnDelete()
        );
    }

    public function down(): void
    {
        foreach ([...$this->tenantTables, 'users'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['business_id']);
                $t->dropColumn('business_id');
            });
        }
    }
};
```

- [ ] **Step 2: Run migration**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: migration completes without error.

- [ ] **Step 3: Verify backfill**

```bash
docker-compose run --rm app php artisan tinker --execute="echo DB::table('appointments')->whereNull('business_id')->count();"
```

Expected: `0`

- [ ] **Step 4: Update all factories that create tenant models to include `business_id`**

Any factory that creates records for tenant tables must now include `'business_id' => 1` as a default (before the BelongsToBusiness trait is applied — after Task 5 the trait auto-fills it when context is bound). Open each factory file and add `'business_id' => 1` to the `definition()` array.

Factories to update (check each file in `database/factories/`):
- `AppointmentFactory.php` — add `'business_id' => 1`
- `ServiceFactory.php` — add `'business_id' => 1`
- `PaymentFactory.php` — add `'business_id' => 1`
- `WaitlistEntryFactory.php` — add `'business_id' => 1`
- `SalonReviewFactory.php` — add `'business_id' => 1`
- `UserFactory.php` — add `'business_id' => 1`
- Any other factory for a tenant model

Pattern for each:
```php
public function definition(): array
{
    return [
        'business_id' => 1,  // add this line
        // ... existing fields
    ];
}
```

- [ ] **Step 5: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest --stop-on-failure
```

Expected: all tests pass. If any test fails with a NOT NULL constraint error, find the factory and add `'business_id' => 1`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_05_27_160000_add_business_id_to_tenant_tables.php database/factories/
git commit -m "feat: add business_id to all tenant tables; backfill to business 1; update factories"
```

---

### Task 4: Migration — composite indexes + singleton UNIQUE constraints

**Files:**
- Create: `database/migrations/2026_05_27_170000_add_multi_tenancy_indexes.php`

- [ ] **Step 1: Create migration**

Create `database/migrations/2026_05_27_170000_add_multi_tenancy_indexes.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One record per business for singletons
        Schema::table('system_settings',     fn(Blueprint $t) => $t->unique('business_id'));
        Schema::table('salon_profiles',      fn(Blueprint $t) => $t->unique('business_id'));
        Schema::table('integration_settings',fn(Blueprint $t) => $t->unique('business_id'));

        Schema::table('appointments', function (Blueprint $t) {
            $t->index(['business_id', 'scheduled_date']);
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'user_id']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->index(['business_id', 'email']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('services', function (Blueprint $t) {
            $t->index(['business_id', 'active']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'created_at']);
        });

        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->index(['business_id', 'status']);
            $t->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings',     fn(Blueprint $t) => $t->dropUnique(['business_id']));
        Schema::table('salon_profiles',      fn(Blueprint $t) => $t->dropUnique(['business_id']));
        Schema::table('integration_settings',fn(Blueprint $t) => $t->dropUnique(['business_id']));

        Schema::table('appointments', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'scheduled_date']);
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'user_id']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'email']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('services', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'active']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'created_at']);
        });

        Schema::table('waitlist_entries', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'status']);
            $t->dropIndex(['business_id', 'created_at']);
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_170000_add_multi_tenancy_indexes.php
git commit -m "feat: add composite indexes and singleton unique constraints"
```

---

### Task 5: Apply BelongsToBusiness trait to 12 tenant models

**Files (all modified):**
`app/Models/Service.php`, `app/Models/Appointment.php`, `app/Models/AvailabilityRule.php`, `app/Models/AppointmentReminder.php`, `app/Models/Payment.php`, `app/Models/UserPreference.php`, `app/Models/WaitlistEntry.php`, `app/Models/SalonReview.php`, `app/Models/SystemSetting.php`, `app/Models/SalonProfile.php`, `app/Models/IntegrationSetting.php`

For each model, three edits:
1. Add `use App\Models\Concerns\BelongsToBusiness;` to imports
2. Add `BelongsToBusiness` to the `use` line inside the class
3. Add `'business_id'` to `#[Fillable([...])]`

- [ ] **Step 1: Edit Service.php**

```php
// Add import:
use App\Models\Concerns\BelongsToBusiness;

// Change class use line from:
use HasFactory;
// to:
use HasFactory, BelongsToBusiness;

// Change #[Fillable] from:
#[Fillable(['name', 'description', 'duration_minutes', 'price', 'active', 'featured'])]
// to:
#[Fillable(['name', 'description', 'duration_minutes', 'price', 'active', 'featured', 'business_id'])]
```

- [ ] **Step 2: Edit Appointment.php**

Same three edits. Add `use BelongsToBusiness;` to the existing `use` line (which likely has `HasFactory`). Add `'business_id'` to `#[Fillable]`.

- [ ] **Step 3: Edit AvailabilityRule.php**

Same three edits.

- [ ] **Step 4: Edit AppointmentReminder.php**

Same three edits.

- [ ] **Step 5: Edit Payment.php**

Same three edits.

- [ ] **Step 6: Edit UserPreference.php**

Same three edits.

- [ ] **Step 7: Edit WaitlistEntry.php**

Same three edits.

- [ ] **Step 8: Edit SalonReview.php**

Same three edits.

- [ ] **Step 9: Edit SystemSetting.php**

Add import + `use BelongsToBusiness;` in class + add `'business_id'` to `#[Fillable]`.

The `#[Fillable]` currently is:
```php
#[Fillable([
    'slot_generation_weeks', 'slot_granularity_minutes', 'timezone',
    'booking_max_days_ahead', 'cancellation_deadline_hours',
    'reminder_count', 'reminder_1_hours', 'reminder_2_hours', 'payment_mode',
])]
```

Add `'business_id'` to the array.

- [ ] **Step 10: Edit SalonProfile.php**

SalonProfile has `use InteractsWithMedia;`. Change to:
```php
use BelongsToBusiness, InteractsWithMedia;
```

Also add import and `'business_id'` to `#[Fillable]`.

- [ ] **Step 11: Edit IntegrationSetting.php**

Same three edits.

- [ ] **Step 12: Run BusinessTest to verify scope and auto-fill work**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/BusinessTest.php
```

Expected: all 5 PASS.

- [ ] **Step 13: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all pass. If a test creates a model without `business_id` set and the context is not bound, the `creating` listener won't fire and the record will be created with `NULL` (which violates NOT NULL constraint on non-users tables). Fix by ensuring test setup binds `current_business_id` or passes `business_id` directly in factory calls.

- [ ] **Step 14: Commit**

```bash
git add app/Models/
git commit -m "feat: apply BelongsToBusiness trait to all 12 tenant models"
```

---

### Task 6: Update User model + Update singleton models' current() methods

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Models/SystemSetting.php`
- Modify: `app/Models/SalonProfile.php`
- Modify: `app/Models/IntegrationSetting.php`

- [ ] **Step 1: Write failing tests for User HasTenants and SystemSetting::current()**

Add to `tests/Feature/Models/BusinessTest.php`:

```php
use Filament\Panel;

it('User::getTenants() returns collection with its business', function () {
    $business = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => $business->id]);

    $tenants = $user->getTenants(Mockery::mock(Panel::class));

    expect($tenants)->toHaveCount(1);
    expect($tenants->first()->id)->toBe($business->id);
});

it('User::getTenants() returns empty collection for super_admin', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create(['business_id' => null]);
    $user->assignRole('super_admin');

    expect($user->getTenants(Mockery::mock(Panel::class)))->toHaveCount(0);
});

it('User::canAccessTenant() returns false for super_admin', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $business = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => null]);
    $user->assignRole('super_admin');

    expect($user->canAccessTenant($business))->toBeFalse();
});

it('User::canAccessTenant() returns true for matching business', function () {
    $business = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => $business->id]);

    expect($user->canAccessTenant($business))->toBeTrue();
});

it('User::canAccessTenant() returns false for wrong business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    $user = \App\Models\User::factory()->create(['business_id' => $b1->id]);

    expect($user->canAccessTenant($b2))->toBeFalse();
});

it('SystemSetting::current() creates a record for current business', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $setting = \App\Models\SystemSetting::current();

    expect($setting->business_id)->toBe($business->id);
    expect($setting->slot_granularity_minutes)->toBe(15);
});

it('SystemSetting::current() returns same record on subsequent calls', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $first  = \App\Models\SystemSetting::current();
    $second = \App\Models\SystemSetting::current();

    expect($first->id)->toBe($second->id);
});

it('SystemSetting::current() creates separate records per business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    app()->instance('current_business_id', $b1->id);
    $s1 = \App\Models\SystemSetting::current();

    app()->instance('current_business_id', $b2->id);
    $s2 = \App\Models\SystemSetting::current();

    expect($s1->id)->not->toBe($s2->id);
    expect($s1->business_id)->toBe($b1->id);
    expect($s2->business_id)->toBe($b2->id);
});
```

- [ ] **Step 2: Run to verify failure**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/BusinessTest.php --filter "getTenants|canAccessTenant|SystemSetting"
```

Expected: FAIL.

- [ ] **Step 3: Replace User model**

Replace the full content of `app/Models/User.php`:

```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'email', 'password', 'internal_notes', 'calendar_color',
    'bio', 'receive_email_notifications', 'business_id', 'must_change_password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasMedia, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'receive_email_notifications' => 'boolean',
            'must_change_password'        => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->business ? collect([$this->business]) : collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->hasRole('super_admin')) {
            return false;
        }

        return $this->business_id !== null && $this->business_id === $tenant->getKey();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'superadmin') {
            return $this->hasRole('super_admin');
        }

        return $this->isAdmin() || $this->isStaff();
    }

    public function appointmentsAsCustomer(): HasMany { return $this->hasMany(Appointment::class, 'user_id'); }
    public function appointmentsAsStaff(): HasMany   { return $this->hasMany(Appointment::class, 'staff_id'); }
    public function services(): BelongsToMany        { return $this->belongsToMany(Service::class, 'service_staff'); }
    public function availabilityRules(): HasMany     { return $this->hasMany(AvailabilityRule::class); }
    public function preferences(): HasOne            { return $this->hasOne(UserPreference::class); }
    public function payments(): HasMany              { return $this->hasMany(Payment::class); }

    public function isAdmin(): bool    { return $this->hasRole('admin'); }
    public function isStaff(): bool    { return $this->hasRole('staff'); }
    public function isCustomer(): bool { return $this->hasRole('customer'); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile()->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(200)->height(200)->nonQueued();
    }
}
```

- [ ] **Step 4: Update SystemSetting::current()**

In `app/Models/SystemSetting.php`, replace the `current()` method:

```php
public static function current(): self
{
    return self::firstOrCreate(
        ['business_id' => Business::currentId()],
        [
            'slot_generation_weeks'       => 4,
            'slot_granularity_minutes'    => 15,
            'timezone'                    => 'Europe/Rome',
            'booking_max_days_ahead'      => 30,
            'cancellation_deadline_hours' => 24,
            'reminder_count'              => 1,
            'reminder_1_hours'            => 24,
            'reminder_2_hours'            => 2,
            'payment_mode'                => 'both',
        ]
    );
}
```

Add `use App\Models\Business;` import to `app/Models/SystemSetting.php`.

- [ ] **Step 5: Update SalonProfile::current()**

In `app/Models/SalonProfile.php`, replace the `current()` method:

```php
public static function current(): self
{
    return self::firstOrCreate(
        ['business_id' => Business::currentId()],
        [
            'name'          => 'Il mio salone',
            'primary_color' => '#1d1d1d',
        ]
    );
}
```

Add `use App\Models\Business;` import.

- [ ] **Step 6: Update IntegrationSetting::current()**

In `app/Models/IntegrationSetting.php`, replace the `current()` method:

```php
public static function current(): self
{
    return self::firstOrCreate(
        ['business_id' => Business::currentId()]
    );
}
```

Add `use App\Models\Business;` import.

- [ ] **Step 7: Fix existing singleton tests**

Existing tests in `tests/Feature/Models/SystemSettingTest.php`, `SalonProfileTest.php`, `IntegrationSettingTest.php` call `current()` without a bound business context → they will throw `RuntimeException`.

Fix: in each of those test files, add a `beforeEach` that sets business context:

```php
beforeEach(function () {
    \App\Models\Business::factory()->create(['id' => 1]); // match the seeded record
    app()->instance('current_business_id', 1);
});
```

Or use `RefreshDatabase` + factory to create a business and bind its id.

- [ ] **Step 8: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/BusinessTest.php
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SystemSettingTest.php
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Models/User.php app/Models/SystemSetting.php app/Models/SalonProfile.php app/Models/IntegrationSetting.php tests/Feature/Models/
git commit -m "feat: update User (HasTenants) and singleton models to use Business::currentId()"
```

---

### Task 7: Environment config + SubdomainMiddleware

**Files:**
- Modify: `config/app.php`
- Modify: `.env.example`
- Create: `app/Http/Middleware/SubdomainMiddleware.php`
- Create: `tests/Feature/MultiTenancy/MiddlewareTest.php`

- [ ] **Step 1: Add base_domain to config/app.php**

In `config/app.php`, add after the `'url'` line:

```php
'base_domain' => env('APP_BASE_DOMAIN', ''),
```

- [ ] **Step 2: Update .env.example**

Append to `.env.example`:

```dotenv
APP_BASE_DOMAIN=tuogestionale.it
SESSION_DOMAIN=null
TRUSTED_HOSTS=\.tuogestionale\.it$
```

- [ ] **Step 3: Write failing middleware tests**

Create `tests/Feature/MultiTenancy/MiddlewareTest.php`:

```php
<?php

use App\Enums\BusinessStatus;
use App\Http\Middleware\EnsureUserBelongsToCurrentBusiness;
use App\Http\Middleware\SubdomainMiddleware;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config(['app.base_domain' => 'tuogestionale.it']);
});

it('SubdomainMiddleware binds current_business_id for active subdomain', function () {
    $business = Business::factory()->create(['subdomain' => 'test-salon']);

    $request = Request::create('http://test-salon.tuogestionale.it/prenota');
    $request->headers->set('HOST', 'test-salon.tuogestionale.it');

    $called = false;
    (new SubdomainMiddleware())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
    expect(app('current_business_id'))->toBe($business->id);
});

it('SubdomainMiddleware returns 404 for unknown subdomain', function () {
    $request = Request::create('http://unknown.tuogestionale.it/prenota');
    $request->headers->set('HOST', 'unknown.tuogestionale.it');

    expect(fn() => (new SubdomainMiddleware())->handle($request, fn() => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

it('SubdomainMiddleware returns 503 for suspended business', function () {
    Business::factory()->suspended()->create(['subdomain' => 'suspended-salon']);

    $request = Request::create('http://suspended-salon.tuogestionale.it/prenota');
    $request->headers->set('HOST', 'suspended-salon.tuogestionale.it');

    expect(fn() => (new SubdomainMiddleware())->handle($request, fn() => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('SubdomainMiddleware skips when APP_BASE_DOMAIN is empty', function () {
    config(['app.base_domain' => '']);
    Business::factory()->create();

    $request = Request::create('http://localhost/prenota');
    $request->headers->set('HOST', 'localhost');

    $called = false;
    (new SubdomainMiddleware())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
    expect(app()->bound('current_business_id'))->toBeTrue();
});

it('EnsureUserBelongsToCurrentBusiness allows user with matching business', function () {
    $business = Business::factory()->create();
    $user = User::factory()->create(['business_id' => $business->id]);
    app()->instance('current_business_id', $business->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn() => $user);

    $called = false;
    (new EnsureUserBelongsToCurrentBusiness())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
});

it('EnsureUserBelongsToCurrentBusiness blocks user from wrong business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    $user = User::factory()->create(['business_id' => $b1->id]);
    app()->instance('current_business_id', $b2->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn() => $user);

    expect(fn() => (new EnsureUserBelongsToCurrentBusiness())->handle($request, fn() => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
```

- [ ] **Step 4: Run to verify failure**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/MiddlewareTest.php
```

Expected: FAIL — `SubdomainMiddleware` not found.

- [ ] **Step 5: Create SubdomainMiddleware**

Create `app/Http/Middleware/SubdomainMiddleware.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubdomainMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = config('app.base_domain');

        if (! $baseDomain) {
            $business = Business::withoutGlobalScopes()
                ->where('status', BusinessStatus::Active)
                ->first();

            if ($business) {
                app()->instance('current_business_id', $business->id);
            }

            return $next($request);
        }

        $host = $request->getHost();

        if (! str_ends_with($host, '.' . $baseDomain)) {
            abort(404);
        }

        $subdomain = str($host)->before('.' . $baseDomain)->value();

        if (empty($subdomain)) {
            abort(404);
        }

        $business = Business::withoutGlobalScopes()
            ->where('subdomain', $subdomain)
            ->first();

        if (! $business) {
            abort(404);
        }

        if ($business->status === BusinessStatus::Suspended) {
            abort(503, 'This salon is currently unavailable.');
        }

        app()->instance('current_business_id', $business->id);

        return $next($request);
    }
}
```

- [ ] **Step 6: Create EnsureUserBelongsToCurrentBusiness**

Create `app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToCurrentBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->business_id !== Business::currentId()) {
            abort(403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 7: Create EnforceTenantStatus**

Create `app/Http/Middleware/EnforceTenantStatus.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\BusinessStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $business = auth()->user()->business;
            if ($business && $business->status === BusinessStatus::Suspended) {
                auth()->logout();
                abort(503, 'This salon is currently unavailable.');
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 8: Run middleware tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/MiddlewareTest.php
```

Expected: all PASS.

- [ ] **Step 9: Commit**

```bash
git add config/app.php .env.example app/Http/Middleware/
git commit -m "feat: add SubdomainMiddleware, EnsureUserBelongsToCurrentBusiness, EnforceTenantStatus"
```

---

### Task 8: Register middlewares in bootstrap/app.php + update routes

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Register middleware aliases and append SubdomainMiddleware**

Replace the `withMiddleware` closure in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->preventRequestForgery(except: [
        'stripe/webhook',
    ]);

    $middleware->alias([
        'tenant.user'   => \App\Http\Middleware\EnsureUserBelongsToCurrentBusiness::class,
        'tenant.status' => \App\Http\Middleware\EnforceTenantStatus::class,
    ]);

    $middleware->web(append: [
        \App\Http\Middleware\SubdomainMiddleware::class,
    ]);

    $middleware->api(append: [
        \App\Http\Middleware\SubdomainMiddleware::class,
    ]);
})
```

- [ ] **Step 2: Add tenant.user + tenant.status to the authenticated route group in routes/web.php**

Find the `Route::middleware(['auth'])->group(...)` block and change its middleware to:

```php
Route::middleware(['auth', 'tenant.user', 'tenant.status'])->group(function () {
    // All existing authenticated routes stay unchanged
});
```

- [ ] **Step 3: Add tenant.user to the authenticated API group in routes/api.php**

Find the `Route::middleware(['auth:sanctum'])->group(...)` block and change to:

```php
Route::middleware(['auth:sanctum', 'tenant.user'])->group(function () {
    // All existing API routes stay unchanged
});
```

- [ ] **Step 4: Add root domain routes at the top of routes/web.php (before any middleware groups)**

Add at the very top of `routes/web.php`, before any other route definitions:

```php
// Root domain: redirect to super-admin (no SubdomainMiddleware here)
Route::get('/', fn() => redirect('/superadmin'))
    ->withoutMiddleware([\App\Http\Middleware\SubdomainMiddleware::class]);
```

> **Note:** The `/up` health route is already registered by `withRouting(health: '/up')` in `bootstrap/app.php` — no changes needed.

- [ ] **Step 5: Set APP_BASE_DOMAIN empty in phpunit.xml for tests**

Open `phpunit.xml` and add inside `<php>`:

```xml
<env name="APP_BASE_DOMAIN" value=""/>
```

This ensures `SubdomainMiddleware` uses the local-dev fallback (first active business) during test runs instead of requiring actual subdomain routing.

- [ ] **Step 6: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add bootstrap/app.php routes/web.php routes/api.php phpunit.xml
git commit -m "feat: register tenant middlewares and apply to authenticated routes"
```

---

### Task 9: Update AdminPanelProvider + AppServiceProvider

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add tenant config to AdminPanelProvider**

In `app/Providers/Filament/AdminPanelProvider.php`, add `use App\Models\Business;` to imports.

In the `panel()` method, add after `->id('admin')`:

```php
->tenant(Business::class, slugAttribute: 'subdomain')
->tenantDomain('{tenant:subdomain}.' . config('app.base_domain', 'tuogestionale.it'))
->tenantMiddleware([\App\Http\Middleware\SubdomainMiddleware::class], isPersistent: true)
```

- [ ] **Step 2: Change singleton to bind in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, change every `$this->app->singleton(PaymentService::class, ...)`, `$this->app->singleton(\App\Services\NotificationService::class, ...)`, and `$this->app->singleton(\App\Services\GoogleCalendarService::class, ...)` to `$this->app->bind(...)`.

The closures stay identical — only `singleton` changes to `bind`. This forces a fresh client per request so each request uses the correct tenant's credentials.

- [ ] **Step 3: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all pass.

- [ ] **Step 4: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php app/Providers/AppServiceProvider.php
git commit -m "feat: configure Filament tenant panel and switch integration services to per-request binding"
```

---

### Task 10: SuperAdminPanelProvider + BusinessResource + BusinessProvisioningService

**Files:**
- Create: `app/Providers/Filament/SuperAdminPanelProvider.php`
- Create: `app/Filament/SuperAdmin/Resources/BusinessResource.php`
- Create: `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/ListBusinesses.php`
- Create: `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/CreateBusiness.php`
- Create: `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/EditBusiness.php`
- Create: `app/Services/BusinessProvisioningService.php`
- Modify: `bootstrap/providers.php`
- Create: `tests/Feature/Services/BusinessProvisioningServiceTest.php`

- [ ] **Step 1: Write failing provisioning test**

Create `tests/Feature/Services/BusinessProvisioningServiceTest.php`:

```php
<?php

use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BusinessProvisioningService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'staff', 'customer', 'super_admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

it('provisions admin user with correct business and role', function () {
    $business = Business::factory()->create();

    $admin = (new BusinessProvisioningService())->provision($business, 'owner@example.com');

    expect($admin->email)->toBe('owner@example.com');
    expect($admin->business_id)->toBe($business->id);
    expect($admin->hasRole('admin'))->toBeTrue();
    expect($admin->must_change_password)->toBeTrue();
    expect(isset($admin->plainPassword))->toBeTrue();
});

it('provisions default SystemSetting for the business', function () {
    $business = Business::factory()->create();

    (new BusinessProvisioningService())->provision($business, 'owner@example.com');

    $setting = SystemSetting::withoutGlobalScopes()
        ->where('business_id', $business->id)->first();

    expect($setting)->not->toBeNull();
    expect($setting->slot_granularity_minutes)->toBe(15);
    expect($setting->payment_mode)->toBe('both');
});

it('provisions SalonProfile, IntegrationSetting, and 3 sample services', function () {
    $business = Business::factory()->create(['name' => 'Salone Test']);

    (new BusinessProvisioningService())->provision($business, 'owner@example.com');

    $profile = SalonProfile::withoutGlobalScopes()->where('business_id', $business->id)->first();
    expect($profile->name)->toBe('Salone Test');

    $integration = IntegrationSetting::withoutGlobalScopes()->where('business_id', $business->id)->first();
    expect($integration)->not->toBeNull();

    $count = Service::withoutGlobalScopes()->where('business_id', $business->id)->count();
    expect($count)->toBe(3);
});

it('rolls back completely on failure', function () {
    // Create a user with the same email to trigger a unique constraint violation
    User::factory()->create(['email' => 'conflict@example.com']);

    $business = Business::factory()->create();

    expect(fn() => (new BusinessProvisioningService())->provision($business, 'conflict@example.com'))
        ->toThrow(\Exception::class);

    expect(SystemSetting::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(0);
    expect(SalonProfile::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(0);
});
```

- [ ] **Step 2: Run to verify failure**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/BusinessProvisioningServiceTest.php
```

Expected: FAIL — `BusinessProvisioningService` not found.

- [ ] **Step 3: Create BusinessProvisioningService**

Create `app/Services/BusinessProvisioningService.php`:

```php
<?php

namespace App\Services;

use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessProvisioningService
{
    public function provision(Business $business, string $adminEmail): User
    {
        return DB::transaction(function () use ($business, $adminEmail) {
            $tempPassword = Str::random(12);

            $admin = User::create([
                'name'                 => 'Admin',
                'email'                => $adminEmail,
                'password'             => Hash::make($tempPassword),
                'business_id'          => $business->id,
                'must_change_password' => true,
            ]);
            $admin->assignRole('admin');
            $admin->plainPassword = $tempPassword;

            SystemSetting::create([
                'business_id'                 => $business->id,
                'slot_generation_weeks'       => 4,
                'slot_granularity_minutes'    => 15,
                'timezone'                    => 'Europe/Rome',
                'booking_max_days_ahead'      => 30,
                'cancellation_deadline_hours' => 24,
                'reminder_count'              => 1,
                'reminder_1_hours'            => 24,
                'reminder_2_hours'            => 2,
                'payment_mode'                => 'both',
            ]);

            SalonProfile::create([
                'business_id'   => $business->id,
                'name'          => $business->name,
                'primary_color' => '#1d1d1d',
            ]);

            IntegrationSetting::create(['business_id' => $business->id]);

            foreach (['Taglio', 'Piega', 'Colore'] as $i => $name) {
                Service::create([
                    'business_id'      => $business->id,
                    'name'             => $name,
                    'duration_minutes' => 30,
                    'price'            => 20.00,
                    'active'           => true,
                    'featured'         => $i === 0,
                ]);
            }

            return $admin;
        });
    }
}
```

- [ ] **Step 4: Run provisioning tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/BusinessProvisioningServiceTest.php
```

Expected: all PASS.

- [ ] **Step 5: Create SuperAdminPanelProvider**

Create `app/Providers/Filament/SuperAdminPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('superadmin')
            ->path('superadmin')
            ->login()
            ->colors(['primary' => Color::Slate])
            ->brandName('Super Admin')
            ->discoverResources(
                in: app_path('Filament/SuperAdmin/Resources'),
                for: 'App\Filament\SuperAdmin\Resources'
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

- [ ] **Step 6: Create BusinessResource and page classes**

Create `app/Filament/SuperAdmin/Resources/BusinessResource.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Enums\BusinessStatus;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\CreateBusiness;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\EditBusiness;
use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\ListBusinesses;
use App\Models\Business;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;
    protected static ?string $navigationLabel = 'Saloni';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    private const RESERVED = [
        'superadmin', 'admin', 'api', 'www', 'app',
        'mail', 'static', 'assets', 'media', 'webhook', 'health',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->label('Nome salone')
                ->required()
                ->maxLength(255),

            TextInput::make('subdomain')
                ->label('Sottodominio')
                ->required()
                ->maxLength(63)
                ->helperText('Solo lettere minuscole, numeri e trattini.')
                ->rules([
                    'alpha_dash',
                    fn() => function ($attribute, $value, $fail) {
                        if (in_array(strtolower((string) $value), self::RESERVED)) {
                            $fail("Il sottodominio '{$value}' è riservato.");
                        }
                    },
                ]),

            TextInput::make('admin_email')
                ->label('Email admin iniziale')
                ->email()
                ->required()
                ->visibleOn('create'),

            Select::make('status')
                ->label('Stato')
                ->options(['active' => 'Attivo', 'suspended' => 'Sospeso'])
                ->required()
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('subdomain')->label('Sottodominio'),
                TextColumn::make('status')->label('Stato')->badge()
                    ->color(fn(BusinessStatus $state) => match ($state) {
                        BusinessStatus::Active    => 'success',
                        BusinessStatus::Suspended => 'danger',
                    }),
                TextColumn::make('created_at')->label('Creato')->since()->sortable(),
            ])
            ->actions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBusinesses::route('/'),
            'create' => CreateBusiness::route('/create'),
            'edit'   => EditBusiness::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
```

Create `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/ListBusinesses.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\Pages;

use App\Filament\SuperAdmin\Resources\BusinessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBusinesses extends ListRecords
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

Create `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/CreateBusiness.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\Pages;

use App\Filament\SuperAdmin\Resources\BusinessResource;
use App\Services\BusinessProvisioningService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    private string $adminEmail = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->adminEmail = $data['admin_email'];
        unset($data['admin_email']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $admin = (new BusinessProvisioningService())->provision($this->record, $this->adminEmail);

        Notification::make()
            ->title("Salone creato — Admin: {$admin->email} — Password: {$admin->plainPassword}")
            ->success()
            ->persistent()
            ->send();
    }
}
```

Create `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/EditBusiness.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\Pages;

use App\Filament\SuperAdmin\Resources\BusinessResource;
use Filament\Resources\Pages\EditRecord;

class EditBusiness extends EditRecord
{
    protected static string $resource = BusinessResource::class;
}
```

- [ ] **Step 7: Register SuperAdminPanelProvider in bootstrap/providers.php**

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\SuperAdminPanelProvider::class,
];
```

- [ ] **Step 8: Seed super_admin role**

```bash
docker-compose run --rm app php artisan tinker --execute="\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']); echo 'done';"
```

Also add `'super_admin'` to the roles array in `DatabaseSeeder.php` if it exists.

- [ ] **Step 9: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all pass.

- [ ] **Step 10: Commit**

```bash
git add app/Providers/Filament/SuperAdminPanelProvider.php app/Filament/SuperAdmin/ app/Services/BusinessProvisioningService.php bootstrap/providers.php tests/Feature/Services/BusinessProvisioningServiceTest.php
git commit -m "feat: add super-admin panel, BusinessResource, and BusinessProvisioningService"
```

---

### Task 11: Update Jobs + Stripe webhook

**Files:**
- Modify: `app/Jobs/SendAppointmentReminder.php`
- Modify: `app/Jobs/SyncGoogleCalendar.php`
- Modify: `app/Jobs/SendAppointmentConfirmation.php`
- Modify: `app/Jobs/SendCancellationNotification.php`
- Modify: `app/Jobs/NotifyWaitlistCandidateJob.php`
- Modify: `app/Services/PaymentService.php`
- Modify: `app/Http/Controllers/StripeWebhookController.php`

Pattern for all jobs: add `app()->instance('current_business_id', $this->{model}->business_id);` as the first line of `handle()`.

- [ ] **Step 1: Update SendAppointmentReminder**

In `app/Jobs/SendAppointmentReminder.php`, add as the first line of `handle()`:

```php
public function handle(NotificationService $notificationService): void
{
    app()->instance('current_business_id', $this->reminder->business_id);
    // ... rest of method unchanged
```

- [ ] **Step 2: Update SyncGoogleCalendar**

In `app/Jobs/SyncGoogleCalendar.php`, add as the first line of `handle()`:

```php
public function handle(GoogleCalendarService $calendarService): void
{
    app()->instance('current_business_id', $this->appointment->business_id);
    // ... rest of method unchanged
```

- [ ] **Step 3: Update SendAppointmentConfirmation**

In `app/Jobs/SendAppointmentConfirmation.php`, add as the first line of `handle()`:

```php
public function handle(): void
{
    app()->instance('current_business_id', $this->appointment->business_id);
    // ... rest of method unchanged
```

- [ ] **Step 4: Update SendCancellationNotification**

Open `app/Jobs/SendCancellationNotification.php`. Find the constructor to identify the model property name (likely `$this->appointment` or `$this->reminder`). Add as first line of `handle()`:

```php
public function handle(): void
{
    app()->instance('current_business_id', $this->appointment->business_id);
    // ... rest of method unchanged
```

- [ ] **Step 5: Update NotifyWaitlistCandidateJob**

In `app/Jobs/NotifyWaitlistCandidateJob.php`, add as first line of `handle()`:

```php
public function handle(NotificationService $notificationService): void
{
    app()->instance('current_business_id', $this->entry->business_id);
    // ... rest of method unchanged
```

- [ ] **Step 6: Add business_id to Stripe PaymentIntent metadata**

In `app/Services/PaymentService.php`, find the line:

```php
'metadata' => ['appointment_id' => $appointmentId],
```

Change to:

```php
'metadata' => [
    'appointment_id' => $appointmentId,
    'business_id'    => app()->bound('current_business_id') ? app('current_business_id') : null,
],
```

- [ ] **Step 7: Update StripeWebhookController to restore business context**

In `app/Http/Controllers/StripeWebhookController.php`, after the `$event = Webhook::constructEvent(...)` call, add:

```php
$event = Webhook::constructEvent(
    $request->getContent(),
    $request->header('Stripe-Signature', ''),
    $secret,
);

$businessId = $event->data->object?->metadata?->business_id ?? null;
if ($businessId) {
    app()->instance('current_business_id', (int) $businessId);
}

$this->paymentService->handleStripeWebhook($event->toArray());
```

- [ ] **Step 8: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Jobs/ app/Services/PaymentService.php app/Http/Controllers/StripeWebhookController.php
git commit -m "feat: propagate business context in all jobs and Stripe webhook"
```

---

### Task 12: Anti-leakage tests

**Files:**
- Create: `tests/Concerns/WithBusinessContext.php`
- Create: `tests/Feature/MultiTenancy/ModelScopingTest.php`

- [ ] **Step 1: Create WithBusinessContext trait**

Create `tests/Concerns/WithBusinessContext.php`:

```php
<?php

namespace Tests\Concerns;

use App\Models\Business;

trait WithBusinessContext
{
    protected function setBusinessContext(Business $business): void
    {
        app()->instance('current_business_id', $business->id);
    }
}
```

- [ ] **Step 2: Create ModelScopingTest**

Create `tests/Feature/MultiTenancy/ModelScopingTest.php`:

```php
<?php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WaitlistEntry;
use Tests\Concerns\WithBusinessContext;

uses(WithBusinessContext::class);

beforeEach(function () {
    foreach (['admin', 'staff', 'customer'] as $role) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

// --- Appointment ---

it('scopes appointments to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    Appointment::factory()->create(['business_id' => $b1->id]);
    Appointment::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(Appointment::count())->toBe(1);

    $this->setBusinessContext($b2);
    expect(Appointment::count())->toBe(1);
});

it('cannot find appointment from another business by id', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    $apt = Appointment::factory()->create(['business_id' => $b1->id]);

    $this->setBusinessContext($b2);
    expect(Appointment::find($apt->id))->toBeNull();
});

// --- Service ---

it('scopes services to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    Service::factory()->create(['business_id' => $b1->id]);
    Service::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(Service::count())->toBe(1);
});

// --- Payment ---

it('scopes payments to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    Payment::factory()->create(['business_id' => $b1->id]);
    Payment::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(Payment::count())->toBe(1);
});

// --- SystemSetting ---

it('SystemSetting::current() isolates per business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    $this->setBusinessContext($b1);
    $s1 = SystemSetting::current();

    $this->setBusinessContext($b2);
    $s2 = SystemSetting::current();

    expect($s1->id)->not->toBe($s2->id);
    expect($s1->business_id)->toBe($b1->id);
    expect($s2->business_id)->toBe($b2->id);
});

// --- Auto-fill ---

it('auto-fills business_id on new records', function () {
    $business = Business::factory()->create();
    $this->setBusinessContext($business);

    $service = Service::create([
        'name'             => 'Auto Test',
        'duration_minutes' => 30,
        'price'            => 10.00,
        'active'           => true,
        'featured'         => false,
    ]);

    expect($service->business_id)->toBe($business->id);
});

// --- Cross-tenant API ---

it('prevents cross-tenant API access with valid Sanctum token', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $user = User::factory()->create(['business_id' => $b1->id]);
    $user->assignRole('customer');

    app()->instance('current_business_id', $b2->id);

    $this->actingAs($user)
        ->getJson('/api/appointments')
        ->assertForbidden();
});

// --- WaitlistEntry ---

it('scopes waitlist entries to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    WaitlistEntry::factory()->create(['business_id' => $b1->id]);
    WaitlistEntry::factory()->create(['business_id' => $b2->id]);

    $this->setBusinessContext($b1);
    expect(WaitlistEntry::count())->toBe(1);
});
```

- [ ] **Step 3: Run anti-leakage tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/
```

Expected: all PASS.

- [ ] **Step 4: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all pass.

- [ ] **Step 5: Commit**

```bash
git add tests/Concerns/WithBusinessContext.php tests/Feature/MultiTenancy/ModelScopingTest.php
git commit -m "test: add anti-leakage tests for multi-tenancy isolation"
```
