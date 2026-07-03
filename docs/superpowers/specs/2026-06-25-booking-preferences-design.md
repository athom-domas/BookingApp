# Booking Preferences Design Spec

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere giorni della settimana e fascia oraria preferiti al profilo cliente, usarli per suggerire slot nel booking wizard, e proporre il salvataggio dopo la prima prenotazione.

**Architecture:** Le preferenze vengono salvate su `user_preferences` (3 nuove colonne + flag dismissed). Il booking wizard richiede gli slot suggeriti a un endpoint backend dedicato (`/api/booking/suggested-slots`) che applica uno scoring server-side. Il blocco "Suggeriti per te" appare prima del calendario con stato Alpine dedicato. Il prompt post-prenotazione appare solo se le preferenze non sono ancora impostate e non sono state precedentemente ignorate.

**Tech Stack:** Laravel 13, Filament 4, Alpine.js, Blade, MySQL 8

## Global Constraints

### Multi-tenancy
- `AvailabilityRule` usa il trait `BelongsToBusiness` che aggiunge un global scope automatico su `current_business_id` — nessuna query manuale di scoping necessaria.
- `UserPreference` usa lo stesso trait. La colonna `user_id` è UNIQUE sulla tabella, quindi ogni utente ha una sola riga, ma il global scope filtra per `business_id`. Nei controller usare sempre `firstOrCreate(['user_id' => $user->id, 'business_id' => app('current_business_id')])` come chiave di ricerca per garantire coerenza.
- La relazione `User::preferences()` è `hasOne(UserPreference::class)` senza scope business esplicito — non usarla direttamente per leggere o scrivere preferenze nei controller del portale; usare invece `UserPreference::firstOrCreate([...])` con entrambe le chiavi.

### Schema
- `preferred_days` su `user_preferences`: `json nullable` — array di interi 0–6 (0=domenica, 6=sabato), stesso formato di `AvailabilityRule.day_of_week`.
- `preferred_time_from`, `preferred_time_to`: `time nullable` — MySQL restituisce `HH:MM:SS`; normalizzare sempre a `HH:MM` con `substr($value, 0, 5)` prima di passare a Blade/JS.
- `booking_preference_prompt_dismissed`: `boolean default false`.

### Logica suggerimenti
- Il blocco "Suggeriti per te" appare se il cliente ha impostato almeno `preferred_time_from`/`preferred_time_to` OPPURE almeno un giorno in `preferred_days` — non richiede entrambi.
- Se `preferred_days` è null/vuoto, la ricerca degli slot suggeriti considera tutti i giorni aperti del salone.
- Scoring: **+2** giorno preferito, **+2** slot nella fascia oraria, **+1** slot entro 60 min dalla fascia.
- Gli slot suggeriti vengono calcolati lato backend da un endpoint dedicato (non con N chiamate `/api/booking/slots` in JS).

### UI
- `showSuggestions` è uno stato Alpine (boolean) — non manipolare `style.display` direttamente.
- Le opzioni orario nei select sono da 07:00 a 21:00 a step 30 min.
- I giorni nei checkbox mostrano solo quelli in cui il salone è aperto (`AvailabilityRule` con `is_available = true`).
- Nel prompt post-prenotazione, la fascia viene descritta dinamicamente: 07–12 → "mattina", 12–17 → "pomeriggio", 17–21 → "sera".

### Test
Ogni task deve includere test feature specifici per i nuovi comportamenti — non solo "esegui i test esistenti".

---

### Task 1: Migration e model

**Files:**
- Create: `database/migrations/2026_06_25_000001_add_booking_preferences_to_user_preferences.php`
- Modify: `app/Models/UserPreference.php`
- Create/Modify: `tests/Feature/Models/UserPreferenceTest.php`

**Interfaces:**
- Produces: `UserPreference` con `preferred_days` (cast array), `preferred_time_from` (string|null), `preferred_time_to` (string|null), `booking_preference_prompt_dismissed` (bool)

- [ ] **Step 1: Crea la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('preferred_days')->nullable()->after('phone_number');
            $table->time('preferred_time_from')->nullable()->after('preferred_days');
            $table->time('preferred_time_to')->nullable()->after('preferred_time_from');
            $table->boolean('booking_preference_prompt_dismissed')->default(false)->after('preferred_time_to');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_days',
                'preferred_time_from',
                'preferred_time_to',
                'booking_preference_prompt_dismissed',
            ]);
        });
    }
};
```

- [ ] **Step 2: Esegui la migration**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: completata senza errori.

- [ ] **Step 3: Aggiorna UserPreference**

```php
#[Fillable([
    'business_id', 'user_id', 'notification_channel', 'phone_number',
    'follow_up_reminders_enabled', 'preferred_days',
    'preferred_time_from', 'preferred_time_to',
    'booking_preference_prompt_dismissed',
])]

