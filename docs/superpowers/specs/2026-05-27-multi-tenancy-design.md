# Multi-Tenancy Design

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform the single-tenant salon management system into a multi-tenant SaaS where each salon operates on its own subdomain (`salone.tuogestionale.it`) with logically isolated data per tenant via `business_id`, global scopes, middleware, and anti-leakage tests.

**Architecture:** Single database with `business_id` column on all tenant tables. Filament 4 native tenancy (`->tenantDomain()`) handles admin panel scoping. A custom `SubdomainMiddleware` handles scoping for public routes and APIs. A separate super-admin Filament panel allows creating and managing salons.

**Security note:** Isolation is logical, not physical. A query without scope, a `withoutGlobalScopes()` call, or a non-tenant-aware validation can leak cross-tenant data. Filament itself states that multi-tenancy security is the application's responsibility. All CRUD actions must have anti-leakage tests.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4 tenancy, MySQL 8, Spatie Permission

---

## Database

### New table: `businesses`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `name` | string | Display name, e.g. "Salone Rosa" |
| `subdomain` | string unique | URL slug, e.g. `salone-rosa` → `salone-rosa.tuogestionale.it` |
| `status` | enum: `active`, `suspended` | Suspended tenants get a 503 response |
| `timestamps` | | |

### Tables receiving `business_id`

`users` gets `business_id BIGINT UNSIGNED NULL` (nullable — super-admin users have `NULL`).

All other tenant tables get `business_id BIGINT UNSIGNED NOT NULL` with a FK to `businesses.id`:

- `users` — nullable (see above)
- `services`
- `appointments`
- `availability_rules`
- `time_slots`
- `appointment_reminders`
- `payments`
- `user_preferences`
- `system_settings`
- `salon_profiles`
- `salon_reviews`
- `waitlist_entries`
- `integration_settings`

### Tables NOT modified (shared/central)

`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `media`, `cache`, `jobs`, `job_batches`, `failed_jobs`, `personal_access_tokens`, `sessions`

### Singleton table unique constraints

`system_settings`, `salon_profiles`, `integration_settings` each get `UNIQUE (business_id)` — only one record per business.

### Composite indexes

| Table | Index |
|-------|-------|
| `appointments` | `(business_id, scheduled_date)`, `(business_id, status)`, `(business_id, user_id)` |
| `users` | `(business_id, email)` — unique-per-tenant email |
| `services` | `(business_id, active)` |
| `waitlist_entries` | `(business_id, status)` |
| `payments` | `(business_id, status)` |
| All tables | `(business_id, created_at)` |

### Migration strategy

Multi-step to be safe in production:

1. Create `businesses` table; insert `id=1, name='Salone', subdomain='salone', status='active'`
2. Add `business_id BIGINT UNSIGNED NULL` (nullable, no FK yet) + index to each table
3. `UPDATE <table> SET business_id = 1` in chunks of 1000 rows
4. Verify row counts match
5. Add FK constraint → `businesses.id`
6. For all tables except `users`: add `NOT NULL` constraint
7. Add composite indexes
8. Add `UNIQUE (business_id)` on singleton tables

---

## Reserved subdomains

Cannot be registered as salon subdomains. Validated in `BusinessResource` form:

`superadmin`, `admin`, `api`, `www`, `app`, `mail`, `static`, `assets`, `media`, `webhook`, `health`

---

## Environment configuration

```dotenv
# .env / .env.example
APP_BASE_DOMAIN=tuogestionale.it
SESSION_DOMAIN=null               # null in local/staging (host-scoped)
                                  # .tuogestionale.it in production (subdomain-shared)
TRUSTED_HOSTS=\.tuogestionale\.it$
```

`config/app.php`:
```php
'base_domain' => env('APP_BASE_DOMAIN', 'tuogestionale.it'),
```

`config/session.php`:
```php
'domain' => env('SESSION_DOMAIN', null),
```

---

## Root domain routing

`tuogestionale.it` (root, no subdomain) redirects to the super-admin panel and exposes a health endpoint:

```php
// routes/web.php — applied only when no subdomain middleware is active
Route::get('/', fn() => redirect('/superadmin'));
Route::get('/health', fn() => response('OK'));
```

These routes are registered outside the `SubdomainMiddleware` group.

---

## Model: `Business`

```php
#[Fillable(['name', 'subdomain', 'status'])]
class Business extends Model
{
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

