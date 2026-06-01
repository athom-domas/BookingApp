# Admin Multi-Business Tenancy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow admin users to be linked to multiple businesses via a pivot table and access each in the Filament panel using the tenant switcher.

**Architecture:** Add a `business_user` pivot table. Admin tenancy in Filament uses the pivot (`getTenants()`/`canAccessTenant()`). Staff and customer users continue to use `User.business_id` unchanged. `Business::users()` (existing HasMany) is left alone — a separate `Business::admins()` BelongsToMany is added. A RelationManager in the superadmin `BusinessResource` lets the superadmin attach/detach admins per business.

**Tech Stack:** Laravel 13, Filament 4, Spatie Permission, MySQL 8, PestPHP

---

## File Map

| File | Change |
|------|--------|
| `database/migrations/2026_06_01_100000_create_business_user_table.php` | New |
| `app/Models/User.php` | Add `businesses()`, update `getTenants()`, `canAccessTenant()` |
| `app/Models/Business.php` | Add `admins()` BelongsToMany, add `BelongsToMany` import |
| `app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php` | Branch admin vs staff/customer |
| `app/Listeners/SendAppointmentNotifications.php` | Admin query via pivot |
| `app/Jobs/SendCancellationNotification.php` | Admin query via pivot |
| `app/Services/BusinessProvisioningService.php` | Attach admin to pivot after creation |
| `app/Filament/SuperAdmin/Resources/BusinessResource.php` | Register RelationManager |
| `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/BusinessAdminsRelationManager.php` | New |
| `database/seeders/RolesAndUsersSeeder.php` | syncWithoutDetaching pivot |
| `tests/Feature/MultiTenancy/AdminMultiBusinessTest.php` | New |
| `tests/Feature/MultiTenancy/MiddlewareTest.php` | Add admin middleware tests |

---

### Task 1: Create `business_user` pivot migration

**Files:**
- Create: `database/migrations/2026_06_01_100000_create_business_user_table.php`
- Create: `tests/Feature/MultiTenancy/AdminMultiBusinessTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MultiTenancy/AdminMultiBusinessTest.php`:

```php
<?php

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('business_user pivot table exists and accepts multiple rows per user', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);

    DB::table('business_user')->insert([
        ['business_id' => $b1->id, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => $b2->id, 'user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(DB::table('business_user')->where('user_id', $admin->id)->count())->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "business_user pivot"
```

Expected: FAIL — `Base table or view not found: 1146 Table 'booking_app.business_user' doesn't exist`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_06_01_100000_create_business_user_table.php`:

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
        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        // Backfill existing admin users so they can still access their panel after deploy.
        DB::table('users')
            ->whereNotNull('business_id')
            ->whereIn('id', function ($sub) {
                $sub->select('model_id')
                    ->from('model_has_roles')
                    ->where('model_type', 'App\\Models\\User')
                    ->whereIn('role_id', function ($q) {
                        $q->select('id')->from('roles')
                          ->where('name', 'admin')
                          ->where('guard_name', 'web');
                    });
            })
            ->select('id', 'business_id')
            ->get()
            ->each(fn ($u) => DB::table('business_user')->insertOrIgnore([
                'business_id' => $u->business_id,
                'user_id'     => $u->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
    }
};
```

- [ ] **Step 4: Run the migration**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: `Migrated: 2026_06_01_100000_create_business_user_table (Xms)`

- [ ] **Step 5: Run the test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "business_user pivot"
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_01_100000_create_business_user_table.php \
        tests/Feature/MultiTenancy/AdminMultiBusinessTest.php
git commit -m "feat: add business_user pivot table with admin backfill"
```

---

### Task 2: Add model relationships

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Models/Business.php`

**Context:** `Business::users()` at line 62 is a `HasMany` (all users with `business_id = X`) used in `StripeBillingWebhookController` — leave it untouched. The new BelongsToMany is a separate `admins()` method.

- [ ] **Step 1: Add tests**

Append to `tests/Feature/MultiTenancy/AdminMultiBusinessTest.php`:

```php
it('User::businesses() returns all businesses from pivot', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);

    $admin->businesses()->attach([$b1->id, $b2->id]);

    $businesses = $admin->fresh()->businesses;
    expect($businesses)->toHaveCount(2);
    expect($businesses->pluck('id')->toArray())->toContain($b1->id, $b2->id);
});

it('Business::admins() returns users from pivot', function () {
    $b1    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);

    $admin->businesses()->attach($b1->id);

    expect($b1->fresh()->admins)->toHaveCount(1);
    expect($b1->fresh()->admins->first()->id)->toBe($admin->id);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "BelongsToMany|businesses\(\)|admins\(\)"
```