protected function casts(): array
{
    return [
        'follow_up_reminders_enabled'        => 'boolean',
        'preferred_days'                      => 'array',
        'booking_preference_prompt_dismissed' => 'boolean',
    ];
}
```

- [ ] **Step 4: Scrivi i test**

```php
use App\Models\UserPreference;

it('casts preferred_days as array', function () {
    $pref = UserPreference::factory()->create(['preferred_days' => [1, 3, 5]]);
    expect($pref->fresh()->preferred_days)->toBe([1, 3, 5]);
});

it('preferred_days is nullable', function () {
    $pref = UserPreference::factory()->create(['preferred_days' => null]);
    expect($pref->fresh()->preferred_days)->toBeNull();
});

it('casts booking_preference_prompt_dismissed as bool', function () {
    $pref = UserPreference::factory()->create(['booking_preference_prompt_dismissed' => true]);
    expect($pref->fresh()->booking_preference_prompt_dismissed)->toBeTrue();
});
```

- [ ] **Step 5: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/UserPreferenceTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_25_000001_add_booking_preferences_to_user_preferences.php \
        app/Models/UserPreference.php \
        tests/Feature/Models/UserPreferenceTest.php
git commit -m "feat: add booking preferences columns to user_preferences"
```

---

### Task 2: Portale cliente — form preferenze in settings

**Files:**
- Modify: `app/Http/Controllers/Portal/SettingsController.php`
- Modify: `resources/views/portal/settings/index.blade.php`
- Modify: `routes/web.php`
- Create/Modify: `tests/Feature/Controllers/Portal/SettingsBookingPreferencesTest.php`

**Interfaces:**
- Consumes: `UserPreference` con nuovi campi (Task 1)
- Produces:
  - `PATCH /portal/settings/booking-preferences` → salva `preferred_days`, `preferred_time_from`, `preferred_time_to`
  - `POST /portal/settings/booking-preferences/dismiss` → setta `booking_preference_prompt_dismissed = true`

- [ ] **Step 1: Aggiungi le route**

In `routes/web.php`, dentro il gruppo auth portal esistente:

```php
Route::patch('/portal/settings/booking-preferences', [SettingsController::class, 'updateBookingPreferences'])
    ->name('portal.settings.booking-preferences');
Route::post('/portal/settings/booking-preferences/dismiss', [SettingsController::class, 'dismissBookingPreferencePrompt'])
    ->name('portal.settings.booking-preferences.dismiss');
```

- [ ] **Step 2: Aggiungi i metodi al controller**

`AvailabilityRule` è già importato in Laravel tramite il global scope — aggiungere `use App\Models\AvailabilityRule;` in cima al file se non presente.

```php
public function updateBookingPreferences(Request $request): RedirectResponse
{
    // AvailabilityRule è auto-scoped a current_business_id via BelongsToBusiness
    $openDays = AvailabilityRule::where('is_available', true)
        ->distinct()->pluck('day_of_week')->all();

    $validated = $request->validate([
        'preferred_days'      => ['nullable', 'array'],
        'preferred_days.*'    => ['integer', Rule::in($openDays ?: range(0, 6))],
        'preferred_time_from' => ['nullable', 'date_format:H:i', 'required_with:preferred_time_to'],
        'preferred_time_to'   => [
            'nullable', 'date_format:H:i',
            'required_with:preferred_time_from',
            'after:preferred_time_from',
        ],
    ]);

    UserPreference::firstOrCreate(
        ['user_id' => $request->user()->id, 'business_id' => app('current_business_id')],
        ['notification_channel' => 'email']
    )->update([
        'preferred_days'      => $validated['preferred_days'] ?? null,
        'preferred_time_from' => $validated['preferred_time_from'] ?? null,
        'preferred_time_to'   => $validated['preferred_time_to'] ?? null,
    ]);

    return back()->with('status', 'Preferenze di prenotazione aggiornate.');
}

public function dismissBookingPreferencePrompt(Request $request): RedirectResponse
{
    UserPreference::firstOrCreate(
        ['user_id' => $request->user()->id, 'business_id' => app('current_business_id')],
        ['notification_channel' => 'email']
    )->update(['booking_preference_prompt_dismissed' => true]);

    return back();
}
```

Aggiungere `use Illuminate\Validation\Rule;` e `use App\Models\UserPreference;` in cima se non presenti.

- [ ] **Step 3: Aggiorna il metodo `index()` del controller**

