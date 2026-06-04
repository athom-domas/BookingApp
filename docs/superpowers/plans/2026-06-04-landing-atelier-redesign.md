# Landing Atelier Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sostituire l'estetica SaaS generica (teal + slate + blob + gradient animati) con un'identità editoriale calda: palette terracotta/crema, Cormorant Garamond display, zero animazioni decorative superflue.

**Architecture:** Due file: `resources/css/app.css` riceve i token `@theme` Tailwind v4 e la font-family display; `resources/views/landing.blade.php` viene aggiornato sezione per sezione. Nessuna logica PHP coinvolta — cambiamenti puramente visuali.

**Tech Stack:** Tailwind CSS v4, Alpine.js (già in uso), Google Fonts (Cormorant Garamond), Laravel Blade

**Spec di riferimento:** `docs/superpowers/specs/2026-06-04-landing-atelier-redesign.md`

**Nota su testing:** Non esistono test automatici per markup HTML/CSS. Verifica = build Vite senza errori + controllo visivo nel browser.

---

## File da modificare

| File | Ruolo |
|---|---|
| `resources/css/app.css` | Aggiunta token @theme (palette + font-display) |
| `resources/views/landing.blade.php` | Redesign completo sezione per sezione |

---

### Task 1: Token CSS in app.css

**Files:**
- Modify: `resources/css/app.css`

- [ ] **Step 1: Aggiungere i token @theme**

Sostituire il blocco `@theme` esistente con questo (aggiunge i nuovi token, mantiene `--font-sans`):

```css
@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
    --font-display: 'Cormorant Garamond', Georgia, serif;
    --color-cream: #FAF7F2;
    --color-cream-dark: #F2EBE3;
    --color-ink: #1C1410;
    --color-ink-muted: #7A6A60;
    --color-terra: #C4714A;
    --color-terra-light: #F2DDD3;
    --color-warm-border: #E5D8CF;
}
```

In Tailwind v4, `--color-*` genera automaticamente classi `bg-*`, `text-*`, `border-*` e supporta modificatori opacità (es. `bg-terra/15`). `--font-display` genera la classe `font-display`.

- [ ] **Step 2: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: add atelier design tokens to tailwind theme"
```

---

### Task 2: Pulizia blocco `<style>` inline

**Files:**
- Modify: `resources/views/landing.blade.php` (righe 9–129)

- [ ] **Step 1: Rimuovere le animazioni e classi deprecate**

Nel blocco `<style>` (tra `<style>` e `</style>`), eliminare completamente questi blocchi CSS:

```css
/* RIMUOVERE — hero gradient animato */
.hero-gradient {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #134e4a 80%, #0d9488 100%);
    background-size: 220% 220%;
    animation: gradPulse 12s ease infinite;
}
@keyframes gradPulse {
    0%, 100% { background-position: 0% 50%; }
    50%       { background-position: 100% 50%; }
}