Expected: FAIL — `Call to undefined method App\Models\User::businesses()`

- [ ] **Step 3: Add `businesses()` to User**

In `app/Models/User.php`, `BelongsToMany` is already imported at line 14. Add after the `business()` method (line 47):

```php
public function businesses(): BelongsToMany
{
    return $this->belongsToMany(Business::class);
}
```

- [ ] **Step 4: Add `admins()` and import to Business**

In `app/Models/Business.php`, add the import after the existing `HasMany`/`HasOne` imports:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

Then add after the existing `users()` HasMany (line 62):

```php
public function admins(): BelongsToMany
{
    return $this->belongsToMany(User::class);
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "businesses|admins"
```

Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/User.php app/Models/Business.php \
        tests/Feature/MultiTenancy/AdminMultiBusinessTest.php
git commit -m "feat: add User::businesses() and Business::admins() pivot relationships"
```

---

### Task 3: Update User tenancy methods

**Files:**
- Modify: `app/Models/User.php`

**Context:** `getTenants(Panel $panel)` (line 52) currently returns `collect([$this->business])` for all non-super_admin users. `canAccessTenant(Model $tenant)` (line 57) checks `$this->business_id === $tenant->getKey()` for all non-super_admin users.

- [ ] **Step 1: Add tests**

Append to `tests/Feature/MultiTenancy/AdminMultiBusinessTest.php`:

```php
it('canAccessTenant returns true for admin linked to business via pivot', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach([$b1->id, $b2->id]);

    expect($admin->canAccessTenant($b1))->toBeTrue();
    expect($admin->canAccessTenant($b2))->toBeTrue();
});

it('canAccessTenant returns false for admin not linked to business', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($b1->id);

    expect($admin->canAccessTenant($b2))->toBeFalse();
});

it('canAccessTenant uses business_id for staff (not pivot)', function () {
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $staff = User::factory()->create(['business_id' => $b1->id]);
    $staff->assignRole('staff');

    expect($staff->canAccessTenant($b1))->toBeTrue();
    expect($staff->canAccessTenant($b2))->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "canAccessTenant"
```

Expected: FAIL — `canAccessTenant` currently uses `business_id` for everyone; admin assigned to b2 via pivot would return false for b2.

- [ ] **Step 3: Update `getTenants()` in `app/Models/User.php`**

Replace lines 52–55 (the existing `getTenants()` method):

```php
public function getTenants(Panel $panel): Collection
{
    if ($this->isAdmin()) {
        return $this->businesses;
    }
    return $this->business ? collect([$this->business]) : collect();
}
```

- [ ] **Step 4: Update `canAccessTenant()` in `app/Models/User.php`**

Replace lines 57–64 (the existing `canAccessTenant()` method):

```php
public function canAccessTenant(Model $tenant): bool
{
    if ($this->hasRole('super_admin')) {
        return false;
    }
    if ($this->isAdmin()) {
        return $this->businesses()->where('businesses.id', $tenant->getKey())->exists();
    }
    return $this->business_id !== null && $this->business_id === $tenant->getKey();
}
```

- [ ] **Step 5: Run new tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "canAccessTenant"
```

Expected: PASS (3 tests)

- [ ] **Step 6: Run full test suite for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all existing tests pass

- [ ] **Step 7: Commit**

```bash
git add app/Models/User.php tests/Feature/MultiTenancy/AdminMultiBusinessTest.php
git commit -m "feat: use pivot for admin getTenants() and canAccessTenant()"
```

---

### Task 4: Update EnsureUserBelongsToCurrentBusiness middleware

**Files:**
- Modify: `app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php`
- Modify: `tests/Feature/MultiTenancy/MiddlewareTest.php`

**Context:** The existing middleware at `app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php` checks `$user->business_id !== Business::currentId()` for all users. It needs to check the pivot for admins.

- [ ] **Step 1: Add middleware tests for admin**

Append to `tests/Feature/MultiTenancy/MiddlewareTest.php` (add imports at top of file if missing):

```php
use Spatie\Permission\Models\Role;
```

Then append the tests:

```php
it('EnsureUserBelongsToCurrentBusiness allows admin linked to business via pivot', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($b2->id);
    app()->instance('current_business_id', $b2->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn () => $admin);

    $called = false;
    (new EnsureUserBelongsToCurrentBusiness())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
});

it('EnsureUserBelongsToCurrentBusiness blocks admin not linked via pivot', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($b1->id);
    app()->instance('current_business_id', $b2->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn () => $admin);

    expect(fn () => (new EnsureUserBelongsToCurrentBusiness())->handle($request, fn () => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
```

- [ ] **Step 2: Run new tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/MiddlewareTest.php --filter "admin"
```

Expected: FAIL — admin linked to b2 via pivot (not via `business_id`) would be blocked.

- [ ] **Step 3: Rewrite the middleware**

Replace the full content of `app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php`:

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
        $user = $request->user();

        if ($user) {
            $currentId = Business::currentId();

            if ($user->isAdmin()) {
                if (! $user->businesses()->where('businesses.id', $currentId)->exists()) {
                    abort(403);
                }
            } elseif ($user->business_id !== $currentId) {
                abort(403);
            }
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Run all middleware tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/MiddlewareTest.php
```

Expected: all tests pass (4 existing + 2 new = 6 total)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php \
        tests/Feature/MultiTenancy/MiddlewareTest.php
git commit -m "feat: middleware checks pivot for admin business access"
```

---

### Task 5: Update notification queries

**Files:**
- Modify: `app/Listeners/SendAppointmentNotifications.php`
- Modify: `app/Jobs/SendCancellationNotification.php`

**Context:** Both files currently use `User::role('admin')->where('business_id', $businessId)`. An admin assigned as secondary to a business has a pivot record but may have a different `business_id`, so they'd be missed. We switch to `whereHas('businesses', ...)`.

- [ ] **Step 1: Add notification scoping tests**

Append to `tests/Feature/MultiTenancy/AdminMultiBusinessTest.php`:

```php
it('admin assigned as secondary to a business appears in pivot-scoped admin query', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    $primaryAdmin = User::factory()->create(['business_id' => $b1->id]);
    $primaryAdmin->assignRole('admin');
    $primaryAdmin->businesses()->attach($b1->id);

    $secondaryAdmin = User::factory()->create(['business_id' => $b2->id]);
    $secondaryAdmin->assignRole('admin');
    $secondaryAdmin->businesses()->attach([$b1->id, $b2->id]);

    $admins = User::role('admin')
        ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $b1->id))
        ->get();

    expect($admins)->toHaveCount(2);
    expect($admins->pluck('id')->toArray())->toContain($primaryAdmin->id, $secondaryAdmin->id);
});

