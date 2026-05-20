# Customer Settings Page — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a customer-facing `/portal/settings` page with two independently-saved sections: Profilo (name, email, password) and Notifiche (notification channel + phone number).

**Architecture:** A single `SettingsController` handles `GET /portal/settings`, `PATCH /portal/settings/profile`, and `PATCH /portal/settings/notifications`. Profile section updates `users`; notifications section uses `updateOrCreate` on `user_preferences`. The view uses Alpine.js `x-show` to toggle the phone field based on the selected channel.

**Tech Stack:** Laravel 13, PHP 8.4, Blade, Tailwind CSS, Alpine.js (already loaded via `@filamentScripts`), Pest

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Create | `app/Http/Controllers/Portal/SettingsController.php` | index, updateProfile, updateNotifications |
| Create | `resources/views/portal/settings/index.blade.php` | Two-section settings form |
| Modify | `routes/web.php` | Add 3 routes + import |
| Modify | `resources/views/layouts/app.blade.php` | Add "Impostazioni" nav link |
| Create | `tests/Feature/Portal/SettingsPortalTest.php` | All settings tests |

---

## Task 1: Routes and nav link

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Add import and three routes to `routes/web.php`**

Add the import after the existing `Portal` controller imports at the top:

```php
use App\Http\Controllers\Portal\SettingsController;
```

Add three routes inside the `Route::middleware('auth')->group(...)` block, after the existing portal routes:

```php
Route::get('/portal/settings', [SettingsController::class, 'index'])->name('portal.settings.index');
Route::patch('/portal/settings/profile', [SettingsController::class, 'updateProfile'])->name('portal.settings.profile');
Route::patch('/portal/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('portal.settings.notifications');
```

- [ ] **Step 2: Add "Impostazioni" nav link to `resources/views/layouts/app.blade.php`**

Inside `@auth`, after the "Appuntamenti" link (line 46), add:

```blade
<a href="{{ route('portal.settings.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Impostazioni</a>
```

- [ ] **Step 3: Commit**

```bash
git add routes/web.php resources/views/layouts/app.blade.php
git commit -m "feat: add settings routes and nav link"
```

---

## Task 2: TDD — index (GET /portal/settings)

**Files:**
- Create: `tests/Feature/Portal/SettingsPortalTest.php`
- Create: `app/Http/Controllers/Portal/SettingsController.php`
- Create: `resources/views/portal/settings/index.blade.php`

- [ ] **Step 1: Write failing tests for the index**

Create `tests/Feature/Portal/SettingsPortalTest.php`:

```php
<?php

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('redirects guests away from settings', function () {
    $this->get('/portal/settings')->assertRedirect('/login');
});

it('shows the settings page for authenticated users', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->get('/portal/settings')
        ->assertOk()
        ->assertSee('Profilo')
        ->assertSee('Notifiche');
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/SettingsPortalTest.php
```

Expected: FAIL — `SettingsController` does not exist.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Portal/SettingsController.php`:

```php
<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $preferences = $request->user()->preferences()->firstOrCreate([], [
            'notification_channel' => 'email',
        ]);

        return view('portal.settings.index', [
            'user' => $request->user(),
            'preferences' => $preferences,
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password'     => ['nullable', 'min:8', 'confirmed'],
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];

        if (!empty($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();

        return back()->with('profile_updated', 'Profilo aggiornato.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_channel' => ['required', 'in:email,sms,whatsapp'],
            'phone_number'         => [
                'nullable',
                'required_if:notification_channel,sms',
                'required_if:notification_channel,whatsapp',
                'regex:/^\+\d{7,15}$/',
                'max:20',
            ],
        ]);

        $request->user()->preferences()->updateOrCreate([], $validated);

        return back()->with('notifications_updated', 'Preferenze notifiche aggiornate.');
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/portal/settings/index.blade.php` (also create the directory `resources/views/portal/settings/`):

```blade
@extends('layouts.app')

@section('title', 'Impostazioni')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-semibold text-gray-950 dark:text-gray-50">Impostazioni</h1>
    </div>

    {{-- Profilo --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h2 class="mb-6 text-lg font-semibold text-gray-950 dark:text-gray-50">Profilo</h2>

        @if (session('profile_updated'))
            <div class="mb-4 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                {{ session('profile_updated') }}
            </div>
        @endif

        <form method="POST" action="{{ route('portal.settings.profile') }}" class="max-w-md space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Lascia vuoto per non cambiare la password.</p>

                <div class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password attuale</label>
                        <input type="password" id="current_password" name="current_password"
                            class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @error('current_password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nuova password</label>
                        <input type="password" id="new_password" name="new_password"
                            class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @error('new_password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Conferma nuova password</label>
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                            class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div>
                <button type="submit" class="btn-primary rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm">
                    Salva profilo
                </button>
            </div>
        </form>
    </div>

    {{-- Notifiche --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm"
         x-data="{ channel: '{{ old('notification_channel', $preferences->notification_channel) }}' }">
        <h2 class="mb-6 text-lg font-semibold text-gray-950 dark:text-gray-50">Notifiche</h2>

        @if (session('notifications_updated'))
            <div class="mb-4 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                {{ session('notifications_updated') }}
            </div>
        @endif

        <form method="POST" action="{{ route('portal.settings.notifications') }}" class="max-w-md space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Canale di notifica</label>
                <div class="space-y-2">
                    @foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="radio" name="notification_channel" value="{{ $value }}"
                                x-model="channel"
                                class="h-4 w-4 border-gray-300 text-blue-600 dark:border-gray-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('notification_channel')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div x-show="channel === 'sms' || channel === 'whatsapp'">
                <label for="phone_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Numero di telefono <span class="text-red-500">*</span>
                </label>
                <input type="tel" id="phone_number" name="phone_number"
                    value="{{ old('phone_number', $preferences->phone_number) }}"
                    placeholder="+39 334 1234567"
                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Formato internazionale, es. +39 334 1234567</p>
                @error('phone_number')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <button type="submit" class="btn-primary rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm">
                    Salva notifiche
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
```

- [ ] **Step 5: Run tests — verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/SettingsPortalTest.php
```

Expected: 2 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Portal/SettingsController.php \
        resources/views/portal/settings/index.blade.php \
        tests/Feature/Portal/SettingsPortalTest.php
git commit -m "feat: add settings page controller and view"
```

---

## Task 3: TDD — PATCH /portal/settings/profile

**Files:**
- Modify: `tests/Feature/Portal/SettingsPortalTest.php` (append tests)
- No controller changes needed — already implemented in Task 2

- [ ] **Step 1: Append profile tests to `tests/Feature/Portal/SettingsPortalTest.php`**

Add after the existing tests:

```php
// --- Profile ---

it('updates name and email', function () {
    $customer = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'New Name',
            'email' => 'new@example.com',
        ])
        ->assertRedirect();

    expect($customer->fresh())
        ->name->toBe('New Name')
        ->email->toBe('new@example.com');
});

it('requires name and email', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [])
        ->assertSessionHasErrors(['name', 'email']);
});

it('rejects duplicate email from another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'Test',
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors(['email']);
});

it('allows keeping own email', function () {
    $customer = User::factory()->create(['email' => 'mine@example.com']);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'Test',
            'email' => 'mine@example.com',
        ])
        ->assertSessionDoesntHaveErrors();
});

it('changes password when current password is correct', function () {
    $customer = User::factory()->create(['password' => Hash::make('oldpassword')]);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'current_password'          => 'oldpassword',
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'newpassword1',
        ])
        ->assertSessionDoesntHaveErrors();

    expect(Hash::check('newpassword1', $customer->fresh()->password))->toBeTrue();
});

