# AGENTS.md

## Commands

```bash
# All commands run inside Docker unless via Makefile

# Tests — ALWAYS use test DB
docker compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
make test-filter filter="test name"
make test                         # uses docker compose exec

# After running tests, ALWAYS re-seed dev DB so the user can log in:
# make artisan cmd="migrate:fresh --seed"

# Artisan
docker compose run --rm app php artisan <cmd>
make artisan cmd="migrate:fresh --seed"
make tinker
make cache-clear

# Queue
make queue-work                   # foreground worker

# Makefile targets work via docker compose exec (not run --rm)
make migrate make migrate-fresh make seed make shell make logs
make npm-install make npm-build

# Deploy
make deploy                       # prod: lock → maintenance → sync → migrate → cache → healthcheck (solo da main)
make deploy-staging               # same flow for staging (solo da staging)
make staging-reset-db             # explicit DB reset (not part of deploy-staging)

# Git workflow
# feature/* → staging (PR) → main (PR after staging validation)
# `make deploy-staging` fallisce se non si è su staging
# `make deploy` fallisce se non si è su main

# New salon
docker compose exec app php artisan app:create-business --name="..." --subdomain=...
# Then add subdomain + SSL in IONOS panel (no wildcard SSL)
```

## Architecture

- **Tenant model:** `Business` (not `Team`/`Organization`). All domain models carry a `business_id` FK and use the `BelongsToBusiness` trait (global scope + auto-fill).
- **Admin panels:** Two Filament panels — `admin` (tenant-aware, `/admin`, `AdminPanelProvider`) and `superadmin` (platform-level, `/superadmin`, `SuperAdminPanelProvider`).
- **Auth:** Sanctum tokens for API; Spatie `HasRoles` with helpers `isAdmin()`, `isStaff()`, `isCustomer()`.
- **Payments:** Stripe Connect per-business (each salon has its own Stripe connected account). `IntegrationSetting` stores encrypted Stripe keys per business.
- **Subscriptions:** Laravel Cashier on the `Business` model (not `User`). Plans in `config/plans.php` (`base`, `plus`). Feature-gating via `PlanFeatureGate`.
- **WhatsApp AI:** Conversation state machine (`WhatsAppConversationState`), AI-driven booking via Anthropic Claude (`config/services.php` model: `claude-haiku-4-5-20251001`).
- **AppointmentObserver** handles side effects on status changes (loyalty, cancellations, reminders, waitlist matching). Do NOT duplicate its logic.
- **Events drive notifications:** `AppointmentConfirmed` / `AppointmentCancelled` → `SendAppointmentNotifications` (email) + `SendWhatsAppAppointmentNotification` + `MatchWaitlistOnCancellation`.
- **Server PHP:** `/usr/bin/php8.5` (not `php` which is 8.3). Composer: `~/composer.phar`.
- **Production queue:** `QUEUE_CONNECTION=sync` — jobs run synchronously. Watch for transaction rollback issues.
- **Scheduler:** defined in `routes/console.php`, triggered via IONOS UnixCron panel.

## Testing

- `RefreshDatabase` globally enabled in `tests/Pest.php`.
- `Pest.php` `beforeEach` creates a `Business` and binds `current_business_id`.
- Roles must exist in DB before `assignRole()` — use `Role::firstOrCreate()` in `beforeEach`.
- Tests use the `WithBusinessContext` concern to rebind business context.
- Feature tests under `tests/Feature/` mirror app structure: `Api/`, `Controllers/Portal/`, `Filament/`, `Services/`, `WhatsApp/`, `Loyalty/`, `MultiTenancy/`.
- phpunit.xml forces `DB_DATABASE=booking_app_test` and `DB_HOST=db` — tests always run against the MySQL test DB.

## Conventions

- **PHP 8 attributes** for `#[Fillable]`, `#[Hidden]` — not `$fillable`/`$hidden` properties.
- **`casts()` method**, not `$casts` property.
- Query scopes return `Builder`, not `void`.
- Factory docblocks: `/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Foo> */`
- Model `HasFactory` docblocks: `/** @use HasFactory<\Database\Factories\FooFactory> */`
- `UserPreference.preferred_staff` FK lacks `_id` suffix — use explicit `'preferred_staff'` in `belongsTo`.

## Key configs

- `config/services.php` — Stripe, Twilio, Anthropic, WhatsApp, Google credentials (not just `.env`).
- `config/plans.php` — plan definitions and feature gates.
- `config/cashier.php` — Cashier maps to `Business` model.
- `config/app.php` — production uses `Europe/Rome`, locale `it`.

## Known quirks

- `SystemSetting` and `IntegrationSetting` are per-business singletons resolved via `::current()` static method. `SystemSetting::platform()` reads global settings (null business_id).
- `StripeClient` resolves secret from `IntegrationSetting` first, falls back to config — do NOT hardcode Stripe keys.
- `Payment::completed()` scope (not `paid()`).
- Migration timestamps are real dates (e.g. `2026_05_08_000012`), not conventional `YYYY_MM_DD_HHMMSS`.
- `SalonProfile` uses Spatie MediaLibrary collections: `logo`, `logo_dark`, `cover`, `favicon`.
- `signed` routes for public confirm/cancel/pay links — use `URL::signedRoute()` with `uid` bound to the user.
