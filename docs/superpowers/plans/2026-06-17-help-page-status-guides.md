# Help Page — Pannello Stato e Nuove Guide — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere alla pagina Aiuto un banner di stato delle integrazioni (visibile solo se qualcosa manca) e due nuove guide (Setup salone, Staff e servizi) con griglia a 3 colonne.

**Architecture:** `HelpPage.php` (Livewire/Filament Page) aggiunge `mount()` che calcola gli stati e li espone come proprietà pubblica. Il blade legge `$integrationStatuses` e `$integrationSettingsUrl` lato PHP — nessun JS aggiuntivo. Le nuove guide seguono il pattern Alpine `x-show="guide === 'key'"` già esistente.

**Tech Stack:** Laravel 13, Filament 4, Livewire v3, Alpine.js, Tailwind CSS v4

---

### Task 1: HelpPage.php — mount() e integrationStatuses

**Files:**
- Modify: `app/Filament/Pages/HelpPage.php`

- [ ] **Step 1: Sostituire il contenuto di HelpPage.php con la versione aggiornata**

```php
<?php

namespace App\Filament\Pages;

use App\Models\IntegrationSetting;
use Filament\Pages\Page;

class HelpPage extends Page
{
    protected static ?string $navigationLabel = 'Aiuto';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $slug = 'aiuto';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.help';

    public array $integrationStatuses = [];
    public string $integrationSettingsUrl = '';

    public function mount(): void
    {
        $setting = IntegrationSetting::current();

        $this->integrationSettingsUrl = IntegrationSettings::getUrl();

        $this->integrationStatuses = [
            'stripe' => [
                'label'      => 'Stripe',
                'configured' => ! empty($setting->stripe_public_key) && ! empty($setting->stripe_secret_key),
            ],
            'whatsapp' => [
                'label'      => 'WhatsApp',
                'configured' => IntegrationSetting::hasMetaWhatsApp(),
            ],
            'google_calendar' => [
                'label'      => 'Google Calendar',
                'configured' => ! empty($setting->google_calendar_id) && ! empty($setting->google_credentials_json),
            ],
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Pages/HelpPage.php
git commit -m "feat: add integration status to HelpPage"
```

---

### Task 2: help.blade.php — banner di stato

**Files:**
- Modify: `resources/views/filament/pages/help.blade.php`

Il banner va inserito tra `<x-filament-panels::page>` (riga 1) e il div `x-data` (riga 2).

- [ ] **Step 1: Aggiungere il banner di stato dopo `<x-filament-panels::page>` e prima del div x-data**

Il file attualmente inizia così:
```html
<x-filament-panels::page>
    <div x-data="{ guide: null }">
```

Diventa:
```html
<x-filament-panels::page>
    @php $unconfigured = collect($integrationStatuses)->where('configured', false)->values() @endphp
    @if($unconfigured->isNotEmpty())
    <div class="mb-6 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-5 py-4">
        <div class="flex items-center gap-2 mb-3">
            <svg class="h-4 w-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Integrazioni da configurare</p>
        </div>
        <div class="space-y-1.5">
            @foreach($unconfigured as $status)
            <div class="flex items-center justify-between">
                <p class="text-sm text-amber-700 dark:text-amber-300">{{ $status['label'] }} non è ancora configurato</p>
                <a href="{{ $integrationSettingsUrl }}" class="text-xs font-medium text-amber-700 dark:text-amber-400 hover:underline whitespace-nowrap ml-4">Configura ora →</a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div x-data="{ guide: null }">
```

- [ ] **Step 2: Verificare visivamente**

Aprire `/admin/aiuto`. Se almeno una delle tre integrazioni non è configurata, il banner amber deve apparire con un riga per ogni integrazione mancante e il link "Configura ora →". Se tutte sono configurate, il banner non appare.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/help.blade.php
git commit -m "feat: add integration status banner to help page"
```

---

### Task 3: help.blade.php — griglia 3 colonne e nuove card

**Files:**
- Modify: `resources/views/filament/pages/help.blade.php`

La sezione `{{-- INDICE GUIDE --}}` contiene attualmente:
```html
<div class="grid gap-4 sm:grid-cols-2">
```
con 3 button (Stripe, WhatsApp, Calendar).

- [ ] **Step 1: Cambiare la griglia a 3 colonne e aggiungere le due nuove card PRIMA delle esistenti**

Sostituire:
```html
<div class="grid gap-4 sm:grid-cols-2">

                <button @click="guide = 'stripe'"