it('rejects wrong current password', function () {
    $customer = User::factory()->create(['password' => Hash::make('correctpassword')]);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'current_password'          => 'wrongpassword',
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'newpassword1',
        ])
        ->assertSessionHasErrors(['current_password']);
});

it('requires current_password when setting new_password', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'newpassword1',
        ])
        ->assertSessionHasErrors(['current_password']);
});

it('rejects mismatched password confirmation', function () {
    $customer = User::factory()->create(['password' => Hash::make('oldpassword')]);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'current_password'          => 'oldpassword',
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors(['new_password']);
});

it('flashes profile_updated on success', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'Test',
            'email' => $customer->email,
        ])
        ->assertSessionHas('profile_updated');
});
```

- [ ] **Step 2: Run tests — verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/SettingsPortalTest.php
```

Expected: all PASS (controller was already implemented in Task 2).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Portal/SettingsPortalTest.php
git commit -m "test: add profile settings tests"
```

---

## Task 4: TDD — PATCH /portal/settings/notifications

**Files:**
- Modify: `tests/Feature/Portal/SettingsPortalTest.php` (append tests)
- No controller changes needed — already implemented in Task 2

- [ ] **Step 1: Append notification tests to `tests/Feature/Portal/SettingsPortalTest.php`**

Add after the profile tests:

```php
// --- Notifications ---

it('saves email channel without phone', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/notifications', [
            'notification_channel' => 'email',
        ])
        ->assertSessionDoesntHaveErrors();

    expect($customer->fresh()->preferences->notification_channel)->toBe('email');
});

it('requires phone_number when channel is sms', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/notifications', [
            'notification_channel' => 'sms',
        ])
        ->assertSessionHasErrors(['phone_number']);
});

it('requires phone_number when channel is whatsapp', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/notifications', [
            'notification_channel' => 'whatsapp',
        ])
        ->assertSessionHasErrors(['phone_number']);
});

it('rejects phone number without + prefix', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/notifications', [
            'notification_channel' => 'sms',
            'phone_number'         => '3334567890',
        ])
        ->assertSessionHasErrors(['phone_number']);
});

it('saves sms with valid international phone number', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/notifications', [
            'notification_channel' => 'sms',
            'phone_number'         => '+393334567890',
        ])
        ->assertSessionDoesntHaveErrors();

    $prefs = $customer->fresh()->preferences;
    expect($prefs->notification_channel)->toBe('sms')
        ->and($prefs->phone_number)->toBe('+393334567890');
});

it('creates UserPreference if none exists', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    expect(UserPreference::where('user_id', $customer->id)->exists())->toBeFalse();

    $this->actingAs($customer)
        ->patch('/portal/settings/notifications', [
            'notification_channel' => 'email',
        ]);

    expect(UserPreference::where('user_id', $customer->id)->exists())->toBeTrue();
});

it('flashes notifications_updated on success', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/notifications', [
            'notification_channel' => 'email',
        ])
        ->assertSessionHas('notifications_updated');
});
```

- [ ] **Step 2: Run tests — verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Portal/SettingsPortalTest.php
```

Expected: all PASS.

- [ ] **Step 3: Run full test suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Portal/SettingsPortalTest.php
git commit -m "test: add notification settings tests"
```
