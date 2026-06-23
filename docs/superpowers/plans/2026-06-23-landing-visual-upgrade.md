# Landing Page Visual Upgrade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sostituire il mockup HTML con screenshot reali + tab switcher, aggiungere una sezione video demo, e migliorare il layout dei testimonial.

**Architecture:** Modifiche esclusivamente a `resources/views/landing.blade.php` + creazione directory asset statici (`public/img/screenshots/`, `public/video/`). Nessun backend, nessuna route nuova. I file PNG/MP4 sono asset manuali che l'utente aggiunge dopo — il codice li referenzia ma non li genera.

**Tech Stack:** Laravel Blade, Alpine.js v3 (già caricato), Tailwind CSS (già configurato)

## Global Constraints

- File Blade da modificare: solo `resources/views/landing.blade.php`
- Alpine.js è già presente — usare `x-data`, `x-show`, `:class` senza import aggiuntivi
- Classi Tailwind da usare: solo quelle già nel progetto (`text-terra`, `bg-cream-dark`, `border-warm-border`, `bg-terra-light`, ecc.)
- Screenshot path: `/img/screenshots/screenshot-lista.png`, `screenshot-calendario.png`, `screenshot-booking.png`
- Video path: `/video/demo.mp4`
- Nessun test automatizzato per le viste Blade — verifica visiva nel browser a `http://localhost`
- Comandi eseguiti dentro Docker: `docker-compose run --rm app <cmd>`

---

### Task 1: Product Preview — Tab switcher con screenshot reali

**Files:**
- Modify: `resources/views/landing.blade.php` (sezione `{{-- PRODUCT PREVIEW --}}`, blocco `{{-- Right: admin panel mockup --}}`)
- Create: `public/img/screenshots/.gitkeep`

**Interfaces:**
- Produces: referenze a `/img/screenshots/screenshot-lista.png` e `/img/screenshots/screenshot-calendario.png` (file PNG da aggiungere manualmente)

- [ ] **Step 1: Crea la directory screenshot**

```bash
mkdir -p public/img/screenshots
touch public/img/screenshots/.gitkeep
```

- [ ] **Step 2: Sostituisci il mockup HTML**

In `resources/views/landing.blade.php`, sostituisci il blocco che inizia con `{{-- Right: admin panel mockup --}}` e termina con `</div>{{-- /right col --}}` con il seguente codice:

```blade
            {{-- Right: screenshot tab switcher --}}
            <div data-r style="--d:1" x-data="{ tab: 'lista' }">
                <div class="rounded-xl overflow-hidden shadow-2xl shadow-black/60 border border-white/10">

                    {{-- Chrome bar --}}
                    <div class="flex items-center gap-3 px-4 py-2.5" style="background:#1a1b26">
                        <div class="flex gap-1.5 shrink-0">
                            <div class="w-3 h-3 rounded-full" style="background:#ff5f57"></div>
                            <div class="w-3 h-3 rounded-full" style="background:#febc2e"></div>
                            <div class="w-3 h-3 rounded-full" style="background:#28c840"></div>
                        </div>
                        <div class="flex-1 rounded px-3 py-1 text-xs font-mono" style="background:#2a2b3d;color:rgba(255,255,255,0.3)">
                            gestionale.pro/admin
                        </div>
                    </div>

                    {{-- Tab bar --}}
                    <div class="flex" style="background:#1a1b26;border-bottom:1px solid rgba(255,255,255,0.08)">
                        <button @click="tab = 'lista'"
                                class="px-4 py-2.5 text-xs font-medium transition-colors border-b-2"
                                :class="tab === 'lista' ? 'text-terra border-terra' : 'text-white/40 border-transparent hover:text-white/70'">
                            Lista appuntamenti
                        </button>
                        <button @click="tab = 'calendario'"
                                class="px-4 py-2.5 text-xs font-medium transition-colors border-b-2"
                                :class="tab === 'calendario' ? 'text-terra border-terra' : 'text-white/40 border-transparent hover:text-white/70'">
                            Calendario
                        </button>
                    </div>

                    {{-- Screenshots --}}
                    <div style="height:360px" class="overflow-hidden bg-cream">
                        <img x-show="tab === 'lista'"
                             src="/img/screenshots/screenshot-lista.png"
                             alt="Lista appuntamenti nel pannello admin"
                             class="w-full h-full object-cover object-top">
                        <img x-show="tab === 'calendario'"
                             src="/img/screenshots/screenshot-calendario.png"
                             alt="Vista calendario con drag-and-drop"
                             class="w-full h-full object-cover object-top">
                    </div>

                </div>
            </div>
```

