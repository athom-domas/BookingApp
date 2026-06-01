# Admin Multi-Business Tenancy Design

## Goal

Allow an admin user to be associated with multiple businesses and access each of them in the Filament admin panel, without changing how staff and customer users work.

## Architecture

Add a `business_user` pivot table for admin multi-tenancy. Staff and customers continue to use `User.business_id` (one business, unchanged). Admin users use the pivot for Filament tenant access. `User.business_id` on admin users remains as their "home business" (the one they were originally created in) but no longer gates panel access.

The superadmin assigns admins to additional businesses via a RelationManager inside `BusinessResource`. Admins cannot create new businesses.

## Tech Stack

Laravel 13, Filament 4, Spatie Permission, MySQL 8

---

## Database

### New table: `business_user`

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED PK | |
| `business_id` | BIGINT UNSIGNED FK | → `businesses.id` CASCADE DELETE |
| `user_id` | BIGINT UNSIGNED FK | → `users.id` CASCADE DELETE |
| `created_at` | TIMESTAMP NULL | |
| `updated_at` | TIMESTAMP NULL | |

Unique constraint on `(business_id, user_id)`.

No `role` column — roles are managed via Spatie Permission on `model_has_roles`.

### `users` table

No structural change. `business_id` stays as-is (home business for all user types).

---

## Model Changes

### `User`

New relationship:
```php
public function businesses(): BelongsToMany
{
    return $this->belongsToMany(Business::class);
}
```

Updated `getTenants()`:
```php
public function getTenants(Panel $panel): Collection
{
    if ($this->isAdmin()) {
        return $this->businesses;
    }
    return $this->business ? collect([$this->business]) : collect();
}
```

Updated `canAccessTenant()`:
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

### `Business`

New relationship (used by RelationManager):
```php
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class);
}
```

---

## Middleware

### `EnsureUserBelongsToCurrentBusiness`

Used by portal routes (customer/staff/admin). Updated to check pivot for admins:

```php
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
```

---

## Notification Queries

Two locations that currently use `User::role('admin')->where('business_id', ...)` must be updated to use the pivot, so that admins assigned to a business as a secondary get notifications for it too.

**`app/Listeners/SendAppointmentNotifications.php`** and **`app/Jobs/SendCancellationNotification.php`:**

```php
// Before
User::role('admin')->where('business_id', $businessId)->get()

// After
User::role('admin')
    ->whereHas('businesses', fn($q) => $q->where('businesses.id', $businessId))
    ->get()
```

---

## Provisioning & Seeders

### `BusinessProvisioningService::provision()`

After `$admin->assignRole('admin')`, attach to pivot:
```php
$admin->businesses()->attach($business->id);
```

### `RolesAndUsersSeeder`

After `$admin->syncRoles(['admin'])`, sync pivot (idempotent):
```php
$admin->businesses()->syncWithoutDetaching([app('current_business_id')]);
```

---

## SuperAdmin UI

### New file: `BusinessAdminsRelationManager`

Path: `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/BusinessAdminsRelationManager.php`

Registered on `BusinessResource::getRelationManagers()`. Appears as a tab on the Edit page of a business.

**Table columns:** name, email, home business (`business_id` → `business.name`)

**Actions:**
- `AttachAction` — searchable Select on `User::role('admin')`, shows name + email
- `DetachAction` — removes pivot record only, does not delete the user
- No `CreateAction` — creating a new admin from scratch still belongs to the main business provisioning flow

### `BusinessResource`

Add `getRelationManagers()`:
```php
public static function getRelationManagers(): array
{
    return [BusinessAdminsRelationManager::class];
}
```

---

## File Map

| File | Change |
|------|--------|
| `database/migrations/2026-06-01-create-business-user-table.php` | New |
| `app/Models/User.php` | Add `businesses()`, update `getTenants()`, `canAccessTenant()` |
| `app/Models/Business.php` | Add `users()` |
| `app/Http/Middleware/EnsureUserBelongsToCurrentBusiness.php` | Branch admin vs staff/customer |
| `app/Services/BusinessProvisioningService.php` | Attach to pivot after user creation |
| `app/Listeners/SendAppointmentNotifications.php` | Admin query via pivot |
| `app/Jobs/SendCancellationNotification.php` | Admin query via pivot |
| `app/Filament/SuperAdmin/Resources/BusinessResource.php` | Register RelationManager |
| `app/Filament/SuperAdmin/Resources/BusinessResource/RelationManagers/BusinessAdminsRelationManager.php` | New |
| `database/seeders/RolesAndUsersSeeder.php` | syncWithoutDetaching pivot |

---

## Testing

- Admin with one business: panel access unchanged
- Admin assigned to two businesses: tenant switcher shows both, can access both
- Admin detached from a business: can no longer access it
- Staff/customer: unaffected by pivot changes
- Notification: admin assigned to secondary business receives email for that business's appointments
- Seeder: `migrate:fresh --seed` produces correct pivot records for both businesses