it('admin not linked to business is excluded from pivot-scoped notification query', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($b1->id);

    $admins = User::role('admin')
        ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $b2->id))
        ->get();

    expect($admins)->toHaveCount(0);
});
```

- [ ] **Step 2: Run to verify tests pass (the query pattern is already valid)**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "notification"
```

Expected: PASS — these tests verify the query pattern we're about to use in production code.

- [ ] **Step 3: Update SendAppointmentNotifications**

In `app/Listeners/SendAppointmentNotifications.php`, replace line 20:

```php
// Before
$admins = User::role('admin')->where('business_id', $event->appointment->business_id)->get();

// After
$admins = User::role('admin')
    ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $event->appointment->business_id))
    ->get();
```

- [ ] **Step 4: Update SendCancellationNotification**

In `app/Jobs/SendCancellationNotification.php`, replace line 38:

```php
// Before
$admins = User::role('admin')->where('business_id', $this->appointment->business_id)->get();

// After
$admins = User::role('admin')
    ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $this->appointment->business_id))
    ->get();
```

- [ ] **Step 5: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass

- [ ] **Step 6: Commit**

```bash
git add app/Listeners/SendAppointmentNotifications.php \
        app/Jobs/SendCancellationNotification.php \
        tests/Feature/MultiTenancy/AdminMultiBusinessTest.php
git commit -m "feat: scope admin notification queries via business_user pivot"
```

---

### Task 6: Update provisioning and seeders

**Files:**
- Modify: `app/Services/BusinessProvisioningService.php`
- Modify: `database/seeders/RolesAndUsersSeeder.php`

- [ ] **Step 1: Add provisioning test**

Append to `tests/Feature/MultiTenancy/AdminMultiBusinessTest.php`:

```php
it('BusinessProvisioningService attaches new admin to pivot', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    $admin = (new \App\Services\BusinessProvisioningService())->provision($business, 'newadmin@test.com');

    expect($admin->businesses()->where('businesses.id', $business->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "BusinessProvisioningService"
```

Expected: FAIL — no pivot record is created yet.

- [ ] **Step 3: Update BusinessProvisioningService**

