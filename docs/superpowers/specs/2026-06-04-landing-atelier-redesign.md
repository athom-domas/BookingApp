# Landing Page — Atelier Redesign

**Data:** 2026-06-04  
**Obiettivo:** Eliminare l'estetica SaaS generica (teal + slate + card grid) e sostituirla con un'identità editoriale calda da boutique italiana.

---

## Problema

La landing attuale ha tutti i segnali della "SaaS template": gradient animato teal/navy nell'hero, tre blob decorativi, grid uniforme di card con icona+titolo+testo, sezioni alternate bianco/grigio, cardFloat sull'unica pricing card. È indistinguibile da migliaia di altri prodotti.

---

## Sistema visivo

### Palette

| Ruolo | Token Tailwind custom | Hex |
|---|---|---|
| Background pagina | `bg-cream` | `#FAF7F2` |
| Background sezioni alternate | `bg-cream-dark` | `#F2EBE3` |
| Testo primario | `text-ink` | `#1C1410` |
| Testo secondario | `text-ink-muted` | `#7A6A60` |
| Accento primario | `text-terra` / `bg-terra` | `#C4714A` |
| Accento chiaro (bg) | `bg-terra-light` | `#F2DDD3` |
| Bordi | `border-warm` | `#E5D8CF` |
| Superfici dark (hero, CTA) | `bg-ink` | `#1C1410` |

Tailwind custom theme in `app.css`:
```css
@theme {
  --color-cream: #FAF7F2;
  --color-cream-dark: #F2EBE3;
  --color-ink: #1C1410;
  --color-ink-muted: #7A6A60;
  --color-terra: #C4714A;
  --color-terra-light: #F2DDD3;
  --color-warm-border: #E5D8CF;
}
```

### Tipografia

- **Display (H1, H2, citazioni):** Cormorant Garamond — importato da Google Fonts, weight 400/600/700
- **Body / UI / bottoni / label:** Instrument Sans — già presente

Import da aggiungere all'`<head>`:
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
```

Classe utility:
```css
.font-display { font-family: 'Cormorant Garamond', Georgia, serif; }
```

H1 hero: `font-display text-6xl sm:text-7xl lg:text-8xl font-semibold`  
H2 sezioni: `font-display text-4xl sm:text-5xl font-semibold`

---

## Sezioni — cambiamenti specifici

### `<style>` inline — animazioni rimosse

Rimuovere completamente:
- `.hero-gradient` + `@keyframes gradPulse` — il background diventa statico `bg-ink`
- `.cta-gradient` — il background diventa statico `bg-ink`
- `.blob` + `.blob-2` + `.blob-3` + `@keyframes blobMove` — rimuovere i tre div blob
- `.float-card` + `@keyframes cardFloat` — rimossa l'animazione sulla pricing card
- `.dot-grid` — rimossa (troppo decorativa)

Mantenere:
- `fadeUp`, `fadeDown`, `.h-badge`, `.h-title`, ecc. — restano per l'entrata hero
- `[data-r]` scroll reveal — resta
- `.shimmer` — resta ma aggiornato al terracotta
- `.f-card` spotlight — resta
- `#spb` barra scroll — resta ma gradient cambia in `#C4714A → #1C1410`
- `.eyebrow` — resta ma cambia colore a `var(--color-terra, #C4714A)`
- `.text-grad` — diventa gradiente caldo: `linear-gradient(120deg, #C4714A, #8B4513)` (terracotta → marrone scuro)

### `<body>`
```
bg-white → bg-cream  (classe Tailwind custom)
text-gray-900 → text-ink
```

### NAV

Stesso markup. Aggiornamenti colore:
- Logo tint: `text-teal-400` → `text-terra` / `text-teal-600` → `text-terra`
- CTA button: `bg-teal-600 hover:bg-teal-700` → `bg-terra hover:bg-terra/90`
- Nav scrolled: `bg-white/95` → `bg-cream/95`
- Border scrolled: `border-gray-100` → `border-warm-border`

### HERO

Background: `hero-gradient` → `bg-ink` (statico, nessuna animazione)

Rimuovere: div dot-grid, i tre div blob, il wave divider SVG (sostituito da nessun divider — la sezione successiva ha bg-cream, il contrasto netto fa da separatore).

Titolo — aggiungere `font-display`:
```html
<h1 class="h-title font-display text-6xl sm:text-7xl font-semibold text-white leading-[1.05] mb-6 tracking-tight">
```
Rimuovere `<br class="hidden sm:block">` — il display font grande va su due righe naturalmente.

