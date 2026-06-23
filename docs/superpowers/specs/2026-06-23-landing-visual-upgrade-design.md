# Landing Page Visual Upgrade — Design Spec

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migliorare la credibilità visiva della landing page (`landing.blade.php`) sostituendo il mockup HTML con screenshot reali, aggiungendo una sezione video demo e migliorando il layout dei testimonial.

**Architecture:** Modifiche al solo file `resources/views/landing.blade.php` + aggiunta di asset statici (`public/img/screenshots/`, `public/video/`). Nessuna modifica al backend, nessuna nuova route.

**Tech Stack:** Laravel Blade, Alpine.js (già presente), Tailwind CSS (già configurato), `<video>` nativo HTML5

---

## 1. Screenshot nel Product Preview

### Cosa cambia

La sezione esistente "Il pannello che semplifica ogni giornata" contiene un mockup amministrativo codificato in HTML/PHP. Va sostituito con screenshot reali dell'app dentro lo stesso wrapper "browser chrome" già presente.

### Struttura

Il chrome (pallini rosso/giallo/verde + barra URL) rimane invariato. Il contenuto interno diventa:

1. **Tab switcher** (Alpine.js, `x-data="{ tab: 'lista' }"`):
   - Tab "Lista appuntamenti" → mostra `screenshot-lista.png`
   - Tab "Calendario" → mostra `screenshot-calendario.png`
2. Un `<img>` condizionale per ogni tab, `w-full object-cover object-top`, altezza fissa `360px`

### Screenshot da acquisire

| File | Schermata | Note |
|---|---|---|
| `public/img/screenshots/screenshot-lista.png` | `/admin/appointments` — lista appuntamenti di oggi | Larghezza minima 1200px, sfondo `#faf7f2` |
| `public/img/screenshots/screenshot-calendario.png` | `/admin/calendar` — vista calendario con almeno 3-4 appuntamenti | Stessa larghezza |

### Screenshot aggiuntivo nella sezione Funzionalità

Alla voce "Prenotazioni Online 24/7" (prima feature card), aggiungere sotto il testo descrittivo un piccolo screenshot della pagina di prenotazione pubblica (lato cliente):

| File | Schermata |
|---|---|
| `public/img/screenshots/screenshot-booking.png` | Pagina pubblica `/` di un salone — step selezione servizio |

Layout: `<img>` con `rounded-xl border border-warm-border shadow-sm mt-4 w-full max-w-xs` — compatto, non ingombrante.

---

## 2. Sezione Video Demo

### Posizionamento

Nuova sezione inserita **tra** la sezione "Come funziona" (`#come-funziona`) e la sezione "Product Preview" (quella con `bg-ink`).

### Struttura HTML

```
<section class="py-24 px-6 bg-cream-dark">
  <div class="max-w-4xl mx-auto text-center">
    <p class="text-xs font-semibold text-terra uppercase tracking-widest mb-3">Demo</p>
    <h2>Guardalo in azione</h2>
    <p class="text-ink-muted mb-10">Da zero a prima prenotazione: meno di 2 minuti.</p>

    <div class="rounded-2xl overflow-hidden shadow-2xl border border-warm-border">
      <video controls poster="/img/screenshots/screenshot-lista.png"
             class="w-full block" preload="none">
        <source src="/video/demo.mp4" type="video/mp4">
      </video>
    </div>
  </div>
</section>
```

- **Nessun iframe** (YouTube/Vimeo) — video nativo, zero cookie di terze parti
- **Poster:** riutilizza `screenshot-lista.png` come immagine statica prima del play
- **File:** `public/video/demo.mp4`
- `preload="none"` per non rallentare il caricamento della pagina

### Script del video da registrare

Sequenza consigliata (60–90 secondi totali):

1. **Lato cliente** (30 sec): apri la pagina pubblica di prenotazione → seleziona servizio → seleziona operatore → scegli data e ora → conferma prenotazione
2. **Lato admin** (30 sec): passa al pannello admin → mostra la nuova prenotazione nella lista → apri il calendario drag-and-drop → sposta un appuntamento

**Come registrare su Mac:** QuickTime Player → File → Nuova registrazione schermo → seleziona la finestra del browser → registra → salva come `.mp4`.

Non serve editing. Qualità retina del Mac è più che sufficiente.

---

## 3. Miglioramento layout Testimonial

I testimonial attuali hanno iniziali colorate come avatar (nessuna foto reale). Il layout rimane, ma il design delle card viene reso più credibile:

- Rimuovere il simbolo `"` gigante in stile display font
- Le stelle diventano più grandi (`w-5 h-5` invece di `w-4 h-4`) e posizionate sopra la citazione
- Aggiungere un "badge ruolo" sotto nome e ruolo: chip `bg-terra-light text-terra text-xs rounded-full px-2 py-0.5` con tipo attività (es. "Parrucchiera", "Barbiere", "Estetista")
- Quote in corsivo per più impatto visivo (`italic`)

I dati (nomi, ruoli, testi) rimangono gli stessi — solo la presentazione migliora.

---

## File modificati / creati

| Azione | Path |
|---|---|
| Modifica | `resources/views/landing.blade.php` |
| Aggiungi (manuale — screenshot) | `public/img/screenshots/screenshot-lista.png` |
| Aggiungi (manuale — screenshot) | `public/img/screenshots/screenshot-calendario.png` |
| Aggiungi (manuale — screenshot) | `public/img/screenshots/screenshot-booking.png` |
| Aggiungi (manuale — video) | `public/video/demo.mp4` |

Le directory `public/img/screenshots/` e `public/video/` vanno create. I file PNG/MP4 sono asset manuali — l'implementazione code crea le directory e inserisce i path nel Blade, ma i file li fornisce l'utente.

---

## Out of scope

- Nessun redesign della struttura delle sezioni
- Nessuna modifica al pricing, hero, FAQ, CTA finale
- Nessun video autoplaying in background (distrae dalla CTA)
- Nessuna integrazione con CDN o ottimizzazione immagini
