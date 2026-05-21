# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Environment

No local PHP or Composer. All commands run inside Docker:

```bash
# Start services
docker-compose up -d

# Run any artisan/composer/pest command
docker-compose run --rm app <command>

# For commands that don't need the database (composer, npm)
docker-compose run --rm --no-deps app <command>
```

## Common Commands

```bash
# Run full test suite (requires mysql — starts automatically)
docker-compose run --rm app ./vendor/bin/pest

# Run a single test file
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/AppointmentTest.php

# Run a specific test by name
docker-compose run --rm app ./vendor/bin/pest --filter "canBeCancelled"

# Artisan
docker-compose run --rm app php artisan migrate
docker-compose run --rm app php artisan migrate:fresh
docker-compose run --rm app php artisan tinker

# Composer (no DB needed)
docker-compose run --rm --no-deps app composer require vendor/package
```

## Architecture

**Stack:** Laravel 13, PHP 8.4, Filament 4, MySQL 8, Redis 7

**Services (docker-compose):**
- `app` — PHP 8.4-fpm-alpine, serves on port 8000
- `mysql` — database, port 3306 (user: booking, password: secret, db: booking_app)
- `phpmyadmin` — DB browser UI at port 8080
- `redis` — cache + queue driver, host port 6380 (internal 6379)
- `mailpit` — mail catcher, UI at port 8025
- `nginx` — reverse proxy, ports 80/443

**Admin panel:** Filament 4 at `/admin`. Resources/Pages/Widgets are auto-discovered from `app/Filament/`. Panel provider: `app/Providers/Filament/AdminPanelProvider.php`.

**Roles (Spatie Permission):** Three roles — `admin`, `staff`, `customer`. `User` model carries `HasRoles` trait; use `$user->isAdmin()`, `$user->isStaff()`, `$user->isCustomer()` helpers. When writing tests that call `assignRole()`, the role must first exist in the DB — use `Role::firstOrCreate(...)` in `beforeEach`.

## Domain Model

The booking system centers on `Appointment`, which links a customer (`user_id`) and a staff member (`staff_id`) — both FK to `users`. Key relationships:

```
User ──< Appointment (as customer, via user_id)
User ──< Appointment (as staff, via staff_id)
User >──< Service (via service_staff pivot)
User ──< AvailabilityRule
User ──< TimeSlot
User ──1 UserPreference

Appointment ──< AppointmentReminder
Appointment ──1 Payment
Appointment >── Service

TimeSlot >── Appointment (nullable — null means available)
```

`UserPreference.preferred_staff` is an FK to `users` without the standard `_id` suffix — this is intentional from the migration; use `'preferred_staff'` as the explicit FK argument in `belongsTo`.

**Appointment status enum:** `pending` → `confirmed` → `completed` / `cancelled`

**Payment status enum:** `pending`, `completed`, `refunded`, `failed`, `cancelled` — the scope is `Payment::completed()` (not `paid()`).

## Code Conventions

- Use PHP 8 attribute syntax: `#[Fillable([...])]` and `#[Hidden([...])]` — not `$fillable`/`$hidden` properties
- Use `protected function casts(): array` — not `$casts` property
- Query scopes must return `Builder`, not `void`
- Factory classes need `/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Foo> */` docblock
- Model classes need `/** @use HasFactory<\Database\Factories\FooFactory> */` on the `use HasFactory` line
- `RefreshDatabase` is enabled globally in `tests/Pest.php` for all Feature tests
