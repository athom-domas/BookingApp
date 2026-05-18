# Booking Wizard Auth State Persistence Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preserve booking wizard state when a guest user navigates to login/register at step 5, and restore it automatically after authentication.

**Architecture:** When the user clicks "Accedi" or "Registrati" from step 5, Alpine.js saves the wizard state to `sessionStorage` and the login/register links include `?return=/prenota`. The login controller stores the return URL as the intended URL in session. After auth, the user lands back on `/prenota` where Alpine.js restores the saved state from `sessionStorage` and clears it.

**Tech Stack:** Alpine.js (booking-wizard.js), Laravel Blade (booking/index.blade.php, auth/login.blade.php, auth/register.blade.php), PHP controllers (AuthenticatedSessionController, RegisteredUserController), Pest (tests).

---

### Task 1: Save and restore wizard state in booking-wizard.js

**Files:**
- Modify: `resources/js/booking-wizard.js`

The wizard state that must survive auth redirect: `selectedServiceIds`, `staffId`, `date`, `slot`, `calendarMonth`, `paymentMethod`, `notes`, `step`, `completed`.

- [ ] **Step 1: Add `saveForAuth()` and restore-on-init logic to `booking-wizard.js`**

Replace the entire `init()` function and add two new methods. Edit `resources/js/booking-wizard.js`:

```js
        init() {
            const now = new Date();
            this.calendarMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

            const saved = sessionStorage.getItem('bookingWizardState');
            if (saved) {
                sessionStorage.removeItem('bookingWizardState');
                try {
                    const s = JSON.parse(saved);
                    this.selectedServiceIds = s.selectedServiceIds ?? [];
                    this.staffId            = s.staffId ?? null;
                    this.date               = s.date ?? null;
                    this.slot               = s.slot ?? null;
                    this.calendarMonth      = s.calendarMonth ?? this.calendarMonth;
                    this.paymentMethod      = s.paymentMethod ?? null;
                    this.notes              = s.notes ?? '';
                    this.completed          = s.completed ?? [];
                    this.step               = s.step ?? 1;
                } catch (_) {}

                if (this.step === 3 || (this.completed.includes(3) && this.date)) {
                    this.loadAvailableDates();
                    if (this.date) this.loadAvailableSlots();
                }
            }

            this.$watch('step', (v) => {
                if (v === 3) this.loadAvailableDates();
            });
        },

        saveForAuth(returnPath) {
            sessionStorage.setItem('bookingWizardState', JSON.stringify({
                selectedServiceIds: this.selectedServiceIds,
                staffId:            this.staffId,
                date:               this.date,
                slot:               this.slot,
                calendarMonth:      this.calendarMonth,
                paymentMethod:      this.paymentMethod,
                notes:              this.notes,
                step:               this.step,
                completed:          this.completed,
            }));
            window.location.href = returnPath;
        },
```

- [ ] **Step 2: Verify no syntax errors by running the build**

```bash
docker-compose run --rm --no-deps app npm run build 2>&1 | tail -20
```

Expected: build completes with no errors (exit 0, asset files listed).

- [ ] **Step 3: Commit**

```bash
git add resources/js/booking-wizard.js
git commit -m "feat: save/restore booking wizard state via sessionStorage for auth redirect"
```

---

### Task 2: Update step 5 auth buttons to use saveForAuth()

**Files:**
- Modify: `resources/views/portal/booking/index.blade.php` (lines 379–384)

The current code at step 5 (`@else` block):
```blade
<a href="{{ route('login') }}" class="...">Accedi</a>
<a href="{{ route('register') }}" class="...">Crea account</a>
```

These must call `saveForAuth()` instead of navigating directly so the state is saved first.

- [ ] **Step 1: Replace the two `<a>` tags with `<button>` elements that call `saveForAuth()`**

In `resources/views/portal/booking/index.blade.php`, replace:

```blade
                        <div class="flex gap-3">
                            <a href="{{ route('login') }}" class="flex-1 rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-center text-sm font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Accedi</a>
                            <a href="{{ route('register') }}" class="flex-1 rounded-md bg-blue-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-800">Crea account</a>
                        </div>
```

With:

```blade
                        <div class="flex gap-3">
                            <button type="button" @click="saveForAuth('{{ route('login') }}?return=/prenota')" class="flex-1 rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-center text-sm font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Accedi</button>
                            <button type="button" @click="saveForAuth('{{ route('register') }}?return=/prenota')" class="flex-1 rounded-md bg-blue-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-800">Crea account</button>
                        </div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/portal/booking/index.blade.php
git commit -m "feat: save wizard state before auth redirect in step 5"
```

---

### Task 3: Handle `?return` param in login controller and register controller

**Files:**
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`

The login controller's `create()` (GET /login) must store the `?return` URL in `session('url.intended')` so Laravel's `redirect()->intended()` picks it up.

The register controller currently hardcodes `redirect()->route('portal.appointments.index')` — it should use `redirect()->intended()` as well.

- [ ] **Step 1: Update `AuthenticatedSessionController::create()` to store `?return` as intended URL**

In `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, replace:

```php
    public function create(): View
    {
        return view('auth.login');
    }
```