```

Con:
```html
<div class="grid gap-4 sm:grid-cols-3">

                <button @click="guide = 'setup-salone'"
                    class="group text-left rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm hover:border-amber-400 dark:hover:border-amber-500 hover:shadow-md transition-all">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40 group-hover:bg-amber-200 dark:group-hover:bg-amber-800/60 transition-colors">
                            <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 2.189a3.004 3.004 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">Setup iniziale del salone</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Come configurare profilo, logo, orari e aspetto del portale clienti per iniziare ad accettare prenotazioni.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                        Leggi la guida
                        <svg class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </button>

                <button @click="guide = 'staff-servizi'"
                    class="group text-left rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm hover:border-violet-400 dark:hover:border-violet-500 hover:shadow-md transition-all">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40 group-hover:bg-violet-200 dark:group-hover:bg-violet-800/60 transition-colors">
                            <svg class="h-5 w-5 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">Gestione staff e servizi</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Come aggiungere collaboratori, creare servizi, assegnarli allo staff e impostare la disponibilità.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-xs font-medium text-violet-600 dark:text-violet-400">
                        Leggi la guida
                        <svg class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </button>

                <button @click="guide = 'stripe'"
```

- [ ] **Step 2: Verificare visivamente**

Aprire `/admin/aiuto`. L'indice deve mostrare 5 card in griglia 3 colonne: Setup salone (amber), Staff e servizi (violet), Stripe (indigo), WhatsApp (green), Calendar (blue).

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/help.blade.php
git commit -m "feat: add setup salone and staff guide cards (3-col grid)"
```

---

### Task 4: help.blade.php — guida Setup iniziale del salone

**Files:**
- Modify: `resources/views/filament/pages/help.blade.php`

Aggiungere la sezione guida subito prima di `{{-- GUIDA: STRIPE --}}` (che è la prima guida dettaglio).

- [ ] **Step 1: Inserire la guida Setup salone prima di `{{-- GUIDA: STRIPE --}}`**

```html
        {{-- GUIDA: SETUP SALONE --}}
        <div x-show="guide === 'setup-salone'" x-transition.opacity style="display:none">
            <div class="mb-5">
                <button @click="guide = null" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                    </svg>
                    Torna alle guide
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-6 py-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                        <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 2.189a3.004 3.004 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Setup iniziale del salone</h2>
                </div>
                <div class="px-6 py-5 space-y-5 text-sm text-gray-700 dark:text-gray-300">
                    <p>Prima di accettare prenotazioni, configura le informazioni base del tuo salone. I passaggi qui sotto bastano per rendere il portale clienti operativo.</p>

                    <ol class="space-y-5 list-none">
                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-600 text-xs font-bold text-white">1</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Inserisci i dati del salone</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Dal menu laterale vai su <strong>Impostazioni → Profilo Salone</strong>. Inserisci nome, indirizzo, numero di telefono e una breve descrizione. Questi dati vengono mostrati ai clienti sul portale e nelle email di conferma.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-600 text-xs font-bold text-white">2</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Carica il logo</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Nella stessa pagina Profilo Salone, scorri fino alla sezione <strong>Logo</strong> e carica il logo del tuo salone. Il logo compare nell'header del portale clienti, nelle email e nelle notifiche. Formato consigliato: PNG o SVG, sfondo trasparente.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-600 text-xs font-bold text-white">3</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Personalizza l'aspetto del portale</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <strong>Impostazioni → Impostazioni di sistema</strong> → sezione <strong>Aspetto</strong>. Scegli il tema colore, il font e lo stile dei bordi per dare al portale clienti l'identità visiva del tuo salone. Le modifiche sono visibili in tempo reale.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-600 text-xs font-bold text-white">4</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Imposta gli orari di apertura</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <strong>Salone → Regole di disponibilità</strong> → clicca <strong>Nuova regola</strong>. Aggiungi una regola per ogni giorno lavorativo, specificando l'orario di inizio e fine. I clienti potranno prenotare solo negli orari in cui almeno un collaboratore è disponibile.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">✓</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Il portale è pronto</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Il tuo portale clienti è attivo e personalizzato. I clienti possono visitarlo e — una volta aggiunti staff e servizi — iniziare a prenotare.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-4 py-3 text-xs text-amber-700 dark:text-amber-300">
                        <strong>Prossimo passo:</strong> aggiungi i collaboratori e i servizi — consulta la guida <strong>Gestione staff e servizi</strong>.
                    </div>
                </div>
            </div>
        </div>

```