Aggiungere `$openDayNums` ai dati passati alla view:

```php
$openDayNums = AvailabilityRule::where('is_available', true)
    ->distinct()->pluck('day_of_week')->sort()->values()->all();

return view('portal.settings.index', [
    'user'        => $request->user(),
    'preferences' => $preferences,
    'openDayNums' => $openDayNums,
]);
```

- [ ] **Step 4: Normalizza i campi time prima di passarli alla view**

Nel metodo `index()`, dopo il `firstOrCreate`, normalizzare:

```php
// MySQL restituisce HH:MM:SS — normalizzare a HH:MM
if ($preferences->preferred_time_from) {
    $preferences->preferred_time_from = substr($preferences->preferred_time_from, 0, 5);
}
if ($preferences->preferred_time_to) {
    $preferences->preferred_time_to = substr($preferences->preferred_time_to, 0, 5);
}
```

- [ ] **Step 5: Aggiungi la sezione nella view settings**

In `resources/views/portal/settings/index.blade.php`, dopo la card comunicazioni esistente:

```blade
{{-- Preferenze prenotazione --}}
<div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Preferenze prenotazione</h2>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Usiamo queste informazioni per suggerirti gli slot più adatti.</p>
    </div>
    <form method="POST" action="{{ route('portal.settings.booking-preferences') }}" class="px-5 py-5 space-y-5">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Giorni preferiti</p>
            @php
                $dayLabels = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab'];
                $savedDays = $preferences->preferred_days ?? [];
            @endphp
            <div class="flex flex-wrap gap-2">
                @foreach($openDayNums as $num)
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="preferred_days[]" value="{{ $num }}"
                               {{ in_array($num, $savedDays) ? 'checked' : '' }}
                               class="rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $dayLabels[$num] }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            @php
                $timeOptions = [];
                for ($h = 7; $h <= 21; $h++) {
                    $timeOptions[sprintf('%02d:00', $h)] = sprintf('%02d:00', $h);
                    if ($h < 21) $timeOptions[sprintf('%02d:30', $h)] = sprintf('%02d:30', $h);
                }
            @endphp
            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Dalle</label>
                <select name="preferred_time_from"
                    class="block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                    <option value="">Qualsiasi</option>
                    @foreach($timeOptions as $val => $label)
                        <option value="{{ $val }}" {{ ($preferences->preferred_time_from ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Alle</label>
                <select name="preferred_time_to"
                    class="block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                    <option value="">Qualsiasi</option>
                    @foreach($timeOptions as $val => $label)
                        <option value="{{ $val }}" {{ ($preferences->preferred_time_to ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @error('preferred_time_to')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <div class="flex justify-end">
            <button type="submit"
                class="rounded-md bg-gray-900 dark:bg-gray-100 px-4 py-2 text-sm font-semibold text-white dark:text-gray-900 hover:opacity-90 transition-opacity">
                Salva preferenze
            </button>
        </div>
    </form>
</div>
```

- [ ] **Step 6: Scrivi i test feature**

```php
use App\Models\{User, UserPreference, AvailabilityRule};
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $this->business = \App\Models\Business::factory()->create();
    app()->instance('current_business_id', $this->business->id);
    AvailabilityRule::factory()->create([
        'business_id'  => $this->business->id,
        'day_of_week'  => 1,
        'is_available' => true,
    ]);
});

it('saves booking preferences', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->patch(route('portal.settings.booking-preferences'), [
            'preferred_days'      => [1],
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '12:00',
        ])
        ->assertRedirect();

    $pref = UserPreference::where('user_id', $user->id)->first();
    expect($pref->preferred_days)->toBe([1])
        ->and($pref->preferred_time_from)->toStartWith('09:00')
        ->and($pref->preferred_time_to)->toStartWith('12:00');
});

it('rejects days not in open days', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->patch(route('portal.settings.booking-preferences'), [
            'preferred_days' => [0], // domenica, non aperta
        ])
        ->assertSessionHasErrors('preferred_days.0');
});

it('dismisses the preference prompt', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->post(route('portal.settings.booking-preferences.dismiss'))
        ->assertRedirect();

    expect(UserPreference::where('user_id', $user->id)->first()->booking_preference_prompt_dismissed)->toBeTrue();
});
```

- [ ] **Step 7: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Controllers/Portal/SettingsBookingPreferencesTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Portal/SettingsController.php \
        resources/views/portal/settings/index.blade.php \
        routes/web.php \
        tests/Feature/Controllers/Portal/SettingsBookingPreferencesTest.php