With:

```php
    public function create(Request $request): View
    {
        if ($request->filled('return')) {
            session()->put('url.intended', $request->string('return')->toString());
        }

        return view('auth.login');
    }
```

- [ ] **Step 2: Update `RegisteredUserController::store()` to use `redirect()->intended()`**

In `app/Http/Controllers/Auth/RegisteredUserController.php`, replace:

```php
        return redirect()->route('portal.appointments.index');
```

With:

```php
        return redirect()->intended(route('portal.appointments.index'));
```

Also, add the `?return` handling to `RegisteredUserController::create()` for consistency (user may land on register page directly or switch from login):

In `app/Http/Controllers/Auth/RegisteredUserController.php`, replace:

```php
    public function create(): View
    {
        return view('auth.register');
    }
```

With:

```php
    public function create(Request $request): View
    {
        if ($request->filled('return')) {
            session()->put('url.intended', $request->string('return')->toString());
        }

        return view('auth.register');
    }
```

And add `use Illuminate\Http\Request;` to the imports (it's already imported — check before adding).

- [ ] **Step 3: Verify `RegisteredUserController` already imports `Request`**

Open `app/Http/Controllers/Auth/RegisteredUserController.php` and check that `use Illuminate\Http\Request;` is present (it is, at line 7). No change needed.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Auth/AuthenticatedSessionController.php \
        app/Http/Controllers/Auth/RegisteredUserController.php
git commit -m "feat: store ?return URL as intended redirect for login and register"
```

---

### Task 4: Update the "Registrati" link on the login page to carry the return param

**Files:**
- Modify: `resources/views/auth/login.blade.php`

When the user is on `/login?return=/prenota` and clicks "Registrati", the link currently goes to `/register` without any param, losing the intended URL from the session only if they visit the register page fresh.

The session value set in Task 3 persists for the whole session, so the link just needs to also pass `?return` to register so `create()` can re-set it if they arrive fresh.

- [ ] **Step 1: Make the "Registrati" link on login page carry the `return` query param**

In `resources/views/auth/login.blade.php`, replace:

```blade
            <a href="{{ route('register') }}" class="font-semibold text-blue-700 hover:text-blue-800">Registrati</a>
```

With:

```blade
            <a href="{{ route('register') }}{{ request()->filled('return') ? '?return='.urlencode(request('return')) : '' }}" class="font-semibold text-blue-700 hover:text-blue-800">Registrati</a>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/auth/login.blade.php
git commit -m "feat: carry ?return param from login to register link"
```

---

### Task 5: Feature tests for the auth redirect flow

**Files:**
- Modify: `tests/Feature/Auth/CustomerAuthTest.php`

These tests verify the end-to-end redirect behaviour.

- [ ] **Step 1: Add tests to `CustomerAuthTest.php`**

Open `tests/Feature/Auth/CustomerAuthTest.php` and add the following tests. Add them before the closing `});` of the outer `describe` block (or as top-level `it()` calls if the file uses that style — match the existing pattern):

```php
it('stores intended URL from ?return param on login GET', function () {
    $this->get(route('login') . '?return=/prenota')
        ->assertOk();

    expect(session('url.intended'))->toBe('/prenota');
});

it('redirects to ?return URL after successful login', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $user->assignRole('customer');

    $this->withSession(['url.intended' => '/prenota'])
        ->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/prenota');
});

it('stores intended URL from ?return param on register GET', function () {
    $this->get(route('register') . '?return=/prenota')
        ->assertOk();

    expect(session('url.intended'))->toBe('/prenota');
});

it('redirects to intended URL after registration when ?return was set', function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $this->withSession(['url.intended' => '/prenota'])
        ->post(route('register'), [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect('/prenota');
});
```

Make sure `use Spatie\Permission\Models\Role;` and `use App\Models\User;` are already present in the test file (check before adding).

- [ ] **Step 2: Run the new tests to verify they fail before implementation (they should already pass since Task 3 is done first)**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Auth/CustomerAuthTest.php --filter "intended URL"
```

Expected: 4 tests pass.

- [ ] **Step 3: Run the full test suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Auth/CustomerAuthTest.php
git commit -m "test: verify auth redirect flow preserves ?return URL for booking wizard"
```

---

## Self-Review

**Spec coverage:**
- ✅ Save wizard state before auth redirect → Task 1 (`saveForAuth`) + Task 2 (buttons)
- ✅ Restore state after returning to `/prenota` → Task 1 (`init()` restore)
- ✅ Redirect back to `/prenota` after login → Task 3 (AuthenticatedSessionController)
- ✅ Redirect back to `/prenota` after register → Task 3 (RegisteredUserController) 
- ✅ Carry return param from login → register link → Task 4
- ✅ Tests → Task 5

**Placeholder scan:** No TBD, no "similar to", all code blocks complete.

**Type consistency:**
- `saveForAuth(returnPath)` defined in Task 1, called in Task 2 ✅
- `session('url.intended')` set in Task 3, read by Laravel's `redirect()->intended()` ✅
- `?return=/prenota` passed in Task 2, consumed in Task 3 ✅