Badge: `bg-teal-900/60 border-teal-700/40` → `bg-terra/15 border-terra/25`  
Dot animate: `bg-teal-400` → `bg-terra`  
Testo badge: `text-teal-300` → `text-terra-light/90` o `text-[#F2DDD3]`

CTA primario: `bg-teal-500 hover:bg-teal-400 shadow-teal-900/30` → `bg-terra hover:bg-terra/85 shadow-ink/30`  
CTA secondario: `text-slate-300 hover:text-white` — invariato  
Fine print: `text-slate-400` — invariato

### TRUST BAR

`bg-slate-50 border-b border-gray-100` → `bg-cream-dark border-b border-warm-border`  
Numeri: `text-teal-600` → `text-terra`

### PROBLEM

`bg-white` → `bg-cream`  
Card: `bg-slate-50 border-slate-100` → `bg-cream-dark border-warm-border`  
Icone errore: restano `bg-red-50 text-red-400` — il rosso-problema contrasta bene col terracotta, e funziona semanticamente

### FEATURES

`bg-slate-50` → `bg-cream-dark`  
Card: `bg-white border-gray-100` → `bg-cream border-warm-border`  
Icone: `bg-teal-50 text-teal-600` → `bg-terra-light text-terra`

### HOW IT WORKS

`bg-white` → `bg-cream`  
Cerchi passi: `bg-teal-600 shadow-teal-100` → `bg-terra shadow-terra/20`  
Connettore tratteggiato: `#0d9488` → `#C4714A`

### PRICING

`bg-slate-50` → `bg-cream-dark`  
Card: `bg-white border-gray-200 shadow-slate-200/70` → `bg-cream border-warm-border shadow-ink/8`  
Rimossa classe `float-card` dal div  
CTA: `bg-teal-600 hover:bg-teal-700` → `bg-terra hover:bg-terra/90`

### TESTIMONIALS

`bg-white` → `bg-cream`  
Card: `bg-slate-50` → `bg-cream-dark`  
Avatar iniziali: mantengono i colori attuali (teal/indigo/rose) — vanno bene come note cromatiche su sfondo caldo

Aggiungere virgolette display sopra ogni testimonianza:
```html
<div class="font-display text-6xl text-terra/30 leading-none mb-2 select-none">&ldquo;</div>
```

### FAQ

`bg-slate-50` → `bg-cream-dark`  
Accordion item: `bg-white border-gray-200` → `bg-cream border-warm-border`  
Hover: `hover:bg-slate-50` → `hover:bg-cream-dark`

### CTA FINALE

Rimuovere: `.cta-gradient`, div dot-grid, div blob  
Sostituire con: `bg-ink` statico  
Invariato il testo e i bottoni (già su sfondo scuro)

### FOOTER

`bg-slate-900` → `bg-ink`  
`text-teal-400` nel logo → `text-terra`  
Border: `border-slate-800` → `border-[#2D2420]`

### JS inline

Barra scroll progress (`#spb`): aggiornare gradient:
```js
// da: #0d9488, #818cf8
// a:  #C4714A, #1C1410
background: 'linear-gradient(to right, #C4714A, #1C1410)'
```

---

## Tailwind — configurazione

Tailwind v4 usa `@theme` in CSS. Aggiungere dentro il blocco `@theme` esistente in `resources/css/app.css` (o aggiungere un secondo blocco `@theme` — in v4 si fondono):
```css
@theme {
  /* ... font-sans esistente ... */
  --color-cream: #FAF7F2;
  --color-cream-dark: #F2EBE3;
  --color-ink: #1C1410;
  --color-ink-muted: #7A6A60;
  --color-terra: #C4714A;
  --color-terra-light: #F2DDD3;
  --color-warm-border: #E5D8CF;
  --font-display: 'Cormorant Garamond', Georgia, serif;
}
```

Con Tailwind v4 i token `--color-*` diventano automaticamente classi `bg-*`, `text-*`, `border-*`.  
Il token `--font-display` diventa la classe `font-display`.

---

## File da modificare

1. `resources/css/app.css` — aggiungere token `@theme`
2. `resources/views/landing.blade.php` — tutte le modifiche descritte sopra

---

## Criteri di successo

- Nessun riferimento a `teal-*` o `slate-*` rimasto nella landing
- Nessuna animazione `gradPulse`, `blobMove`, `cardFloat`, `dot-grid` attiva
- Cormorant Garamond visibile sull'H1 e sugli H2 delle sezioni
- La pagina carica senza flash di teal
- Mobile: nav, hero e sezioni funzionano correttamente su viewport < 640px