git commit -m "feat: add booking preferences form to customer settings"
```

---

### Task 3: Endpoint backend `/api/booking/suggested-slots`

**Files:**
- Create: `app/Http/Controllers/Api/SuggestedSlotsController.php`
- Modify: `routes/api.php` (o il file di route API esistente)
- Create: `tests/Feature/Api/SuggestedSlotsTest.php`

**Interfaces:**
- Consumes: `GET /api/booking/suggested-slots?serviceIds[]=1&staffId=2&preferredDays[]=1&preferredDays[]=3&timeFrom=09:00&timeTo=12:00&limit=6`
- Produces: JSON `{ "data": [{ "date": "2026-06-30", "time": "09:30", "score": 4 }, ...] }` — max `limit` slot, ordinati per score desc poi data/time asc.

- [ ] **Step 1: Trova dove sono le route API esistenti e come è strutturato il controller slot**

Leggi `routes/api.php` e `app/Http/Controllers/Api/BookingController.php` per capire il pattern da seguire (middleware, formato risposta, SlotService utilizzato).

- [ ] **Step 2: Crea il controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedSlotsController extends Controller
{
    public function __construct(private readonly SlotCalculationService $slotService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'serviceIds'    => ['required', 'array', 'min:1'],
            'serviceIds.*'  => ['integer', 'exists:services,id'],
            'staffId'       => ['nullable', 'integer', 'exists:users,id'],
            'preferredDays' => ['nullable', 'array'],
            'preferredDays.*' => ['integer', 'between:0,6'],
            'timeFrom'      => ['nullable', 'date_format:H:i'],
            'timeTo'        => ['nullable', 'date_format:H:i', 'after:timeFrom'],
            'limit'         => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $serviceIds    = array_map('intval', $request->input('serviceIds'));
        $staffId       = $request->filled('staffId') ? (int) $request->input('staffId') : null;
        $preferredDays = $request->input('preferredDays') ? array_map('intval', $request->input('preferredDays')) : null;
        $timeFrom      = $request->input('timeFrom');
        $timeTo        = $request->input('timeTo');
        $limit         = (int) ($request->input('limit', 6));

        // Se preferredDays è null o vuoto, usa tutti i giorni aperti
        $openDays = \App\Models\AvailabilityRule::where('is_available', true)
            ->distinct()->pluck('day_of_week')->all();
        $targetDays = (empty($preferredDays)) ? $openDays : $preferredDays;

        $results = [];
        $today   = Carbon::today();

        for ($i = 0; $i < 28 && count($results) < $limit * 3; $i++) {
            $date = $today->copy()->addDays($i);
            $dow  = (int) $date->format('w'); // 0=domenica

            if (! in_array($dow, $targetDays, true)) continue;

            $slots = $this->slotService->getAvailableSlots([
                'date'            => $date,
                'serviceIds'      => $serviceIds,
                'staffId'         => $staffId,
                'staffPreference' => $staffId ? 'specific' : 'any',
            ]);

            foreach ($slots as $slot) {
                $score = $this->score($dow, $slot['start'], $preferredDays, $timeFrom, $timeTo);
                $results[] = [
                    'date'  => $date->toDateString(),
                    'time'  => $slot['start'],
                    'score' => $score,
                ];
            }
        }

        usort($results, fn ($a, $b) =>
            $b['score'] <=> $a['score']
                ?: strcmp($a['date'], $b['date'])
                ?: strcmp($a['time'], $b['time'])
        );

        return response()->json(['data' => array_slice($results, 0, $limit)]);
    }

    private function score(int $dow, string $time, ?array $preferredDays, ?string $timeFrom, ?string $timeTo): int
    {
        $score = 0;
        if ($preferredDays && in_array($dow, $preferredDays, true)) $score += 2;
        if ($timeFrom && $timeTo) {
            if ($time >= $timeFrom && $time < $timeTo) {
                $score += 2;
            } else {
                $slotMin  = (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
                $fromMin  = (int) substr($timeFrom, 0, 2) * 60 + (int) substr($timeFrom, 3, 2);
                $toMin    = (int) substr($timeTo, 0, 2) * 60 + (int) substr($timeTo, 3, 2);
                if ($slotMin >= $fromMin - 60 && $slotMin < $toMin + 60) $score += 1;
            }
        }
        return $score;
    }
}
```

- [ ] **Step 3: Registra la route API**

Nello stesso file dove è registrata `/api/booking/slots`, aggiungi:

```php
Route::get('/booking/suggested-slots', \App\Http\Controllers\Api\SuggestedSlotsController::class);
```

- [ ] **Step 4: Scrivi i test**

