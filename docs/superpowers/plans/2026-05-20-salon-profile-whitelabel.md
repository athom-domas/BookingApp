# Salon Profile — White-label Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow the salon admin to set branding (logo, name, primary color, contact info) that appears on the booking page and in all email templates.

**Architecture:** New `salon_profiles` singleton table + `SalonProfile` model (pattern mirrors `SystemSetting`). Dedicated Filament admin page "Profilo Salone". Layout blade injects a CSS custom property for the primary color. Email templates include two shared partials (branding header + contact footer).

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Pest, Blade, Tailwind CSS

---

## File Map

| Action | File |
|---|---|
| Create | `database/migrations/2026_05_20_000000_create_salon_profiles_table.php` |
| Create | `app/Models/SalonProfile.php` |
| Create | `tests/Feature/Models/SalonProfileTest.php` |
| Create | `app/Filament/Pages/SalonProfilePage.php` |
| Create | `resources/views/filament/pages/salon-profile.blade.php` |
| Create | `tests/Feature/Filament/SalonProfilePageTest.php` |
| Create | `resources/views/emails/partials/header.blade.php` |
| Create | `resources/views/emails/partials/salon-footer.blade.php` |
| Modify | `resources/views/layouts/app.blade.php` |
| Modify | `resources/views/emails/appointment-confirmation.blade.php` |
| Modify | `resources/views/emails/appointment-cancellation.blade.php` |
| Modify | `resources/views/emails/appointment-reminder.blade.php` |
| Modify | `resources/views/emails/admin-appointment-notification.blade.php` |
| Modify | `resources/views/emails/staff-appointment-notification.blade.php` |

---

## Task 1: Migration + SalonProfile model

**Files:**
- Create: `database/migrations/2026_05_20_000000_create_salon_profiles_table.php`
- Create: `app/Models/SalonProfile.php`
- Create: `tests/Feature/Models/SalonProfileTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Models/SalonProfileTest.php

use App\Models\SalonProfile;
use Illuminate\Support\Facades\Storage;

it('creates a default row when none exists', function () {
    expect(SalonProfile::count())->toBe(0);

    $profile = SalonProfile::current();

    expect(SalonProfile::count())->toBe(1);
    expect($profile->id)->toBe(1);
    expect($profile->name)->toBe('Il mio salone');
    expect($profile->primary_color)->toBe('#1d4ed8');
    expect($profile->logo_path)->toBeNull();
});

it('returns the existing row without creating a new one on repeated calls', function () {
    SalonProfile::current();
    SalonProfile::current();

    expect(SalonProfile::count())->toBe(1);
});

it('logoUrl returns null when logo_path is null', function () {
    $profile = SalonProfile::current();

    expect($profile->logoUrl())->toBeNull();
});

it('logoUrl returns public storage url when logo_path is set', function () {
    Storage::fake('public');
    $profile = SalonProfile::current();
    $profile->update(['logo_path' => 'salon-logo/test.png']);

    expect($profile->fresh()->logoUrl())->toContain('salon-logo/test.png');
});
```

- [ ] **Step 2: Run to confirm FAIL**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SalonProfileTest.php
```

Expected: `Class "App\Models\SalonProfile" not found`

- [ ] **Step 3: Create the migration**

```php
<?php
// database/migrations/2026_05_20_000000_create_salon_profiles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salon_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Il mio salone');
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->default('#1d4ed8');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salon_profiles');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php
// app/Models/SalonProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'logo_path', 'primary_color', 'phone', 'address', 'website'])]
class SalonProfile extends Model
{
    public static function current(): self
    {
        $existing = self::find(1);

        if ($existing) {
            return $existing;
        }

        $profile = new self([
            'name'          => 'Il mio salone',
            'logo_path'     => null,
            'primary_color' => '#1d4ed8',
            'phone'         => null,
            'address'       => null,
            'website'       => null,
        ]);
        $profile->id = 1;
        $profile->save();

        return $profile;
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }
}
```

- [ ] **Step 5: Run migration inside Docker**

```bash
docker-compose run --rm app php artisan migrate
```

- [ ] **Step 6: Run tests to confirm PASS**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SalonProfileTest.php
```

Expected: 4 tests pass

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_05_20_000000_create_salon_profiles_table.php \
        app/Models/SalonProfile.php \
        tests/Feature/Models/SalonProfileTest.php
git commit -m "feat: add SalonProfile model and migration"
```

---

## Task 2: Filament admin page "Profilo Salone"

**Files:**
- Create: `app/Filament/Pages/SalonProfilePage.php`
- Create: `resources/views/filament/pages/salon-profile.blade.php`
- Create: `tests/Feature/Filament/SalonProfilePageTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Filament/SalonProfilePageTest.php