    public function users(): HasMany      { return $this->hasMany(User::class); }
    public function services(): HasMany   { return $this->hasMany(Service::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    public function systemSetting(): HasOne  { return $this->hasOne(SystemSetting::class); }
    public function salonProfile(): HasOne   { return $this->hasOne(SalonProfile::class); }
    public function integrationSetting(): HasOne { return $this->hasOne(IntegrationSetting::class); }
}
```

`BusinessStatus` enum: `Active`, `Suspended`.

---

## Model: `User` — HasTenants interface

```php
class User extends Authenticatable implements FilamentUser, HasTenants
{
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

        return $this->business_id !== null
            && $this->business_id === $tenant->getKey();
    }
}
```

---

## Trait: `BelongsToBusiness`

Applied to all tenant models except `User`:

```php
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

Applied to: `Service`, `Appointment`, `AvailabilityRule`, `TimeSlot`, `AppointmentReminder`, `Payment`, `UserPreference`, `SystemSetting`, `SalonProfile`, `SalonReview`, `WaitlistEntry`, `IntegrationSetting`.

**`withoutGlobalScopes()` must only appear in `BusinessProvisioningService` and migration scripts.**

---

## Singleton model updates

```php
// SystemSetting
public static function current(): self
{
    return self::firstOrCreate(
        ['business_id' => Business::currentId()],
        [
            'slot_granularity_minutes'    => 15,
            'booking_max_days_ahead'      => 30,
            'cancellation_deadline_hours' => 24,
            'reminder_count'              => 1,
            'reminder_1_hours'            => 24,
            'payment_mode'                => 'both',
        ]
    );
}

// SalonProfile and IntegrationSetting
public static function current(): self
{
    return self::firstOrCreate(['business_id' => Business::currentId()]);
}
```

---

## SubdomainMiddleware

```php
class SubdomainMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseDomain = config('app.base_domain');
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

Laravel `TrustedHosts` configured to `['\.tuogestionale\.it$']`. Registered on all `web` and `api` route groups. Not applied to `/superadmin` or root domain routes.

---

## EnsureUserBelongsToCurrentBusiness middleware

Prevents cross-tenant access using valid session tokens from another tenant:

```php
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

If a user is removed from a salon, their existing tokens remain valid until they hit this middleware — which returns 403. Token deletion on user removal is post-MVP (audit log).
```

Applied after `SubdomainMiddleware` and `auth` on all tenant-authenticated routes (web portal, API).

---

## EnforceTenantStatus middleware

Logs out and returns 503 if the user's business is suspended after they are already authenticated:

```php
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

Runs after `auth` middleware on all tenant-authenticated routes.

---

## Filament Admin Panel (tenant-aware)

`AdminPanelProvider` updated:

```php
$panel
    ->tenant(Business::class, slugAttribute: 'subdomain')
    ->tenantDomain('{tenant:subdomain}.tuogestionale.it')
    ->tenantMiddleware([SubdomainMiddleware::class], isPersistent: true)
```

**Validation:** Standard Laravel `unique()` and `exists()` do not respect Eloquent global scopes. Use `->scopedUnique()` and `->scopedExists()` in all Resource forms where uniqueness is tenant-scoped (service names, user emails).

---

## Filament Resource policies

Each Resource must declare a Policy that enforces tenant membership and role requirements. Example for `ServiceResource`:

```php
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->business_id === Business::currentId()
            && $user->hasAnyRole(['admin', 'staff']);
    }

    public function create(User $user): bool
    {
        return $user->business_id === Business::currentId()
            && $user->hasRole('admin');
    }

    public function update(User $user, Service $service): bool
    {
        return $user->business_id === Business::currentId()
            && $user->hasRole('admin')
            && $service->business_id === Business::currentId();
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
```

All Policies are registered via `Gate::guessPoliciesForModels()` in `AuthServiceProvider`. The `super_admin` role must never be assignable from any tenant panel form.

---

## Super-admin Filament Panel

```php
$panel
    ->id('superadmin')
    ->path('superadmin')
    ->authGuard('web')
    ->login()
    ->resources([BusinessResource::class])
```

Access restricted via `canAccessPanel()` to users with the `super_admin` Spatie role.

### BusinessResource

- **List**: name, subdomain, status, created_at
- **Create**: name + subdomain (validates against reserved list) + admin email; on `afterCreate()` calls `BusinessProvisioningService`
- **Edit**: name, subdomain, status (suspend/reactivate)

The super-admin provides the initial admin email at creation time. `BusinessProvisioningService` creates the admin user with that email.

---

## Spatie Permission: roles

Roles are global (not per-tenant):

| Role | Description |
|------|-------------|
| `super_admin` | Platform owner. Only `/superadmin`. Never shown in tenant panel. |
| `admin` | Salon admin. Manages their own salon. |
| `staff` | Salon operator. |
| `customer` | End customer. |

Rules:
- Tenant panel user management must never expose or allow assignment of `super_admin`
- Role escalation from tenant panel is forbidden (enforced by policies)
- For per-tenant custom roles in future: evaluate Spatie `teams` mode

---

## BusinessProvisioningService

```php
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

        SystemSetting::create([
            'business_id'                 => $business->id,
            'slot_granularity_minutes'    => 15,
            'booking_max_days_ahead'      => 30,
            'cancellation_deadline_hours' => 24,
            'reminder_count'              => 1,
            'reminder_1_hours'            => 24,
            'payment_mode'                => 'both',
        ]);

        SalonProfile::create([
            'business_id' => $business->id,
            'name'        => $business->name,
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
```

The temp password is returned to the super-admin UI for display. The admin user has `must_change_password = true`.

---

## Queue / Job tenant context

Every tenant-aware job receives `business_id` as a constructor argument and binds it before any model query:

```php
class SendAppointmentReminderJob implements ShouldQueue
{
    public function __construct(
        private readonly int $appointmentId,
        private readonly int $businessId,
    ) {}

    public function handle(): void
    {
        app()->instance('current_business_id', $this->businessId);

        $appointment = Appointment::findOrFail($this->appointmentId);
        // queries are now tenant-scoped
    }
}
```

---

## Email configuration

Single SMTP sender for the entire platform. Business name appears in the "From name":

```php
// In Mailable or via config at send time
Mail::from('noreply@tuogestionale.it', $business->name)
    ->send(new AppointmentConfirmation($appointment));
```

No per-tenant SMTP or reply-to subdomains. Reply-to is omitted or set to `noreply@tuogestionale.it`.

---

## Stripe webhook tenant routing

`business_id` is stored in Stripe PaymentIntent metadata at creation time so the webhook can restore tenant context without relying on the subdomain:

```php
// At PaymentIntent creation
\Stripe\PaymentIntent::create([
    'amount'   => $amountCents,
    'currency' => 'eur',
    'metadata' => ['business_id' => Business::currentId()],
]);

// In StripeWebhookController
$businessId = $event->data->object->metadata->business_id ?? null;
if (! $businessId) {
    return response()->json(['error' => 'Missing business context'], 400);
}
app()->instance('current_business_id', (int) $businessId);
```

The Stripe webhook route is on the root domain (not subdomain), so `SubdomainMiddleware` does not run for it.

---

## Cache

All application cache keys must include `business_id`:

```php
Cache::remember("slots.{$businessId}.{$date}", $ttl, fn() => ...);
```

---

## Session and cookie domain

Sessions are host-scoped per subdomain in local/staging (`SESSION_DOMAIN=null`). In production set `SESSION_DOMAIN=.tuogestionale.it` only if sharing session across subdomains is intentional (not recommended).

---

## Media

The `media` table has no `business_id`. Access is always through the owning tenant-scoped model. Download/serving routes must load the model through a tenant-scoped query before serving the file — never directly from media ID without checking ownership.

---

## Anti-leakage tests (required before shipping)

Tests live in `tests/Feature/MultiTenancy/`. All tests use `RefreshDatabase`.

Shared trait:

```php
// tests/Concerns/WithBusinessContext.php
trait WithBusinessContext
{
    protected function setBusinessContext(Business $business): void
    {
        app()->instance('current_business_id', $business->id);
    }
}
```

Minimum coverage per main model (`Appointment`, `Service`, `User`, `Payment`, `WaitlistEntry`, `SystemSetting`):

```php
it('scopes model to current business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();

    Appointment::factory()->create(['business_id' => $b1->id]);
    Appointment::factory()->create(['business_id' => $b2->id]);

    app()->instance('current_business_id', $b1->id);

    expect(Appointment::count())->toBe(1);
});

it('prevents cross-tenant API access with valid token', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    $user = User::factory()->create(['business_id' => $b1->id]);

    // user from b1 hits an endpoint that sets context to b2
    app()->instance('current_business_id', $b2->id);

    $this->actingAs($user)
        ->getJson('/api/appointments')
        ->assertForbidden();
});
```

---

## Nginx / DNS

- Wildcard DNS: `*.tuogestionale.it` → server IP
- Nginx `server_name *.tuogestionale.it tuogestionale.it`
- Laravel `TrustedHosts`: `['\.tuogestionale\.it$']`
- Reserved subdomains validated in `BusinessResource`

---

## Out of Scope

- Self-service tenant registration (super-admin only)
- Per-tenant billing / subscriptions
- Custom full domains per salon
- Data export / tenant deletion / GDPR erasure (post-MVP)
- Per-tenant rate limiting (post-MVP — `RateLimiter::for('tenant-api', fn() => Limit::perMinute(120)->by(Business::currentId()))`)
- Audit log + token revocation on user removal (post-MVP)
- Per-tenant timezone / locale (post-MVP)
- Spatie teams mode for per-tenant custom roles (post-MVP)
- Read replica for cross-tenant analytics (post-MVP)
- Per-tenant feature flags (post-MVP — `$business->features()->pluck('key')`)