```php
it('returns suggested slots ordered by score', function () {
    // Setup: business, servizi, availability rules, staff
    // ...
    $response = $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds'    => [1],
        'preferredDays' => [1], // lunedì
        'timeFrom'      => '09:00',
        'timeTo'        => '12:00',
    ]));

    $response->assertOk()
        ->assertJsonStructure(['data' => [['date', 'time', 'score']]]);

    // Il primo slot deve avere score massimo
    $scores = collect($response->json('data'))->pluck('score');
    expect($scores->first())->toBeGreaterThanOrEqual($scores->last());
});

it('falls back to all open days when preferredDays is empty', function () {
    // Verifica che vengano restituiti slot anche senza preferredDays
    $response = $this->getJson('/api/booking/suggested-slots?serviceIds[]=1&timeFrom=09:00&timeTo=12:00');
    $response->assertOk();
});
```

- [ ] **Step 5: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Api/SuggestedSlotsTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/SuggestedSlotsController.php \
        routes/api.php \
        tests/Feature/Api/SuggestedSlotsTest.php
git commit -m "feat: add suggested-slots API endpoint with preference scoring"
```

---

### Task 4: Booking wizard — blocco "Suggeriti per te" e pallini calendario

**Files:**
- Modify: `app/Http/Controllers/Portal/BookingController.php`
- Modify: `resources/js/booking-wizard.js`
- Modify: `resources/views/portal/booking/index.blade.php`
- Create: `tests/Feature/Controllers/Portal/BookingPreferencesWizardTest.php`

**Interfaces:**
- Consumes: `UserPreference` con nuovi campi; endpoint `/api/booking/suggested-slots` (Task 3)
- Produces: `bookingPreferences` oggetto JS `{ days: [1,3], timeFrom: "09:00", timeTo: "12:00" }` | `null` passato al wizard

- [ ] **Step 1: Passa le preferenze normalizzate alla view dal controller**

In `BookingController::create()`, aggiungere prima del `return view(...)`:

```php
$bookingPreferences = null;
if (auth()->check()) {
    $pref = UserPreference::where('user_id', auth()->id())
        ->where('business_id', $businessId)
        ->first();
    $hasPrefs = $pref && (! empty($pref->preferred_days) || $pref->preferred_time_from);
    if ($hasPrefs) {
        $bookingPreferences = [
            'days'     => $pref->preferred_days ?? [],
            // Normalizza HH:MM:SS → HH:MM
            'timeFrom' => $pref->preferred_time_from ? substr($pref->preferred_time_from, 0, 5) : null,
            'timeTo'   => $pref->preferred_time_to   ? substr($pref->preferred_time_to,   0, 5) : null,
        ];
    }
}
```

Passare `'bookingPreferences' => $bookingPreferences` alla view. Aggiungere `use App\Models\UserPreference;` se mancante.

- [ ] **Step 2: Aggiorna la firma di `bookingWizard()` in JS**

```js
function bookingWizard(servicesJson, staffJson, bookingPreferences = null) {
```

Nel data object aggiungere:

```js
preferences: bookingPreferences,
suggestedSlots: [],
suggestedSlotsLoaded: false,
showSuggestions: true,
```

- [ ] **Step 3: Aggiungi `toLocalIsoDate()` e i metodi per i suggerimenti**

```js
toLocalIsoDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
},

isPreferredDay(iso) {
    if (!this.preferences?.days?.length) return false;
    const dow = new Date(iso + 'T00:00:00').getDay();
    return this.preferences.days.includes(dow);
},

async loadSuggestedSlots() {
    if (!this.preferences || !this.selectedServiceIds.length) return;
    this.suggestedSlotsLoaded = false;
    this.suggestedSlots = [];
    this.showSuggestions = true;

    const params = new URLSearchParams();
    this.selectedServiceIds.forEach(id => params.append('serviceIds[]', id));
    if (this.staffId) params.append('staffId', this.staffId);
    if (this.preferences.days?.length) {
        this.preferences.days.forEach(d => params.append('preferredDays[]', d));
    }
    if (this.preferences.timeFrom) params.append('timeFrom', this.preferences.timeFrom);
    if (this.preferences.timeTo)   params.append('timeTo',   this.preferences.timeTo);
    params.append('limit', '6');

    try {
        const res  = await fetch('/api/booking/suggested-slots?' + params.toString(), { headers: { Accept: 'application/json' } });
        const data = await res.json();
        this.suggestedSlots = data.data ?? [];
    } catch (_) {}

    this.suggestedSlotsLoaded = true;
},

get groupedSuggested() {
    const map = {};
    for (const s of this.suggestedSlots) {
        if (!map[s.date]) map[s.date] = { date: s.date, slots: [] };
        map[s.date].slots.push(s);
    }
    return Object.values(map).slice(0, 3);
},