use App\Filament\Pages\SalonProfilePage;
use App\Models\SalonProfile;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('admin can view the salon profile page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->assertSuccessful();
});

it('form is pre-filled with current profile', function () {
    SalonProfile::current()->update([
        'name'          => 'Salone Test',
        'primary_color' => '#ff0000',
        'phone'         => '+39 02 111111',
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->assertSet('data.name', 'Salone Test')
        ->assertSet('data.primary_color', '#ff0000')
        ->assertSet('data.phone', '+39 02 111111');
});

it('admin can update the salon profile', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SalonProfilePage::class)
        ->set('data.name', 'Nuovo Nome')
        ->set('data.primary_color', '#123456')
        ->set('data.phone', '+39 02 999999')
        ->set('data.address', 'Via Test 1')
        ->set('data.website', 'https://test.it')
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = SalonProfile::current();
    expect($profile->name)->toBe('Nuovo Nome');
    expect($profile->primary_color)->toBe('#123456');
    expect($profile->phone)->toBe('+39 02 999999');
    expect($profile->address)->toBe('Via Test 1');
    expect($profile->website)->toBe('https://test.it');
});

it('non-admin cannot access the salon profile page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(SalonProfilePage::canAccess())->toBeFalse();
});
```

- [ ] **Step 2: Run to confirm FAIL**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/SalonProfilePageTest.php
```

Expected: `Class "App\Filament\Pages\SalonProfilePage" not found`

- [ ] **Step 3: Create the Filament page**

```php
<?php
// app/Filament/Pages/SalonProfilePage.php

namespace App\Filament\Pages;

use App\Models\SalonProfile;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class SalonProfilePage extends Page
{
    protected string $view = 'filament.pages.salon-profile';

    protected static ?string $navigationLabel = 'Profilo Salone';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 98;

    public ?array $data = [];

    public function mount(): void
    {
        $profile = SalonProfile::current();
        $this->form->fill([
            'name'          => $profile->name,
            'logo_path'     => $profile->logo_path,
            'primary_color' => $profile->primary_color,
            'phone'         => $profile->phone,
            'address'       => $profile->address,
            'website'       => $profile->website,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nome del salone')
                                    ->required(),
                                ColorPicker::make('primary_color')
                                    ->label('Colore primario')
                                    ->required(),
                                TextInput::make('phone')
                                    ->label('Telefono'),
                                TextInput::make('website')
                                    ->label('Sito web')
                                    ->url(),
                                TextInput::make('address')
                                    ->label('Indirizzo')
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(2),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('salon-logo')
                            ->maxSize(2048)
                            ->columnSpan(1),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SalonProfile::current()->update($this->form->getState());

        Notification::make()
            ->title('Profilo salvato')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
```

- [ ] **Step 4: Create the blade view**

```blade
{{-- resources/views/filament/pages/salon-profile.blade.php --}}
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Salva
        </x-filament::button>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 5: Run tests to confirm PASS**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/SalonProfilePageTest.php
```

Expected: 4 tests pass

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/SalonProfilePage.php \
        resources/views/filament/pages/salon-profile.blade.php \
        tests/Feature/Filament/SalonProfilePageTest.php
git commit -m "feat: add SalonProfilePage Filament admin page"
```

---

## Task 3: Booking page — layout integration

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

No dedicated test file for this task — the layout is covered by checking the HTTP response of the public booking page.

- [ ] **Step 1: Write the failing test** (add to `tests/Feature/Http/`)

First check if `tests/Feature/Http/` exists; if not, create the directory. Create the file:

```php
<?php
// tests/Feature/Http/BookingPageBrandingTest.php

use App\Models\SalonProfile;

it('booking page shows salon name from profile', function () {
    SalonProfile::current()->update(['name' => 'Test Salone', 'primary_color' => '#abcdef']);

    $this->get('/')->assertSee('Test Salone')->assertSee('#abcdef');
});

it('booking page shows fallback logo when no logo is set', function () {
    SalonProfile::current();

    $this->get('/')->assertSee('img/logo.png');
});

it('booking page shows contact footer when fields are set', function () {
    SalonProfile::current()->update([
        'phone'   => '+39 02 999999',
        'address' => 'Via Roma 1',
        'website' => 'https://salone.it',
    ]);

    $this->get('/')
        ->assertSee('+39 02 999999')
        ->assertSee('Via Roma 1')
        ->assertSee('https://salone.it');
});

it('contact footer is hidden when all contact fields are null', function () {
    SalonProfile::current();

    $this->get('/')->assertDontSee('<footer', false);
});
```

- [ ] **Step 2: Run to confirm FAIL**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Http/BookingPageBrandingTest.php
```