In `app/Services/BusinessProvisioningService.php`, add `$admin->businesses()->attach($business->id);` after `$admin->assignRole('admin');` (line 29).

The block (lines 22–31) becomes:

```php
$admin = User::create([
    'name'                 => 'Admin',
    'email'                => $adminEmail,
    'password'             => Hash::make($tempPassword),
    'business_id'          => $business->id,
    'must_change_password' => true,
]);
$admin->assignRole('admin');
$admin->businesses()->attach($business->id);
$admin->plainPassword = $tempPassword;
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/MultiTenancy/AdminMultiBusinessTest.php --filter "BusinessProvisioningService"
```

Expected: PASS

- [ ] **Step 5: Update RolesAndUsersSeeder**

In `database/seeders/RolesAndUsersSeeder.php`, add `$admin->businesses()->syncWithoutDetaching([app('current_business_id')]);` after `$admin->syncRoles(['admin']);` (line 17).

Full updated `run()` method:

```php
public function run(string $adminName, string $adminEmail): void
{
    $admin = User::updateOrCreate(
        ['email' => $adminEmail],
        ['name' => $adminName, 'password' => Hash::make('password'), 'business_id' => app('current_business_id')]
    );
    $admin->syncRoles(['admin']);
    $admin->businesses()->syncWithoutDetaching([app('current_business_id')]);
}
```

`syncWithoutDetaching` is used instead of `attach` because the seeder uses `updateOrCreate` — the admin might already exist from a previous run and already have a pivot record.

- [ ] **Step 6: Run fresh seed to verify**

```bash
docker-compose run --rm app php artisan migrate:fresh --seed
```

Expected: completes without errors.

- [ ] **Step 7: Verify pivot records were created**

```bash
docker-compose run --rm app php artisan tinker --execute="echo App\Models\User::where('email','admin@rossini.test')->first()->businesses()->count();"
```

Expected: `1`

- [ ] **Step 8: Commit**

```bash
git add app/Services/BusinessProvisioningService.php \
        database/seeders/RolesAndUsersSeeder.php \
        tests/Feature/MultiTenancy/AdminMultiBusinessTest.php
git commit -m "feat: populate pivot on provisioning and seeding"
```

---

### Task 7: SuperAdmin RelationManager UI

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/BusinessAdminsRelationManager.php`
- Modify: `app/Filament/SuperAdmin/Resources/BusinessResource.php`

**Context:** The pattern for a RelationManager in this codebase is in `app/Filament/Resources/CustomerResource/RelationManagers/AppointmentsRelationManager.php`. Filament 4 RelationManagers use `Filament\Actions\AttachAction` and `Filament\Actions\DetachAction` for BelongsToMany. The `form()` method is required by the abstract class but not used for attach-only workflows.

- [ ] **Step 1: Create the RelationManager**

Create `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/BusinessAdminsRelationManager.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers;

use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessAdminsRelationManager extends RelationManager
{
    protected static string $relationship = 'admins';

    protected static ?string $title = 'Admin';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('business.name')->label('Business principale'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->role('admin'))
                    ->recordSelectSearchColumns(['name', 'email']),
            ])
            ->actions([
                DetachAction::make(),
            ]);
    }
}
```

- [ ] **Step 2: Register RelationManager in BusinessResource**

In `app/Filament/SuperAdmin/Resources/BusinessResource.php`, add after `getPages()`:

```php
public static function getRelationManagers(): array
{
    return [
        \App\Filament\SuperAdmin\Resources\BusinessResource\RelationManagers\BusinessAdminsRelationManager::class,
    ];
}
```

- [ ] **Step 3: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 4: Manual browser verification**

Run the app (`docker-compose up -d`) and verify:

1. Go to `/admin/superadmin/businesses`, click Edit on any business.
2. Verify an "Admin" tab appears alongside the existing form tabs.
3. Click "Attach" in the Admin tab — verify the searchable dropdown lists admin users by name/email.
4. Attach the rossini admin to the chic business.
5. Verify the admin appears in the list.
6. Log in as `admin@rossini.test` at the rossini admin panel — verify the tenant switcher (top-left, next to brand name) now shows both Rossini and Chic entries.
7. Click Chic in the switcher — verify you land on Chic's panel with correct data.
8. Back in superadmin, Detach the rossini admin from chic — verify the row disappears from the list.
9. Verify the rossini admin can no longer switch to chic.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/BusinessAdminsRelationManager.php \
        app/Filament/SuperAdmin/Resources/BusinessResource.php
git commit -m "feat: add BusinessAdminsRelationManager to superadmin panel"
```
