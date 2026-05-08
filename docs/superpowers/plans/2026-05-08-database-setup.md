# Database & Dependencies Setup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install all required dependencies (Filament, Spatie Permission, Stripe, Twilio, Google API, Pest) and create the full database schema for the booking management system.

**Architecture:** Laravel 13 app running in Docker with PostgreSQL and Redis. All PHP/Artisan commands run via `docker-compose run --rm`. Migration files are created as standard Laravel migration classes, each responsible for one domain table.

**Tech Stack:** Laravel 13, Filament 3.2, Spatie Laravel Permission 6, PostgreSQL 15, Redis 7, Docker Compose

---

## File Map

**New migrations:**
- `database/migrations/2026_05_08_000010_create_services_table.php`
- `database/migrations/2026_05_08_000011_create_availability_rules_table.php`
- `database/migrations/2026_05_08_000012_create_appointments_table.php`
- `database/migrations/2026_05_08_000013_create_time_slots_table.php`
- `database/migrations/2026_05_08_000014_create_appointment_reminders_table.php`
- `database/migrations/2026_05_08_000015_create_payments_table.php`
- `database/migrations/2026_05_08_000016_create_service_staff_table.php`
- `database/migrations/2026_05_08_000017_create_user_preferences_table.php`

**Modified:**
- `composer.json` — new require/require-dev entries (via `composer require`)
- `.env` — APP_KEY regenerated
- `config/filament.php` — published by filament:install
- `app/Providers/Filament/AdminPanelProvider.php` — created by filament:install

---

### Task 1: Generate APP_KEY

**Files:**
- Modify: `.env`

- [ ] **Step 1: Run key:generate**

```bash
cd /Users/domas/Progetti/gestionale-prenotazioni
docker-compose run --rm --no-deps app php artisan key:generate
```

Expected output: `Application key set successfully.`

- [ ] **Step 2: Verify .env was updated**

```bash
grep APP_KEY .env
```

Expected: `APP_KEY=base64:[some-64-char-string]` (not `change_me_after_generation`)

---

### Task 2: Install Production Dependencies

**Files:**
- Modify: `composer.json`, `composer.lock`, `vendor/`

- [ ] **Step 1: Install Filament and Spatie packages**

```bash
docker-compose run --rm --no-deps app composer require \
  "filament/filament:^3.2" \
  "spatie/laravel-permission:^6.0" \
  "spatie/laravel-query-builder" \
  "spatie/laravel-data" \
  --no-interaction
```

Expected: `Package operations: N installs` with no errors.

- [ ] **Step 2: Install payment and integration packages**

```bash
docker-compose run --rm --no-deps app composer require \
  "stripe/stripe-php" \
  "twilio/sdk" \
  "google/apiclient:^2.0" \
  --no-interaction
```

Expected: `Package operations: N installs` with no errors.

---

### Task 3: Install Dev Dependencies (Pest)

**Files:**
- Modify: `composer.json`, `composer.lock`, `vendor/`

- [ ] **Step 1: Install Pest**

```bash
docker-compose run --rm --no-deps app composer require \
  --dev \
  "pestphp/pest:^3.0" \
  "pestphp/pest-plugin-laravel:^3.0" \
  --no-interaction
```

Expected: `Package operations: N installs` with no errors.

- [ ] **Step 2: Initialize Pest**

```bash
docker-compose run --rm --no-deps app ./vendor/bin/pest --init
```

Expected: `✓ Pest initialized successfully.`

This creates `tests/Pest.php` and converts the test bootstrap. If the command prompts for anything, it means it already ran — skip.

---

### Task 4: Start Database Services

- [ ] **Step 1: Start postgres and redis**

```bash
docker-compose up -d postgres redis
```

Expected output includes `booking_postgres ... Started` and `booking_redis ... Started`

- [ ] **Step 2: Wait for postgres to be healthy**

```bash
docker-compose ps
```

Expected: postgres STATUS shows `healthy`. If not yet healthy, wait 15 seconds and retry.

---

### Task 5: Install Filament Admin Panel

**Files:**
- Create: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `bootstrap/app.php` or `config/app.php`

- [ ] **Step 1: Run filament:install**

```bash
docker-compose run --rm app php artisan filament:install --panels --no-interaction
```

Expected: Creates `app/Providers/Filament/AdminPanelProvider.php` and registers it.

- [ ] **Step 2: Verify AdminPanelProvider was created**

```bash
ls app/Providers/Filament/
```

Expected: `AdminPanelProvider.php` present.

---

### Task 6: Publish Spatie Permission Config and Migrations

**Files:**
- Create: `config/permission.php`
- Create: `database/migrations/[timestamp]_create_permission_tables.php`

- [ ] **Step 1: Publish Spatie Permission files**

```bash
docker-compose run --rm app php artisan vendor:publish \
  --provider="Spatie\Permission\PermissionServiceProvider" \
  --no-interaction
```

Expected: `Publishing complete.`

- [ ] **Step 2: Verify published files**

```bash
ls database/migrations/ | grep permission
ls config/ | grep permission
```

Expected: one migration file with `_create_permission_tables` and `config/permission.php`.

---

### Task 7: Create Domain Migration Files

**Files:**
- Create all 8 migration files listed in File Map above

- [ ] **Step 1: Create services migration**

Create `database/migrations/2026_05_08_000010_create_services_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->decimal('price', 10, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
```

- [ ] **Step 2: Create availability_rules migration**

Create `database/migrations/2026_05_08_000011_create_availability_rules_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday, 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
```

- [ ] **Step 3: Create appointments migration**

Create `database/migrations/2026_05_08_000012_create_appointments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('scheduled_date');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->decimal('final_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('google_event_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
```

- [ ] **Step 4: Create time_slots migration**

Create `database/migrations/2026_05_08_000013_create_time_slots_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
```

- [ ] **Step 5: Create appointment_reminders migration**

Create `database/migrations/2026_05_08_000014_create_appointment_reminders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['email', 'sms']);
            $table->dateTime('scheduled_for');
            $table->dateTime('sent_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
```

- [ ] **Step 6: Create payments migration**

Create `database/migrations/2026_05_08_000015_create_payments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'refunded', 'failed'])->default('pending');
            $table->string('stripe_transaction_id')->nullable();
            $table->json('stripe_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
```

- [ ] **Step 7: Create service_staff migration**

Create `database/migrations/2026_05_08_000016_create_service_staff_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_staff');
    }
};
```

- [ ] **Step 8: Create user_preferences migration**

Create `database/migrations/2026_05_08_000017_create_user_preferences_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('receive_email_reminders')->default(true);
            $table->boolean('receive_sms_reminders')->default(false);
            $table->string('phone_number')->nullable();
            $table->string('timezone')->default('UTC');
            $table->foreignId('preferred_staff')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
```

---

### Task 8: Run Migrations

- [ ] **Step 1: Run all migrations**

```bash
docker-compose run --rm app php artisan migrate --no-interaction
```

Expected output ends with all migrations listed as `Running` and then `Done`.

- [ ] **Step 2: Verify tables exist in postgres**

```bash
docker-compose exec postgres psql -U postgres -d booking_app -c "\dt"
```

Expected: List includes `services`, `appointments`, `time_slots`, `availability_rules`, `appointment_reminders`, `payments`, `service_staff`, `user_preferences`, plus all Spatie permission tables and default Laravel tables.

---

### Task 9: Commit

- [ ] **Step 1: Stage and commit all changes**

```bash
git init
git add .
git commit -m "feat: initial setup — Filament, Spatie Permission, domain schema migrations"
```

Expected: commit created with all new files.