Expected: tests fail because layout still has hardcoded "Booking App"

- [ ] **Step 3: Update `resources/views/layouts/app.blade.php`**

Replace the entire file content with:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php $salonProfile = \App\Models\SalonProfile::current(); @endphp

        <title>@yield('title', $salonProfile->name) - {{ $salonProfile->name }}</title>

        <style>
            :root { --color-primary: {{ $salonProfile->primary_color }}; }
            .btn-primary { background-color: var(--color-primary) !important; }
            .btn-primary:hover { filter: brightness(0.9); }
        </style>

        <script>
            if (localStorage.theme === 'dark' ||
                (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        </script>

        @fonts
        @filamentStyles
        @vite('resources/css/app.css')
        @vite('resources/css/filament/admin/theme.css')
        @stack('head')
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-gray-950 font-sans text-gray-950 dark:text-gray-50 antialiased">
        <header class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('booking.index') }}" class="flex min-w-0 items-center gap-3">
                    @if($salonProfile->logoUrl())
                        <img src="{{ $salonProfile->logoUrl() }}" alt="" class="h-9 w-9 rounded-md object-contain">
                    @else
                        <img src="{{ asset('img/logo.png') }}" alt="" class="h-9 w-9 rounded-md object-contain">
                    @endif
                    <span class="truncate text-base font-semibold text-gray-950 dark:text-gray-50">{{ $salonProfile->name }}</span>
                </a>

                <nav class="flex items-center gap-2 text-sm font-medium">
                    <a href="{{ route('booking.create') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Prenota</a>
                    @auth
                        <a href="{{ route('portal.appointments.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Appuntamenti</a>
                        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                            <a href="{{ url('/admin') }}" class="hidden rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50 sm:inline-flex">Admin</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Esci</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Accedi</a>
                        <a href="{{ route('register') }}" class="btn-primary rounded-md px-3 py-2 text-white">Registrati</a>
                    @endauth

                    <button
                        x-data
                        @click="
                            document.documentElement.classList.toggle('dark');
                            localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                        "
                        class="rounded-md p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50"
                        aria-label="Cambia tema"
                    >
                        <svg class="hidden dark:block h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg class="block dark:hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                    <p class="font-semibold">Controlla i dati inseriti.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        @if($salonProfile->phone || $salonProfile->address || $salonProfile->website)
        <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mt-8">
            <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8 flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                @if($salonProfile->phone)
                    <span>{{ $salonProfile->phone }}</span>
                @endif
                @if($salonProfile->address)
                    <span>{{ $salonProfile->address }}</span>
                @endif
                @if($salonProfile->website)
                    <a href="{{ $salonProfile->website }}" target="_blank" rel="noopener" class="hover:text-gray-700 dark:hover:text-gray-200">{{ $salonProfile->website }}</a>
                @endif
            </div>
        </footer>
        @endif

        @stack('scripts')
        @filamentScripts
        @vite('resources/js/app.js')
    </body>
</html>
```

- [ ] **Step 4: Run tests to confirm PASS**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Http/BookingPageBrandingTest.php
```

Expected: 4 tests pass

- [ ] **Step 5: Run full suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/app.blade.php \
        tests/Feature/Http/BookingPageBrandingTest.php
git commit -m "feat: apply SalonProfile branding to booking page layout"
```

---

## Task 4: Email partials + update all 5 templates

**Files:**
- Create: `resources/views/emails/partials/header.blade.php`
- Create: `resources/views/emails/partials/salon-footer.blade.php`
- Modify: `resources/views/emails/appointment-confirmation.blade.php`
- Modify: `resources/views/emails/appointment-cancellation.blade.php`
- Modify: `resources/views/emails/appointment-reminder.blade.php`
- Modify: `resources/views/emails/admin-appointment-notification.blade.php`
- Modify: `resources/views/emails/staff-appointment-notification.blade.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Mail/SalonProfileEmailBrandingTest.php

use App\Models\Appointment;
use App\Models\SalonProfile;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('confirmation email contains salon name', function () {
    SalonProfile::current()->update(['name' => 'Salone Branding Test', 'primary_color' => '#ff5500']);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $html = view('emails.appointment-confirmation', ['appointment' => $appointment])->render();

    expect($html)->toContain('Salone Branding Test');
    expect($html)->toContain('#ff5500');
});

it('admin notification email contains salon name', function () {
    SalonProfile::current()->update(['name' => 'Salone Admin Test']);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $html = view('emails.admin-appointment-notification', ['appointment' => $appointment])->render();

    expect($html)->toContain('Salone Admin Test');
});

