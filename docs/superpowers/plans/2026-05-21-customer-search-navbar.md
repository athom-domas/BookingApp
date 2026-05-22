# Customer Search Navbar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere un input di ricerca clienti nella topbar dell'admin Filament con dropdown appuntamenti e shortcut Ctrl+K.

**Architecture:** Livewire component `CustomerSearch` inserito nella topbar già customizzata, seguendo il pattern di `PendingCompletionNotifications`. Il componente cerca `User` con ruolo `customer` via Spatie, eager-load degli appuntamenti, Alpine.js gestisce dropdown e Ctrl+K.

**Tech Stack:** Laravel 13, Livewire 3, Alpine.js, Filament 4, Spatie Permission

---

### Task 1: Livewire component

**Files:**
- Create: `app/Livewire/CustomerSearch.php`
- Test: `tests/Feature/Livewire/CustomerSearchTest.php`

- [ ] **Step 1: Crea il file di test**

```php
<?php

use App\Livewire\CustomerSearch;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

test('returns empty results when query is shorter than 2 characters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'M')
        ->assertDontSee('Mario Rossi');
});

test('returns customers matching by name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'Mario')
        ->assertSee('Mario Rossi');
});

test('returns customers matching by email', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'mario@')
        ->assertSee('Mario Rossi');
});

test('does not return staff or admin users in results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create(['name' => 'Mario Staff']);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'Mario')
        ->assertDontSee('Mario Staff');
});

test('non-admin and non-staff users see no results', function () {
    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $customer->assignRole('customer');

    $other = User::factory()->create(['name' => 'Luigi Verdi']);
    $other->assignRole('customer');

    $this->actingAs($customer);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'Luigi')
        ->assertDontSee('Luigi Verdi');
});

test('limits results to 5 customers', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    User::factory()->count(6)
        ->sequence(fn($seq) => ['name' => 'TestUser' . $seq->index])
        ->create()
        ->each(fn($c) => $c->assignRole('customer'));

    $this->actingAs($admin);

    $count = Livewire::test(CustomerSearch::class)
        ->set('query', 'TestUser')
        ->instance()
        ->results
        ->count();

    expect($count)->toBe(5);
});
```

- [ ] **Step 2: Esegui il test per verificare che fallisce**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Livewire/CustomerSearchTest.php
```

Atteso: errore `Class "App\Livewire\CustomerSearch" not found`

- [ ] **Step 3: Crea il componente Livewire**

```php
<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CustomerSearch extends Component
{
    public string $query = '';

    #[Computed]
    public function results(): Collection
    {
        if (! auth()->user()?->isAdmin() && ! auth()->user()?->isStaff()) {
            return collect();
        }

        if (strlen($this->query) < 2) {
            return collect();
        }

        return User::role('customer')
            ->where(function ($q) {
                $q->where('name', 'like', '%' . $this->query . '%')
                  ->orWhere('email', 'like', '%' . $this->query . '%');
            })
            ->with(['appointmentsAsCustomer' => fn ($q) => $q->orderBy('scheduled_date', 'desc')])
            ->limit(5)
            ->get();
    }

    public function render()
    {
        return view('livewire.customer-search');
    }
}
```

- [ ] **Step 4: Esegui i test per verificare che passano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Livewire/CustomerSearchTest.php
```