selectSuggestedSlot(dateVal, timeVal) {
    this.date         = dateVal;
    this.slot         = timeVal;
    this.calendarMonth = dateVal.slice(0, 7);
    this.loadAvailableSlots();
},

formatSuggestedDate(iso) {
    const s = new Date(iso + 'T00:00:00').toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
    return s.charAt(0).toUpperCase() + s.slice(1);
},
```

- [ ] **Step 4: Chiama `loadSuggestedSlots` al completamento dello step 2**

Nel metodo `completeStep(n)` esistente, aggiungere dopo la logica corrente:

```js
if (n === 2 && this.preferences) this.loadSuggestedSlots();
```

- [ ] **Step 5: Aggiungi pallino preferito nelle celle calendario**

Nel template Blade del calendario (dentro lo step 3 del booking wizard), trovare il div che renderizza ogni cella del calendario. Aggiungere `relative` alla classe della cella e il pallino:

```blade
{{-- la cella deve avere position:relative --}}
<div class="relative">
    <button ... x-text="day.slice(-2)"></button>
    <span x-show="isPreferredDay(day)"
          class="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-primary opacity-50 pointer-events-none"></span>
</div>
```

Nota: leggere la struttura effettiva del calendario nel file prima di modificare — individuare la variabile che contiene la data ISO (potrebbe chiamarsi `day`, `cell`, `date` ecc.).

- [ ] **Step 6: Aggiorna la chiamata al wizard nella view**

```blade
x-data="bookingWizard(
    {{ Illuminate\Support\Js::from($servicesJson) }},
    {{ Illuminate\Support\Js::from($staffJson) }},
    {{ Illuminate\Support\Js::from($bookingPreferences) }}
)"
```

- [ ] **Step 7: Aggiungi il blocco "Suggeriti per te" nella view**

All'inizio dello step 3 (data/slot), prima del calendario:

```blade
{{-- Suggeriti per te --}}
<div x-show="preferences && suggestedSlotsLoaded && suggestedSlots.length > 0 && showSuggestions"
     x-cloak
     class="mb-5 rounded-lg border border-primary/20 bg-primary/5 dark:bg-primary/10 p-4">
    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">✦ Suggeriti per te</p>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">In base alle tue preferenze</p>
    <div class="space-y-2">
        <template x-for="(group, idx) in groupedSuggested" :key="idx">
            <div class="flex items-start gap-3 flex-wrap">
                <span class="text-xs font-medium text-gray-700 dark:text-gray-300 w-36 shrink-0 pt-1"
                      x-text="formatSuggestedDate(group.date)"></span>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="s in group.slots" :key="s.time">
                        <button type="button"
                                @click="selectSuggestedSlot(s.date, s.time)"
                                class="rounded border px-3 py-1 text-xs font-medium transition-colors"
                                :class="date === s.date && slot === s.time
                                    ? 'slot-active border-transparent'
                                    : 'border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gray-400 hover:bg-gray-50 dark:hover:border-gray-500 dark:hover:bg-gray-800'"
                                x-text="s.time"></button>
                    </template>
                </div>
            </div>
        </template>
    </div>
    <button type="button" @click="showSuggestions = false"
            class="mt-3 text-xs text-gray-400 hover:underline">Vedi tutte le disponibilità ↓</button>
</div>
```

- [ ] **Step 8: Scrivi i test feature**

```php
it('passes bookingPreferences to view when customer has preferences', function () {
    // Setup: utente con preferred_days e preferred_time_from
    $this->actingAs($user)
        ->get(route('booking.create'))
        ->assertViewHas('bookingPreferences', fn ($p) =>
            $p['days'] === [1, 3] && $p['timeFrom'] === '09:00'
        );
});

it('passes null bookingPreferences when customer has no preferences', function () {
    $this->actingAs($userWithoutPrefs)
        ->get(route('booking.create'))
        ->assertViewHas('bookingPreferences', null);
});
```

- [ ] **Step 9: Esegui tutti i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Controllers/Portal/BookingPreferencesWizardTest.php
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Controllers/Portal/
```

Expected: PASS.

- [ ] **Step 10: Build assets e verifica manuale**

```bash
docker-compose run --rm --no-deps app npm run build
```

Verifica: cliente con preferenze → blocco "Suggeriti per te" appare → cliccando uno slot lo seleziona → "Vedi tutte le disponibilità" nasconde il blocco (stato `showSuggestions`) → giorni preferiti hanno pallino nel calendario.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/Portal/BookingController.php \
        resources/js/booking-wizard.js \
        resources/views/portal/booking/index.blade.php \
        tests/Feature/Controllers/Portal/BookingPreferencesWizardTest.php