it('salon footer shows contact info in emails', function () {
    SalonProfile::current()->update([
        'name'    => 'Salone Footer Test',
        'phone'   => '+39 02 888888',
        'address' => 'Via Test 99',
    ]);

    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $appointment = Appointment::factory()->create([
        'user_id'  => $customer->id,
        'staff_id' => $staff->id,
    ]);

    $html = view('emails.appointment-confirmation', ['appointment' => $appointment])->render();

    expect($html)->toContain('+39 02 888888');
    expect($html)->toContain('Via Test 99');
});
```

- [ ] **Step 2: Run to confirm FAIL**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Mail/SalonProfileEmailBrandingTest.php
```

Expected: tests fail — email templates don't contain salon name yet

- [ ] **Step 3: Create email partials**

**`resources/views/emails/partials/header.blade.php`:**

```blade
@php $salonProfile = \App\Models\SalonProfile::current(); @endphp
<div style="background-color:{{ $salonProfile->primary_color }};padding:20px 32px;display:flex;align-items:center;gap:12px;">
    @if($salonProfile->logoUrl())
        <img src="{{ $salonProfile->logoUrl() }}" alt="{{ $salonProfile->name }}" style="width:40px;height:40px;border-radius:6px;object-fit:contain;">
    @endif
    <span style="color:#ffffff;font-weight:600;font-size:1rem;">{{ $salonProfile->name }}</span>
</div>
```

**`resources/views/emails/partials/salon-footer.blade.php`:**

```blade
@php $salonProfile = \App\Models\SalonProfile::current(); @endphp
@if($salonProfile->phone || $salonProfile->address || $salonProfile->website)
<div style="padding:12px 32px;font-size:0.75rem;color:#9ca3af;border-top:1px solid #f3f4f6;">
    @if($salonProfile->phone)<span style="margin-right:12px;">{{ $salonProfile->phone }}</span>@endif
    @if($salonProfile->address)<span style="margin-right:12px;">{{ $salonProfile->address }}</span>@endif
    @if($salonProfile->website)<span>{{ $salonProfile->website }}</span>@endif
</div>
@endif
```

- [ ] **Step 4: Update `emails/appointment-confirmation.blade.php`**

Add `@include('emails.partials.header')` immediately after `<div class="container">`, and `@include('emails.partials.salon-footer')` immediately after `<div class="footer">...</div>`:

```blade
    <div class="container">
        @include('emails.partials.header')
        <div class="header">
            <h1>✓ Appuntamento confermato</h1>
        </div>
        {{-- ... existing body ... --}}
        <div class="footer">
            Riceverai un promemoria prima dell'appuntamento.
        </div>
        @include('emails.partials.salon-footer')
    </div>
```

- [ ] **Step 5: Update `emails/appointment-reminder.blade.php`**

Same pattern as confirmation — add `@include('emails.partials.header')` after `<div class="container">` and `@include('emails.partials.salon-footer')` after the `.footer` div.

- [ ] **Step 6: Update `emails/staff-appointment-notification.blade.php`**

Same pattern — add partials inside `<div class="container">`.

- [ ] **Step 7: Update `emails/appointment-cancellation.blade.php`**

This template has a minimal structure (no `.container` div). Add partials directly inside `<body>`:

```blade
<body>
@include('emails.partials.header')
<h2>Appointment Cancelled</h2>
{{-- ... existing content ... --}}
@include('emails.partials.salon-footer')
</body>
```

- [ ] **Step 8: Update `emails/admin-appointment-notification.blade.php`**

Same pattern as cancellation — add partials inside `<body>`:

```blade
<body>
@include('emails.partials.header')
<h2>Nuova prenotazione ricevuta</h2>
{{-- ... existing content ... --}}
@include('emails.partials.salon-footer')
</body>
```

- [ ] **Step 9: Run tests to confirm PASS**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Mail/SalonProfileEmailBrandingTest.php
```

Expected: 3 tests pass

- [ ] **Step 10: Run full suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass

- [ ] **Step 11: Commit**

```bash
git add resources/views/emails/partials/header.blade.php \
        resources/views/emails/partials/salon-footer.blade.php \
        resources/views/emails/appointment-confirmation.blade.php \
        resources/views/emails/appointment-cancellation.blade.php \
        resources/views/emails/appointment-reminder.blade.php \
        resources/views/emails/admin-appointment-notification.blade.php \
        resources/views/emails/staff-appointment-notification.blade.php \
        tests/Feature/Mail/SalonProfileEmailBrandingTest.php
git commit -m "feat: add salon branding to email templates via shared partials"
```