- [ ] **Step 3: Verifica visiva**

Apri `http://localhost`. Scorri alla sezione "Il pannello che semplifica ogni giornata". Devono comparire due tab ("Lista appuntamenti" e "Calendario") sopra un riquadro scuro. Cliccando sulle tab il contenuto deve switchare (area vuota finché i PNG non sono aggiunti, ma il tab switching deve funzionare).

- [ ] **Step 4: Commit**

```bash
git add resources/views/landing.blade.php public/img/screenshots/.gitkeep
git commit -m "feat: replace HTML mockup with screenshot tab switcher"
```

---

### Task 2: Sezione Video Demo

**Files:**
- Modify: `resources/views/landing.blade.php` (tra `</section>` di "Come funziona" e `{{-- PRODUCT PREVIEW --}}`)
- Create: `public/video/.gitkeep`

**Interfaces:**
- Consumes: `/img/screenshots/screenshot-lista.png` come poster del video (da Task 1)
- Produces: referenza a `/video/demo.mp4` (file MP4 da aggiungere manualmente)

- [ ] **Step 1: Crea la directory video**

```bash
mkdir -p public/video
touch public/video/.gitkeep
```

- [ ] **Step 2: Inserisci la sezione video**

In `resources/views/landing.blade.php`, trova esattamente questa sequenza (righe 290-293):

```
</section>


{{-- PRODUCT PREVIEW --}}
```

Sostituiscila con:

```blade
</section>


{{-- VIDEO DEMO --}}
<section class="py-24 px-6 bg-cream-dark">
    <div class="max-w-4xl mx-auto text-center">
        <p class="text-xs font-semibold text-terra uppercase tracking-widest mb-3" data-r style="--d:0">Demo</p>
        <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:1">
            Guardalo in azione
        </h2>
        <p class="text-ink-muted mb-10" data-r style="--d:2">Da zero a prima prenotazione: meno di 2 minuti.</p>
        <div class="rounded-2xl overflow-hidden shadow-2xl border border-warm-border" data-r style="--d:3">
            <video controls
                   poster="/img/screenshots/screenshot-lista.png"
                   class="w-full block"
                   preload="none">
                <source src="/video/demo.mp4" type="video/mp4">
            </video>
        </div>
    </div>
</section>


{{-- PRODUCT PREVIEW --}}
```

- [ ] **Step 3: Verifica visiva**

Apri `http://localhost`. Scorri dopo "Come funziona" (i 3 passi). Deve apparire la sezione "Guardalo in azione" con il player video. Se `screenshot-lista.png` esiste, è visibile come poster; altrimenti il player è nero. Il video non si avvia finché non viene aggiunto `public/video/demo.mp4`.

- [ ] **Step 4: Commit**

```bash
git add resources/views/landing.blade.php public/video/.gitkeep
git commit -m "feat: add video demo section to landing page"
```

---

### Task 3: Miglioramento testimonial + screenshot booking in feature

**Files:**
- Modify: `resources/views/landing.blade.php` (sezione `{{-- TESTIMONIALS --}}` e sezione `{{-- FEATURES --}}`)

**Interfaces:**
- Produces: referenza a `/img/screenshots/screenshot-booking.png` (file PNG da aggiungere manualmente)

- [ ] **Step 1: Sostituisci il loop dei testimonial**

In `resources/views/landing.blade.php`, trova il blocco `<div class="grid grid-cols-1 md:grid-cols-3 gap-6">` nella sezione `{{-- TESTIMONIALS --}}` (il div che contiene il `@foreach` delle tre recensioni) e sostituisci l'intero blocco con:

```blade
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['quote'=>'Prima passavo mezz\'ora ogni mattina a confermare appuntamenti su WhatsApp. Adesso arrivano i clienti e basta. I promemoria pensano a tutto, io non devo fare niente.','name'=>'Giulia Rossi',  'badge'=>'Parrucchiera','city'=>'Milano','initial'=>'G','color'=>'bg-terra'],
                ['quote'=>'Ho tre colleghi in salone e prima era il caos: turni sbagliati, pagamenti da registrare a mano. Adesso tutto è in ordine e so sempre com\'è andata la settimana.',    'name'=>'Marco Torrisi', 'badge'=>'Barbiere',    'city'=>'Roma',   'initial'=>'M','color'=>'bg-indigo-500'],
                ['quote'=>'Le mie clienti prenotano quando vogliono, anche a mezzanotte. Non rispondo più a nessun messaggio per gli appuntamenti. E le prenotazioni sono aumentate.',              'name'=>'Alessia Marino','badge'=>'Estetista',   'city'=>'Torino', 'initial'=>'A','color'=>'bg-rose-500'],
            ] as $i => $t)
            <article class="bg-cream-dark rounded-2xl p-8 flex flex-col" data-r style="--d:{{ $i }}">
                <div class="flex gap-1 mb-5">
                    @for($s = 0; $s < 5; $s++)
                    <svg class="w-5 h-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    @endfor
                </div>
                <p class="text-sm text-ink-muted leading-relaxed mb-6 flex-1 italic">"{{ $t['quote'] }}"</p>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full {{ $t['color'] }} flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {{ $t['initial'] }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ $t['name'] }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="bg-terra-light text-terra text-xs rounded-full px-2 py-0.5 font-medium">{{ $t['badge'] }}</span>
                            <span class="text-xs text-ink-muted">{{ $t['city'] }}</span>
                        </div>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
```

Modifiche rispetto all'originale: rimossa la virgoletta gigante `&ldquo;`, stelle da `w-4 h-4` a `w-5 h-5`, testo quote in `italic`, aggiunto badge attività + città separati al posto del campo `role` unificato.

- [ ] **Step 2: Aggiungi screenshot booking nella sezione Funzionalità**

In `resources/views/landing.blade.php`, trova questa riga esatta nella sezione `{{-- FEATURES --}}`:

```
                    <p class="text-sm text-ink-muted leading-relaxed">I clienti prenotano dal telefono in qualsiasi momento. Nessuna telefonata, nessun messaggio da gestire.</p>
```

Sostituiscila con:

```blade
                    <p class="text-sm text-ink-muted leading-relaxed">I clienti prenotano dal telefono in qualsiasi momento. Nessuna telefonata, nessun messaggio da gestire.</p>
                    <img src="/img/screenshots/screenshot-booking.png"
                         alt="Pagina prenotazione online"
                         class="rounded-xl border border-warm-border shadow-sm mt-4 w-full max-w-xs">
```

- [ ] **Step 3: Verifica visiva**

Apri `http://localhost`. Controlla:
1. **Testimonial**: niente virgoletta gigante, stelle più grandi, quote in corsivo, badge tipo ("Parrucchiera" / "Barbiere" / "Estetista") in color terra + città a fianco
2. **Funzionalità**: sotto "Prenotazioni Online 24/7" compare un'immagine piccola (visibile solo quando `screenshot-booking.png` è presente)

- [ ] **Step 4: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: improve testimonial layout and add booking screenshot to features"
```

---

## Note per l'utente: asset da aggiungere manualmente

Dopo l'implementazione, aggiungere i seguenti file:

| File | Come ottenerlo |
|---|---|
| `public/img/screenshots/screenshot-lista.png` | Screenshot di `/admin/appointments` — larghezza ≥1200px |
| `public/img/screenshots/screenshot-calendario.png` | Screenshot di `/admin/calendar` — stessa larghezza |
| `public/img/screenshots/screenshot-booking.png` | Screenshot della pagina pubblica di prenotazione |
| `public/video/demo.mp4` | Registrazione schermo (QuickTime → File → Nuova registrazione schermo) |

Senza questi file la pagina funziona correttamente ma mostra aree vuote al posto di immagini e video.