/* RIMUOVERE — cta gradient */
.cta-gradient {
    background: linear-gradient(135deg, #134e4a 0%, #0f172a 55%, #1e1b4b 100%);
    background-size: 200% 200%;
    animation: gradPulse 14s ease infinite;
}

/* RIMUOVERE — dot grid */
.dot-grid {
    background-image: radial-gradient(rgba(255,255,255,0.065) 1px, transparent 1px);
    background-size: 28px 28px;
}

/* RIMUOVERE — blob decorativi */
@keyframes blobMove {
    0%, 100% { transform: translate(0,0) scale(1); }
    33%       { transform: translate(20px,-14px) scale(1.04); }
    66%       { transform: translate(-14px,10px) scale(0.97); }
}
.blob   { animation: blobMove 8s ease-in-out infinite; }
.blob-2 { animation-delay: 2.5s; }
.blob-3 { animation-delay: 4.8s; }

/* RIMUOVERE — float pricing card */
@keyframes cardFloat {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-8px); }
}
.float-card { animation: cardFloat 4.5s ease-in-out infinite; }
```

- [ ] **Step 2: Aggiornare i colori nelle regole CSS rimaste**

Modificare `.eyebrow` — cambiare il colore da teal a terra:
```css
/* DA */
color: var(--eb-color, #0d9488);

/* A */
color: var(--eb-color, #C4714A);
```

Modificare `.text-grad` — cambiare gradient da teal/indigo a terracotta:
```css
/* DA */
.text-grad {
    background: linear-gradient(120deg, #2dd4bf, #818cf8);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* A */
.text-grad {
    background: linear-gradient(120deg, #C4714A, #8B4513);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
```

Modificare `#spb` — aggiornare gradient barra scroll:
```css
/* DA */
#spb {
    ...
    background: linear-gradient(to right, #0d9488, #818cf8);
    ...
}

/* A */
#spb {
    ...
    background: linear-gradient(to right, #C4714A, #1C1410);
    ...
}
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: remove generic animations, update accent colors to terracotta"
```

---

### Task 3: `<head>`, `<body>` e NAV

**Files:**
- Modify: `resources/views/landing.blade.php`

- [ ] **Step 1: Aggiungere Cormorant Garamond nell'`<head>`**

Aggiungere subito dopo `@vite(...)`:

```html
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
```

- [ ] **Step 2: Aggiornare tag `<body>`**

```html
<!-- DA -->
<body class="bg-white text-gray-900 antialiased">

<!-- A -->
<body class="bg-cream text-ink antialiased">
```

- [ ] **Step 3: Aggiornare NAV — header container**

```html
<!-- DA -->
:class="scrolled ? 'bg-white/95 backdrop-blur-sm shadow-sm border-b border-gray-100' : 'bg-transparent'"

<!-- A -->
:class="scrolled ? 'bg-cream/95 backdrop-blur-sm shadow-sm border-b border-warm-border' : 'bg-transparent'"
```

- [ ] **Step 4: Aggiornare NAV — logo colori**

```html
<!-- DA -->
:class="scrolled ? 'text-gray-900' : 'text-white'"
...
:class="scrolled ? 'text-teal-600' : 'text-teal-400'"

<!-- A -->
:class="scrolled ? 'text-ink' : 'text-white'"
...
:class="scrolled ? 'text-terra' : 'text-terra'"
```

Nota: il logo mostra sempre terracotta (sia scrolled che non), ma su sfondo scuro nell'hero va bene — terracotta su nero caldo è leggibile.

- [ ] **Step 5: Aggiornare NAV — link desktop colori**

```html
<!-- DA -->
:class="scrolled ? 'text-gray-500 hover:text-gray-900' : 'text-white/80 hover:text-white'"

<!-- A -->
:class="scrolled ? 'text-ink-muted hover:text-ink' : 'text-white/80 hover:text-white'"
```

- [ ] **Step 6: Aggiornare NAV — CTA button desktop**

```html
<!-- DA -->
class="shimmer text-sm font-semibold bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 transition shadow-sm"

<!-- A -->
class="shimmer text-sm font-semibold bg-terra text-white px-4 py-2 rounded-lg hover:bg-terra/90 transition shadow-sm"
```

- [ ] **Step 7: Aggiornare NAV — CTA button mobile**

```html
<!-- DA -->
class="mt-2 text-sm font-semibold bg-teal-600 text-white px-4 py-3 rounded-xl text-center hover:bg-teal-700 transition"

<!-- A -->
class="mt-2 text-sm font-semibold bg-terra text-white px-4 py-3 rounded-xl text-center hover:bg-terra/90 transition"
```

- [ ] **Step 8: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: update body, nav colors to atelier palette"
```

---

### Task 4: HERO section

**Files:**
- Modify: `resources/views/landing.blade.php` (sezione HERO, dopo il tag `{{-- HERO --}}`)

- [ ] **Step 1: Cambiare background e rimuovere elementi decorativi**

Sostituire l'apertura della `<section>` hero:
```html
<!-- DA -->
<section class="hero-gradient relative overflow-hidden min-h-[680px] flex items-center pt-16 pb-32 px-6">
    {{-- Dot grid --}}
    <div class="dot-grid absolute inset-0 pointer-events-none"></div>

    {{-- Blob 1 --}}
    <div class="blob absolute -top-40 -right-40 w-[520px] h-[520px] rounded-full pointer-events-none"
         style="background:radial-gradient(circle,rgba(13,148,136,0.2) 0%,transparent 70%)"></div>
    {{-- Blob 2 --}}
    <div class="blob blob-2 absolute -bottom-24 -left-24 w-[380px] h-[380px] rounded-full pointer-events-none"
         style="background:radial-gradient(circle,rgba(99,102,241,0.13) 0%,transparent 70%)"></div>
    {{-- Blob 3 --}}
    <div class="blob blob-3 absolute top-1/3 left-1/3 w-[260px] h-[260px] rounded-full pointer-events-none"
         style="background:radial-gradient(circle,rgba(20,184,166,0.08) 0%,transparent 70%)"></div>

<!-- A -->
<section class="bg-ink relative overflow-hidden min-h-[680px] flex items-center pt-16 pb-24 px-6">
```

- [ ] **Step 2: Aggiornare badge hero**

```html
<!-- DA -->
<div class="h-badge inline-flex items-center gap-2 bg-teal-900/60 border border-teal-700/40 rounded-full px-4 py-1.5 mb-8">
    <span class="w-1.5 h-1.5 rounded-full bg-teal-400 animate-pulse"></span>
    <span class="text-xs font-medium text-teal-300 tracking-wide">Software gestionale per saloni</span>
</div>

<!-- A -->
<div class="h-badge inline-flex items-center gap-2 bg-terra/15 border border-terra/25 rounded-full px-4 py-1.5 mb-8">
    <span class="w-1.5 h-1.5 rounded-full bg-terra animate-pulse"></span>
    <span class="text-xs font-medium text-[#F2DDD3] tracking-wide">Software gestionale per saloni</span>
</div>
```

- [ ] **Step 3: Aggiornare H1 — aggiungere font-display e dimensioni più grandi**

```html
<!-- DA -->
<h1 class="h-title text-5xl sm:text-6xl font-bold text-white leading-tight mb-6 tracking-tight">
    Porta il tuo salone a un livello più <br class="hidden sm:block">
    <span class="text-grad">professionale</span>
</h1>

<!-- A -->
<h1 class="h-title font-display text-6xl sm:text-7xl lg:text-8xl font-semibold text-white leading-[1.05] mb-6">
    Porta il tuo salone a un livello più
    <span class="text-grad"> professionale</span>
</h1>
```

Nota: rimosso `<br>` e `tracking-tight` — Cormorant Garamond va su più righe naturalmente e non necessita letter-spacing negativo.

- [ ] **Step 4: Aggiornare CTA primario hero**

```html
<!-- DA -->
class="shimmer w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-teal-500 hover:bg-teal-400 text-white font-semibold px-8 py-4 rounded-xl transition text-base shadow-lg shadow-teal-900/30"

<!-- A -->
class="shimmer w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-terra hover:bg-terra/85 text-white font-semibold px-8 py-4 rounded-xl transition text-base shadow-lg shadow-ink/30"
```

- [ ] **Step 5: Rimuovere il wave divider SVG alla fine della sezione hero**

Eliminare questo blocco prima di `</section>`:
```html
    {{-- Wave divider --}}
    <div class="absolute bottom-0 left-0 right-0 pointer-events-none">
        <svg viewBox="0 0 1440 56" class="w-full" preserveAspectRatio="none">
            <path d="M0,56 C240,18 480,48 720,28 C960,8 1200,42 1440,18 L1440,56 Z" fill="#f8fafc"/>
        </svg>
    </div>
```

Il contrasto netto tra `bg-ink` e `bg-cream-dark` della sezione successiva funge da separatore naturale.

- [ ] **Step 6: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: redesign hero — static dark bg, display font, terracotta badge"
```

---

### Task 5: TRUST BAR + PROBLEM

**Files:**
- Modify: `resources/views/landing.blade.php`

- [ ] **Step 1: Aggiornare TRUST BAR**

```html
<!-- DA -->
<section class="bg-slate-50 border-b border-gray-100 py-10 px-6">

<!-- A -->
<section class="bg-cream-dark border-b border-warm-border py-10 px-6">
```

Aggiornare i 4 numeri statistici — `text-teal-600` → `text-terra`:
```html
<!-- DA (tutte e 4 le occorrenze) -->
<span class="text-2xl font-bold text-teal-600" ...>

<!-- A -->
<span class="text-2xl font-bold text-terra" ...>
```

- [ ] **Step 2: Aggiornare sezione PROBLEM — background**

```html
<!-- DA -->
<section class="py-24 px-6 bg-white">

<!-- A -->
<section class="py-24 px-6 bg-cream">
```

- [ ] **Step 3: Aggiornare PROBLEM — H2 display font**

```html
<!-- DA -->
<h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">
    Ancora usi carta, WhatsApp<br class="hidden sm:block"> e fogli Excel per gestire il salone?
</h2>

<!-- A -->
<h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4" data-r style="--d:1">
    Ancora usi carta, WhatsApp<br class="hidden sm:block"> e fogli Excel per gestire il salone?
</h2>
```

- [ ] **Step 4: Aggiornare PROBLEM — testo secondario**

```html
<!-- DA -->
<p class="text-gray-500 max-w-xl mx-auto" data-r style="--d:2">

<!-- A -->
<p class="text-ink-muted max-w-xl mx-auto" data-r style="--d:2">
```

- [ ] **Step 5: Aggiornare PROBLEM — cards**

```html
<!-- DA -->
<div class="bg-slate-50 rounded-2xl p-8 border border-slate-100" data-r style="--d:{{ $i }}">

<!-- A -->
<div class="bg-cream-dark rounded-2xl p-8 border border-warm-border" data-r style="--d:{{ $i }}">
```

Le icone rosse `bg-red-50 text-red-400` rimangono invariate — funzionano semanticamente (problema = rosso) e il contrasto sul fondo crema è buono.

Aggiornare i testi delle card problem `text-gray-900` → `text-ink` e `text-gray-500` → `text-ink-muted`:
```html
<!-- DA -->
<h3 class="font-semibold text-gray-900 mb-2">
<p class="text-sm text-gray-500 leading-relaxed">

<!-- A -->
<h3 class="font-semibold text-ink mb-2">
<p class="text-sm text-ink-muted leading-relaxed">
```

- [ ] **Step 6: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: update trust bar and problem section to atelier palette"
```

---

### Task 6: FEATURES + HOW IT WORKS

**Files:**
- Modify: `resources/views/landing.blade.php`

- [ ] **Step 1: Aggiornare FEATURES — background sezione**

```html
<!-- DA -->
<section id="funzionalita" class="py-24 px-6 bg-slate-50 scroll-mt-16">

<!-- A -->
<section id="funzionalita" class="py-24 px-6 bg-cream-dark scroll-mt-16">
```

- [ ] **Step 2: Aggiornare FEATURES — H2 display font + testi**

```html
<!-- DA -->
<h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">
    Tutto quello che serve per gestire il tuo salone
</h2>
<p class="text-gray-500 max-w-xl mx-auto" data-r style="--d:2">

<!-- A -->
<h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4" data-r style="--d:1">
    Tutto quello che serve per gestire il tuo salone
</h2>
<p class="text-ink-muted max-w-xl mx-auto" data-r style="--d:2">
```

- [ ] **Step 3: Aggiornare FEATURES — cards**

```html
<!-- DA -->
<div class="f-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"

<!-- A -->
<div class="f-card bg-cream rounded-2xl p-7 border border-warm-border shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"
```

```html
<!-- DA (icone features) -->
<div class="w-11 h-11 bg-teal-50 rounded-xl flex items-center justify-center mb-5">
    <svg class="w-5 h-5 text-teal-600" ...>

<!-- A -->
<div class="w-11 h-11 bg-terra-light rounded-xl flex items-center justify-center mb-5">
    <svg class="w-5 h-5 text-terra" ...>
```

```html
<!-- DA (titoli e testi features) -->
<h3 class="font-semibold text-gray-900 mb-2">
<p class="text-sm text-gray-500 leading-relaxed">

<!-- A -->
<h3 class="font-semibold text-ink mb-2">
<p class="text-sm text-ink-muted leading-relaxed">
```

- [ ] **Step 4: Aggiornare HOW IT WORKS — background**

```html
<!-- DA -->
<section id="come-funziona" class="py-24 px-6 bg-white scroll-mt-16">

<!-- A -->
<section id="come-funziona" class="py-24 px-6 bg-cream scroll-mt-16">
```

- [ ] **Step 5: Aggiornare HOW IT WORKS — H2 display font + testi**

```html
<!-- DA -->
<h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">
    Attivo in 5 minuti. Nessuna installazione.
</h2>
<p class="text-gray-500" data-r style="--d:2">

<!-- A -->
<h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4" data-r style="--d:1">
    Attivo in 5 minuti. Nessuna installazione.
</h2>
<p class="text-ink-muted" data-r style="--d:2">
```

- [ ] **Step 6: Aggiornare HOW IT WORKS — cerchi passi e connettore**

```html
<!-- DA (cerchio passo) -->
<div class="w-14 h-14 rounded-full bg-teal-600 text-white flex items-center justify-center text-lg font-bold mb-5 shadow-lg shadow-teal-100 relative z-10">

<!-- A -->
<div class="w-14 h-14 rounded-full bg-terra text-white flex items-center justify-center text-lg font-bold mb-5 shadow-lg shadow-terra/20 relative z-10">
```

```html
<!-- DA (connettore tratteggiato) -->
style="background:repeating-linear-gradient(to right,#0d9488 0,#0d9488 6px,transparent 6px,transparent 14px)"

<!-- A -->
style="background:repeating-linear-gradient(to right,#C4714A 0,#C4714A 6px,transparent 6px,transparent 14px)"
```

Aggiornare anche i testi dei passi:
```html
<!-- DA -->
<h3 class="text-lg font-semibold text-gray-900 mb-2">
<p class="text-sm text-gray-500 leading-relaxed max-w-xs">

<!-- A -->
<h3 class="text-lg font-semibold text-ink mb-2">
<p class="text-sm text-ink-muted leading-relaxed max-w-xs">
```

- [ ] **Step 7: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: update features and how-it-works sections to atelier palette"
```

---

### Task 7: PRICING + TESTIMONIALS + FAQ

**Files:**
- Modify: `resources/views/landing.blade.php`

- [ ] **Step 1: Aggiornare PRICING — background sezione**

```html
<!-- DA -->
<section id="prezzi" class="py-24 px-6 bg-slate-50 scroll-mt-16">

<!-- A -->
<section id="prezzi" class="py-24 px-6 bg-cream-dark scroll-mt-16">
```

- [ ] **Step 2: Aggiornare PRICING — H2 display font + testi**

```html
<!-- DA -->
<h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">Prezzo semplice e trasparente</h2>
<p class="text-gray-500 mb-2" data-r style="--d:2">
<p class="text-xs text-gray-400 mb-12" data-r style="--d:3">

<!-- A -->
<h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4" data-r style="--d:1">Prezzo semplice e trasparente</h2>
<p class="text-ink-muted mb-2" data-r style="--d:2">
<p class="text-xs text-ink-muted/60 mb-12" data-r style="--d:3">
```

- [ ] **Step 3: Aggiornare PRICING — card (rimuovere float-card)**

```html
<!-- DA -->
<div class="float-card bg-white rounded-2xl p-8 border border-gray-200 shadow-xl shadow-slate-200/70 flex flex-col">
    <div class="mb-6 text-left">
        <h3 class="font-bold text-gray-900 text-lg mb-1">Piano completo</h3>
        ...
        <div class="flex items-end gap-1 mt-2">
            <span class="text-5xl font-bold text-gray-900">€29</span>
            <span class="text-gray-400 text-sm mb-2">/mese</span>
        </div>
        <p class="text-xs text-gray-400 mt-1.5">Per saloni di ogni dimensione</p>
    </div>

<!-- A -->
<div class="bg-cream rounded-2xl p-8 border border-warm-border shadow-xl shadow-ink/8 flex flex-col">
    <div class="mb-6 text-left">
        <h3 class="font-bold text-ink text-lg mb-1">Piano completo</h3>
        ...
        <div class="flex items-end gap-1 mt-2">
            <span class="font-display text-6xl font-semibold text-ink">€29</span>
            <span class="text-ink-muted text-sm mb-2">/mese</span>
        </div>
        <p class="text-xs text-ink-muted mt-1.5">Per saloni di ogni dimensione</p>
    </div>
```

Nota: prezzo con `font-display` diventa un elemento editoriale forte.

- [ ] **Step 4: Aggiornare PRICING — lista features e CTA**

```html
<!-- DA -->
<li class="flex items-center gap-2.5 text-sm text-gray-600">

<!-- A -->
<li class="flex items-center gap-2.5 text-sm text-ink-muted">
```

```html
<!-- DA -->
class="shimmer block w-full text-center text-sm font-semibold bg-teal-600 text-white py-3.5 rounded-xl hover:bg-teal-700 transition"

<!-- A -->
class="shimmer block w-full text-center text-sm font-semibold bg-terra text-white py-3.5 rounded-xl hover:bg-terra/90 transition"
```

- [ ] **Step 5: Aggiornare TESTIMONIALS — background e cards**

```html
<!-- DA -->
<section class="py-24 px-6 bg-white">

<!-- A -->
<section class="py-24 px-6 bg-cream">
```

```html
<!-- DA -->
<h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">Cosa dicono i nostri clienti</h2>

<!-- A -->
<h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4" data-r style="--d:1">Cosa dicono i nostri clienti</h2>
```

```html
<!-- DA -->
<article class="bg-slate-50 rounded-2xl p-8 flex flex-col" data-r style="--d:{{ $i }}">

<!-- A -->
<article class="bg-cream-dark rounded-2xl p-8 flex flex-col" data-r style="--d:{{ $i }}">
```

- [ ] **Step 6: Aggiungere virgolette display alle testimonial**

Subito dopo l'apertura di ogni `<article>` (prima delle stelle), aggiungere:
```html
<div class="font-display text-6xl text-terra/30 leading-none mb-1 select-none">&ldquo;</div>
```

La struttura risultante per ogni testimonial:
```html
<article class="bg-cream-dark rounded-2xl p-8 flex flex-col" data-r style="--d:{{ $i }}">
    <div class="font-display text-6xl text-terra/30 leading-none mb-1 select-none">&ldquo;</div>
    <div class="flex gap-0.5 mb-5">
        ... stelle ...
    </div>
    <p class="text-sm text-ink-muted leading-relaxed mb-6 flex-1">"{{ $t['quote'] }}"</p>
```

Aggiornare anche il testo testimonial `text-gray-600` → `text-ink-muted`.

- [ ] **Step 7: Aggiornare FAQ**

```html
<!-- DA -->
<section class="py-24 px-6 bg-slate-50">

<!-- A -->
<section class="py-24 px-6 bg-cream-dark">
```

```html
<!-- DA -->
<h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">Domande frequenti</h2>

<!-- A -->
<h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4" data-r style="--d:1">Domande frequenti</h2>
```

```html
<!-- DA -->
<div class="bg-white rounded-xl border border-gray-200 overflow-hidden" data-r style="--d:{{ $idx }}">

<!-- A -->
<div class="bg-cream rounded-xl border border-warm-border overflow-hidden" data-r style="--d:{{ $idx }}">
```

```html
<!-- DA -->
class="w-full flex items-center justify-between px-6 py-5 text-left font-medium text-gray-900 hover:bg-slate-50 transition-colors text-sm"

<!-- A -->
class="w-full flex items-center justify-between px-6 py-5 text-left font-medium text-ink hover:bg-cream-dark transition-colors text-sm"
```

```html
<!-- DA -->
class="px-6 pb-5 text-sm text-gray-500 leading-relaxed border-t border-gray-100 pt-4"

<!-- A -->
class="px-6 pb-5 text-sm text-ink-muted leading-relaxed border-t border-warm-border pt-4"
```

- [ ] **Step 8: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: update pricing, testimonials, faq sections to atelier palette"
```

---

### Task 8: CTA FINALE + FOOTER + JS

**Files:**
- Modify: `resources/views/landing.blade.php`

- [ ] **Step 1: Aggiornare CTA FINALE — rimuovere decorativi, bg statico**

Sostituire l'intera apertura della sezione CTA:
```html
<!-- DA -->
<section class="cta-gradient relative overflow-hidden py-24 px-6 text-center">
    <div class="dot-grid absolute inset-0 pointer-events-none"></div>
    <div class="blob absolute -top-32 -right-32 w-96 h-96 rounded-full pointer-events-none"
         style="background:radial-gradient(circle,rgba(13,148,136,0.15) 0%,transparent 70%)"></div>

<!-- A -->
<section class="bg-ink relative overflow-hidden py-24 px-6 text-center">
```

- [ ] **Step 2: Aggiornare CTA FINALE — H2 display font**

```html
<!-- DA -->
<h2 class="text-3xl sm:text-4xl font-bold text-white mb-4" data-r style="--d:0">

<!-- A -->
<h2 class="font-display text-4xl sm:text-5xl font-semibold text-white mb-4" data-r style="--d:0">
```

- [ ] **Step 3: Aggiornare FOOTER**

```html
<!-- DA -->
<footer class="bg-slate-900 text-slate-400 py-16 px-6">

<!-- A -->
<footer class="bg-ink text-ink-muted py-16 px-6">
```

```html
<!-- DA (logo footer) -->
<div class="font-bold text-lg text-white tracking-tight mb-3">
    Gestionale<span class="text-teal-400 font-normal">Pro</span>
</div>

<!-- A -->
<div class="font-bold text-lg text-white tracking-tight mb-3">
    Gestionale<span class="text-terra font-normal">Pro</span>
</div>
```

```html
<!-- DA (intestazioni colonne footer) -->
<h4 class="text-xs font-semibold text-slate-300 uppercase tracking-widest mb-4">

<!-- A -->
<h4 class="text-xs font-semibold text-white/70 uppercase tracking-widest mb-4">
```

```html
<!-- DA (border footer) -->
<div class="border-t border-slate-800 pt-8 ...">

<!-- A -->
<div class="border-t border-[#2D2420] pt-8 ...">
```

- [ ] **Step 4: Aggiornare JS inline — barra scroll progress**

Nel blocco `<script>` in fondo, aggiornare il colore di `#spb` nel CSS già dichiarato. Poiché il colore è già dichiarato nel `<style>` (aggiornato al Task 2), verificare che non ci siano override JS. Se non ci sono, questo step è già completato dal Task 2.

- [ ] **Step 5: Commit**

```bash
git add resources/views/landing.blade.php
git commit -m "feat: update CTA, footer to atelier palette — complete landing redesign"
```

---

### Task 9: Build e verifica

**Files:**
- No file changes — solo verifica

- [ ] **Step 1: Avviare i servizi e il build**

```bash
docker-compose up -d
docker-compose run --rm --no-deps app npm run build
```

Output atteso: nessun errore Tailwind o Vite. Se ci sono warning su classi non trovate, verificare che i token `@theme` siano corretti in `app.css`.

- [ ] **Step 2: Verificare nel browser**

Aprire `http://localhost` e controllare:
- Hero: sfondo nero caldo, nessun gradient animato, H1 in Cormorant Garamond grande
- Badge hero: bordino terracotta, punto arancio
- Trust bar: numeri in terracotta su crema scura
- Sezioni: alternanza crema / crema-scura (non bianco / grigio-freddo)
- Feature cards: icone terracotta
- Passi: cerchi terracotta con connettore tratteggiato terracotta
- Testimonial: virgolette grandi in Cormorant
- FAQ: accordion su crema
- CTA finale: sfondo nero caldo statico
- Footer: logo con "Pro" in terracotta
- Nav scrollata: sfondo crema caldo, non bianco freddo
- Mobile: hamburger menu funzionante con colori aggiornati

Checklist anti-regressione:
- [ ] Nessun elemento teal rimasto visibile
- [ ] Nessun slate-50 / slate-900 rimasto visibile
- [ ] Font Cormorant caricato (H1 deve avere grazie evidenti, non sans-serif)
- [ ] Nessun blob che si muove
- [ ] Nessun gradient animato nell'hero
- [ ] La pricing card non fluttua più
- [ ] Barra scroll progress in terracotta

- [ ] **Step 3: Commit finale (se non già tutto committato)**

```bash
git status
# Se pulito, tutto è già committato nei task precedenti.
# Altrimenti:
git add -A
git commit -m "feat: complete landing atelier redesign"
```

---

## Self-Review

**Copertura spec:**
- ✅ Token @theme (Task 1)
- ✅ Rimozione gradPulse / cta-gradient (Task 2)
- ✅ Rimozione blobMove / blob (Task 4)
- ✅ Rimozione cardFloat (Task 7)
- ✅ Rimozione dot-grid (Task 4)
- ✅ Google Fonts Cormorant Garamond (Task 3)
- ✅ font-display su tutti gli H2 (Tasks 5-8)
- ✅ font-display su H1 hero (Task 4)
- ✅ bg-ink statico hero (Task 4)
- ✅ bg-ink statico CTA (Task 8)
- ✅ Palette terra/cream su tutte le sezioni (Tasks 5-8)
- ✅ Virgolette display testimonial (Task 7)
- ✅ Barra scroll terracotta (Task 2)
- ✅ Eyebrow terracotta (Task 2)
- ✅ text-grad terracotta (Task 2)
- ✅ Wave divider rimosso (Task 4)
- ✅ Build verifica (Task 9)

**Placeholder scan:** Nessun TBD o TODO.

**Consistenza tipi:** Nessun mismatch — tutte le classi Tailwind usate sono definite nei token @theme del Task 1.