Atteso: tutti i test passano (verde)

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/CustomerSearch.php tests/Feature/Livewire/CustomerSearchTest.php
git commit -m "feat: add CustomerSearch Livewire component"
```

---

### Task 2: Blade view

**Files:**
- Create: `resources/views/livewire/customer-search.blade.php`

- [ ] **Step 1: Crea la view**

```blade
<div>
    @if (auth()->user()?->isAdmin() || auth()->user()?->isStaff())
        <div
            x-data="{ open: false }"
            @keydown.ctrl.k.window.prevent="$refs.searchInput.focus()"
            @keydown.escape.window="open = false"
            @click.outside="open = false"
            class="relative"
        >
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    x-ref="searchInput"
                    wire:model.live.debounce.300ms="query"
                    @input="open = $event.target.value.length >= 2"
                    type="text"
                    placeholder="Cerca cliente... (Ctrl+K)"
                    autocomplete="off"
                    class="block w-52 rounded-lg border border-gray-200 bg-white py-1.5 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500"
                />
            </div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute right-0 top-full z-50 mt-2 w-96 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900"
                style="display: none"
            >
                <div wire:loading wire:target="query" class="flex items-center justify-center py-6">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                </div>

                <div wire:loading.remove wire:target="query">
                    @if ($this->results->isEmpty())
                        <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                            <x-filament::icon icon="heroicon-o-user-slash" class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                            <p class="text-sm text-gray-400 dark:text-gray-500">Nessun cliente trovato</p>
                        </div>
                    @else
                        <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                            @foreach ($this->results as $customer)
                                <div class="px-3 py-2.5">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $customer->name }}</p>
                                    <p class="mb-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $customer->email }}</p>

                                    @if ($customer->appointmentsAsCustomer->isEmpty())
                                        <p class="text-xs text-gray-400 dark:text-gray-500">Nessun appuntamento</p>
                                    @else
                                        <div class="space-y-0.5">
                                            @foreach ($customer->appointmentsAsCustomer as $appointment)
                                                @php
                                                    $statusConfig = match($appointment->status) {
                                                        'pending'   => ['label' => 'In attesa',   'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400'],
                                                        'confirmed' => ['label' => 'Confermato',  'class' => 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'],
                                                        'completed' => ['label' => 'Completato',  'class' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400'],
                                                        'cancelled' => ['label' => 'Annullato',   'class' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'],
                                                        default     => ['label' => $appointment->status, 'class' => 'bg-gray-100 text-gray-600'],
                                                    };
                                                @endphp
                                                <a
                                                    href="{{ \App\Filament\Resources\AppointmentResource::getUrl('edit', ['record' => $appointment->id]) }}"
                                                    @click="open = false"
                                                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                                                >
                                                    <span class="shrink-0 font-medium text-gray-700 dark:text-gray-300">
                                                        {{ $appointment->scheduled_date->format('d/m/Y H:i') }}
                                                    </span>
                                                    <span class="min-w-0 flex-1 truncate text-gray-500 dark:text-gray-400">
                                                        {{ $appointment->services_label }}
                                                    </span>
                                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $statusConfig['class'] }}">
                                                        {{ $statusConfig['label'] }}
                                                    </span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/livewire/customer-search.blade.php
git commit -m "feat: add customer-search blade view with Alpine.js dropdown"
```

---

### Task 3: Integrazione nella topbar

**Files:**
- Modify: `resources/views/vendor/filament-panels/livewire/topbar.blade.php` (linea 252)

- [ ] **Step 1: Aggiungi il componente nella topbar**

Nel file `resources/views/vendor/filament-panels/livewire/topbar.blade.php`, trova la riga con `@livewire('pending-completion-notifications')` (linea 252) e inserisci il componente sopra di essa:

```blade
            @livewire('customer-search')

            @livewire('pending-completion-notifications')
```

Il blocco completo nella sezione `fi-topbar-end` diventa:

```blade
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

            @if (filament()->isGlobalSearchEnabled() && filament()->getGlobalSearchPosition() === \Filament\Enums\GlobalSearchPosition::Topbar)
                @livewire(Filament\Livewire\GlobalSearch::class)
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

            @livewire('customer-search')

            @livewire('pending-completion-notifications')
```

- [ ] **Step 2: Esegui la suite completa per regressioni**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Atteso: tutti i test passano

- [ ] **Step 3: Commit**

```bash
git add resources/views/vendor/filament-panels/livewire/topbar.blade.php
git commit -m "feat: integrate customer-search component in admin topbar"
```