git commit -m "feat: show preferred slot suggestions in booking wizard"
```

---

### Task 5: Prompt post-prenotazione

**Files:**
- Modify: `app/Http/Controllers/Portal/AppointmentController.php`
- Modify: `resources/views/portal/appointments/show.blade.php`
- Create: `tests/Feature/Controllers/Portal/AppointmentPreferencePromptTest.php`

**Interfaces:**
- Consumes: `UserPreference.booking_preference_prompt_dismissed`, `Appointment.scheduled_at`
- Produces: banner con `preferred_days`, `preferred_time_from`, `preferred_time_to` pre-compilati dall'appuntamento

**Calcolo fascia dinamica:**
- 07:00–11:59 → "mattina"
- 12:00–16:59 → "pomeriggio"
- 17:00–21:00 → "sera"
- La fascia proposta è ±60 min dall'orario prenotato, clampata a 07:00–21:00 e arrotondata a :00/:30

- [ ] **Step 1: Calcola i valori nel controller**

Nella action `show` di `AppointmentController`, aggiungere dopo aver caricato `$appointment`:

```php
$showPreferencePrompt = false;
$prefillPreferences   = null;

if (auth()->check()) {
    $pref = \App\Models\UserPreference::where('user_id', auth()->id())
        ->where('business_id', app('current_business_id'))
        ->first();

    $noPreferences = ! $pref || empty($pref->preferred_days);
    $notDismissed  = ! $pref || ! $pref->booking_preference_prompt_dismissed;

    if ($noPreferences && $notDismissed) {
        $showPreferencePrompt = true;
        $dt      = $appointment->scheduled_at;
        $dow     = (int) $dt->format('w');
        $slotMin = (int) $dt->format('H') * 60 + (int) $dt->format('i');
        $fromMin = max(7 * 60, $slotMin - 60);
        $toMin   = min(21 * 60, $slotMin + 60);
        // arrotonda a :00 o :30
        $fromMin = (int) (floor($fromMin / 30) * 30);
        $toMin   = (int) (ceil($toMin / 30) * 30);

        $hour = (int) $dt->format('H');
        $fasciaLabel = match(true) {
            $hour < 12 => 'mattina',
            $hour < 17 => 'pomeriggio',
            default    => 'sera',
        };

        $dayNames = [0=>'domenica',1=>'lunedì',2=>'martedì',3=>'mercoledì',4=>'giovedì',5=>'venerdì',6=>'sabato'];

        $prefillPreferences = [
            'preferred_days'      => [$dow],
            'preferred_time_from' => sprintf('%02d:%02d', intdiv($fromMin, 60), $fromMin % 60),
            'preferred_time_to'   => sprintf('%02d:%02d', intdiv($toMin, 60), $toMin % 60),
            'label'               => $dayNames[$dow] . ' ' . $fasciaLabel,
        ];
    }
}
```

Passare `$showPreferencePrompt` e `$prefillPreferences` alla view.

- [ ] **Step 2: Aggiungi il banner nella view show**

In `resources/views/portal/appointments/show.blade.php`, dopo il blocco riepilogo appuntamento:

```blade
@if($showPreferencePrompt ?? false)
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-5">
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
            Vuoi salvare il {{ $prefillPreferences['label'] }} come preferenza?
        </p>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 mb-4">
            Ti suggeriremo slot simili per i prossimi appuntamenti.
        </p>
        <div class="flex gap-3">
            <form method="POST" action="{{ route('portal.settings.booking-preferences') }}">
                @csrf
                @method('PATCH')
                @foreach($prefillPreferences['preferred_days'] as $d)
                    <input type="hidden" name="preferred_days[]" value="{{ $d }}">
                @endforeach
                <input type="hidden" name="preferred_time_from" value="{{ $prefillPreferences['preferred_time_from'] }}">
                <input type="hidden" name="preferred_time_to"   value="{{ $prefillPreferences['preferred_time_to'] }}">
                <button type="submit"
                    class="rounded-md bg-gray-900 dark:bg-gray-100 px-4 py-2 text-sm font-semibold text-white dark:text-gray-900 hover:opacity-90 transition-opacity">
                    Salva preferenza
                </button>
            </form>
            <form method="POST" action="{{ route('portal.settings.booking-preferences.dismiss') }}">
                @csrf
                <button type="submit"
                    class="rounded-md border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    No, grazie
                </button>
            </form>
        </div>
    </div>