- [ ] **Step 2: Verificare visivamente**

Aprire `/admin/aiuto` e cliccare su "Setup iniziale del salone". La guida deve aprirsi mostrando 4 steps numerati in amber + il check verde finale + il callout amber in fondo. Il pulsante "Torna alle guide" deve riportare all'indice.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/help.blade.php
git commit -m "feat: add setup salone guide"
```

---

### Task 5: help.blade.php — guida Gestione staff e servizi

**Files:**
- Modify: `resources/views/filament/pages/help.blade.php`

Aggiungere subito dopo la guida Setup salone (Task 4) e prima di `{{-- GUIDA: STRIPE --}}`.

- [ ] **Step 1: Inserire la guida Staff e servizi**

```html
        {{-- GUIDA: STAFF E SERVIZI --}}
        <div x-show="guide === 'staff-servizi'" x-transition.opacity style="display:none">
            <div class="mb-5">
                <button @click="guide = null" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                    </svg>
                    Torna alle guide
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-6 py-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40">
                        <svg class="h-4 w-4 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Gestione staff e servizi</h2>
                </div>
                <div class="px-6 py-5 space-y-5 text-sm text-gray-700 dark:text-gray-300">
                    <p>Per accettare prenotazioni devi avere almeno un collaboratore con almeno un servizio assegnato. Segui questi passaggi nell'ordine indicato.</p>

                    <ol class="space-y-5 list-none">
                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white">1</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Aggiungi i collaboratori</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <strong>Salone → Staff</strong> → clicca <strong>Nuovo collaboratore</strong>. Inserisci nome, email e assegna il ruolo <strong>Staff</strong>. Il collaboratore riceverà un'email di invito per impostare la propria password e accedere al pannello.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white">2</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Crea i servizi</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <strong>Salone → Servizi</strong> → clicca <strong>Nuovo servizio</strong>. Per ogni servizio specifica: nome (es. "Taglio donna"), durata in minuti e prezzo. Puoi aggiungere una descrizione opzionale che i clienti vedranno sul portale.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white">3</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Assegna i servizi allo staff</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Torna su <strong>Salone → Staff</strong> e apri la scheda di ogni collaboratore. Nella sezione <strong>Servizi</strong> seleziona quali servizi è in grado di eseguire. Un servizio non assegnato a nessun collaboratore non è prenotabile dai clienti.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white">4</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Imposta la disponibilità</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <strong>Salone → Regole di disponibilità</strong>. Puoi impostare regole globali per il salone (applicate a tutti) oppure regole specifiche per singolo collaboratore selezionandolo dal filtro. Ogni regola definisce giorno della settimana, orario di inizio e fine.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">✓</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Tutto pronto</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">I clienti possono ora prenotare i tuoi servizi online, scegliendo il collaboratore preferito e l'orario disponibile.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-800 px-4 py-3 text-xs text-violet-700 dark:text-violet-300">
                        <strong>Suggerimento:</strong> se un collaboratore va in ferie o ha un'assenza, puoi bloccare singoli giorni aggiungendo una regola di indisponibilità con orario 00:00–00:00 per quel giorno specifico.
                    </div>
                </div>
            </div>
        </div>

```

- [ ] **Step 2: Verificare visivamente**

Aprire `/admin/aiuto` e cliccare su "Gestione staff e servizi". La guida deve mostrare 4 steps violet + check verde + callout violet. Testare anche che le 3 guide esistenti (Stripe, WhatsApp, Calendar) continuino a funzionare correttamente.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/help.blade.php
git commit -m "feat: add staff and services guide"
```