@endif
```

- [ ] **Step 3: Scrivi i test**

```php
it('shows preference prompt when customer has no preferences', function () {
    $user = /* customer senza preferenze */;
    $appt = /* appuntamento confermato */;

    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('showPreferencePrompt', true)
        ->assertSee('Vuoi salvare');
});

it('does not show prompt when preferences already exist', function () {
    /* customer con preferred_days impostati */
    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('showPreferencePrompt', false);
});

it('does not show prompt when dismissed', function () {
    /* customer senza preferenze ma con dismissed=true */
    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('showPreferencePrompt', false);
});

it('calculates fascia label correctly', function () {
    // Appuntamento alle 14:30 → pomeriggio
    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('prefillPreferences', fn ($p) => str_contains($p['label'], 'pomeriggio'));
});
```

- [ ] **Step 4: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Controllers/Portal/AppointmentPreferencePromptTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Portal/AppointmentController.php \
        resources/views/portal/appointments/show.blade.php \
        tests/Feature/Controllers/Portal/AppointmentPreferencePromptTest.php
git commit -m "feat: show save-preferences prompt after first booking"
```

---

### Task 6: Admin Filament — sezione preferenze in CustomerResource

**Files:**
- Modify: `app/Filament/Resources/CustomerResource.php`

**Interfaces:**
- Consumes: `UserPreference` con nuovi campi (Task 1)
- Produces: sezione "Preferenze prenotazione" nel form edit cliente

**Nota sulla relazione Filament:** `->relationship('preferences')` su Filament funziona correttamente solo se la relazione `User::preferences()` è un `hasOne` che punta al record giusto. Poiché `user_id` è UNIQUE su `user_preferences`, c'è un solo record per utente. Tuttavia, il global scope `BelongsToBusiness` potrebbe filtrarlo a null se il `business_id` salvato non corrisponde al business corrente in Filament. Verificare in fase di implementazione: se `->relationship('preferences')` restituisce il record correttamente, usarlo; altrimenti usare un `afterSave` hook che salva direttamente tramite `UserPreference::updateOrCreate`.

- [ ] **Step 1: Aggiungi il metodo privato `timeOptions()`**

In `CustomerResource`, aggiungere:

```php
private static function timeOptions(): array
{
    $options = [];
    for ($h = 7; $h <= 21; $h++) {
        $options[sprintf('%02d:00', $h)] = sprintf('%02d:00', $h);
        if ($h < 21) $options[sprintf('%02d:30', $h)] = sprintf('%02d:30', $h);
    }
    return $options;
}
```

- [ ] **Step 2: Aggiungi la Section nel form**

In `CustomerResource::form()`, dopo le sezioni esistenti:

```php
Forms\Components\Section::make('Preferenze prenotazione')
    ->relationship('preferences')
    ->schema([
        Forms\Components\CheckboxList::make('preferred_days')
            ->label('Giorni preferiti')
            ->options(function () {
                $dayLabels = [
                    0 => 'Domenica', 1 => 'Lunedì', 2 => 'Martedì',
                    3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato',
                ];
                // AvailabilityRule ha BelongsToBusiness — auto-scoped al business corrente
                return collect(
                    \App\Models\AvailabilityRule::where('is_available', true)
                        ->distinct()->pluck('day_of_week')->sort()->values()->all()
                )->mapWithKeys(fn ($d) => [$d => $dayLabels[$d]])->all();
            })
            ->columns(4)
            ->nullable(),
        Forms\Components\Grid::make(2)->schema([
            Forms\Components\Select::make('preferred_time_from')
                ->label('Dalle')
                ->options(self::timeOptions())
                ->placeholder('Qualsiasi')
                ->nullable(),
            Forms\Components\Select::make('preferred_time_to')
                ->label('Alle')
                ->options(self::timeOptions())
                ->placeholder('Qualsiasi')
                ->nullable(),
        ]),
    ])
    ->collapsible(),
```

- [ ] **Step 3: Verifica che `->relationship('preferences')` funzioni**

Aprire l'admin, navigare su un cliente, verificare che la sezione mostri i valori corretti e che il salvataggio aggiorni `user_preferences`. Se il global scope causa problemi (sezione vuota o errore al salvataggio), sostituire con un `afterSave` esplicito:

```php
->afterSave(function ($record, array $data) {
    \App\Models\UserPreference::updateOrCreate(
        ['user_id' => $record->id, 'business_id' => app('current_business_id')],
        [
            'preferred_days'      => $data['preferred_days'] ?? null,
            'preferred_time_from' => $data['preferred_time_from'] ?? null,
            'preferred_time_to'   => $data['preferred_time_to'] ?? null,
        ]
    );
})
```

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/CustomerResource.php
git commit -m "feat: add booking preferences section to admin customer resource"
```
