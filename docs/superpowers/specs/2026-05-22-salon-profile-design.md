# Salon Profile — Design Spec

**Data:** 2026-05-22  
**Scope:** Profilo salone ricco (admin panel + vetrina pubblica)  
**Approccio:** A (profilo ricco, layout fisso, admin riempie i dati)  
**Stack:** Laravel 13, PHP 8.4, Filament 4, Tailwind v4, Alpine.js

---

## Obiettivo

Rendere il profilo salone completamente configurabile dall'admin (Filament) e trasformare la landing page pubblica in una vetrina professionale che riflette quei dati in tempo reale.

---

## Pacchetti aggiuntivi

| Pacchetto | Versione | Scopo |
|---|---|---|
| `spatie/laravel-medialibrary` | ^11 | Gestione media con conversioni automatiche (WebP, thumbnail) |
| `filament/spatie-laravel-media-library-plugin` | latest | Plugin ufficiale Filament 4 per upload/gallery drag-and-drop |

Il rich text usa `RichEditor` nativo Filament 4 (già incluso, nessuna dipendenza aggiuntiva).

---

## Data Model

### `SalonProfile` — nuovi campi (migration)

| Campo | Tipo | Note |
|---|---|---|
| `tagline` | `string\|null` | Sottotitolo hero |
| `description` | `longText\|null` | "Chi siamo" (HTML da RichEditor) |
| `cancellation_policy` | `longText\|null` | Policy cancellazione (HTML da RichEditor) |
| `google_maps_embed` | `text\|null` | URL src per iframe Google Maps |
| `opening_hours` | `json\|null` | `{"mon":{"open":"09:00","close":"18:00","closed":false}, ...}` — 7 chiavi: mon/tue/wed/thu/fri/sat/sun |
| `instagram_url` | `string\|null` | |
| `facebook_url` | `string\|null` | |
| `tiktok_url` | `string\|null` | |
| `whatsapp_number` | `string\|null` | Solo numero internazionale, link `https://wa.me/` generato lato view |

**Media via medialibrary** (collections su `SalonProfile`):

| Collection | Conversioni | Note |
|---|---|---|
| `logo` | `thumb` (200×200) | Sostituisce `logo_path`. Il campo `logo_path` resta in DB; la view usa `getFirstMediaUrl('logo')` con fallback su `logo_path` per retrocompatibilità |
| `cover` | `web` (1920×600, crop) | Immagine hero full-width |
| `gallery` | `thumb` (400×400), `web` (1200×800) | Galleria pubblica, ordinabile |

### `SalonReview` — nuovo modello

| Campo | Tipo | Note |
|---|---|---|
| `id` | `bigIncrements` | |
| `author_name` | `string` | |
| `body` | `text` | |
| `rating` | `tinyInteger` | 1–5 |
| `is_published` | `boolean` | default `false` |
| `sort_order` | `integer` | default `0` |
| `created_at/updated_at` | timestamps | |

### `users` — nuovi campi

| Campo | Tipo | Note |
|---|---|---|
| `bio` | `text\|null` | Solo per staff, mostrata in vetrina |

**Media via medialibrary** (collection `avatar` su `User`):
- Conversioni: `thumb` (200×200 crop)
- Usata solo per staff (ruolo `staff`)

---

## Admin Panel (Filament)

### `SalonProfilePage` — form a tab

**Tab 1 — Identità**
- `TextInput` nome salone (required)
- `TextInput` tagline
- `ColorPicker` colore primario (required)
- `SpatieMediaLibraryFileUpload` logo (collection `logo`, image, max 2MB)
- `SpatieMediaLibraryFileUpload` cover image (collection `cover`, image, max 5MB)

**Tab 2 — Descrizione**
- `RichEditor` "Chi siamo" (`description`)
- `RichEditor` "Politica di cancellazione" (`cancellation_policy`)

**Tab 3 — Galleria**
- `SpatieMediaLibraryFileUpload` galleria (collection `gallery`, multiple, reorderable, max 10MB per file)

**Tab 4 — Orari**
- `Repeater`-like custom o `KeyValue` per i 7 giorni, ogni giorno: toggle `closed` + `TimePicker` apertura + `TimePicker` chiusura
- Implementato come `Group` con 7 `Grid` rows (lun–dom), ognuno con `Toggle` + `TextInput` (ora HH:MM)

**Tab 5 — Contatti & Social**
- `TextInput` telefono
- `TextInput` indirizzo (`columnSpanFull`)
- `Textarea` Google Maps embed URL
- `TextInput` Instagram URL
- `TextInput` Facebook URL
- `TextInput` TikTok URL
- `TextInput` WhatsApp number

**Tab 6 — Anteprima**
- `Placeholder` con link `<a href="/" target="_blank">Apri vetrina →</a>`

### `SalonReviewResource` — nuovo Resource

**Tabella:** author_name, rating (stelle), estratto body, is_published (toggle inline), sort_order (drag reorder con `reorderable()`).

**Form:** TextInput author_name (required), Textarea body (required), Select rating 1–5 (required), Toggle is_published.

### `StaffResource` — estensione

Aggiunge al form esistente:
- `Textarea` bio (nullable)
- `SpatieMediaLibraryFileUpload` foto profilo (collection `avatar`, image, max 2MB)

---

## Vetrina Pubblica

### Iniezione `primary_color`

In `layouts/app.blade.php`, nel `<head>`:

```html
@php $profile = \App\Models\SalonProfile::current(); @endphp
<style>
  :root { --color-primary: {{ $profile->primary_color ?? '#1d4ed8' }}; }
</style>
```

CTA e accenti usano `style="background-color: var(--color-primary)"` o classi arbitrarie Tailwind `[background-color:var(--color-primary)]`.

### Controller `WelcomeController`

```php
public function index(): View {
    return view('welcome', [
        'profile'  => SalonProfile::current()->load('media'),
        'services' => Service::active()->orderBy('sort_order')->get(),
        'staff'    => User::staff()->with('media')
                         ->where(fn($q) => $q
                             ->whereNotNull('bio')
                             ->orWhereHas('media', fn($q) => $q->where('collection_name', 'avatar'))
                         )->get(),
        'reviews'  => SalonReview::published()->ordered()->get(),
        // published() = where is_published true; ordered() = orderBy sort_order
    ]);
}
```

### Sezioni `welcome.blade.php`

Ogni sezione è condizionale — nascosta se i dati rilevanti sono vuoti.

| # | Sezione | Visibilità |
|---|---|---|
| 1 | **Hero** | Sempre visibile. Fallback: sfondo `primary_color` se no cover |
| 2 | **Servizi** | `@if($services->isNotEmpty())` |
| 3 | **Team** | `@if($staff->isNotEmpty())` |
| 4 | **Galleria** | `@if($profile->getMedia('gallery')->isNotEmpty())` |
| 5 | **Chi siamo** | `@if($profile->description)` |
| 6 | **Orari** | `@if($profile->opening_hours)` |
| 7 | **Contatti + mappa** | Sempre visibile se phone o address compilati; mappa `@if($profile->google_maps_embed)` |
| 8 | **Recensioni** | `@if($reviews->isNotEmpty())` |
| 9 | **Policy cancellazione** | `@if($profile->cancellation_policy)` |
| 10 | **Footer** | Sempre visibile |

**Galleria** — grid CSS responsive, lightbox Alpine.js puro (nessun pacchetto JS).  
**Recensioni** — card con stelle SVG + nome + testo.  
**Orari** — tabella, giorno corrente evidenziato con `primary_color`.  
**Policy** — accordion Alpine.js (`x-show` / `x-collapse`).  
**Footer** — social icons SVG inline, link solo se URL compilato.

---

## Scoping escluso

- Multilingua
- SEO meta tags avanzati (og:image, structured data) — fuori scope
- Integrazione Google Reviews automatica
- Page builder o drag-and-drop sezioni
- Sistema di temi predefiniti

---

## Ordine di implementazione suggerito

1. Installare e configurare medialibrary
2. Migration: nuovi campi `salon_profiles`, nuovo `salon_reviews`, `bio` su `users`
3. Aggiornare `SalonProfile` model (HasMedia, nuovi fillable, media conversions)
4. Aggiornare `User` model (HasMedia per avatar)
5. Creare `SalonReview` model
6. Riscrivere `SalonProfilePage` (tab form con medialibrary)
7. Creare `SalonReviewResource`
8. Estendere `StaffResource` (bio + avatar)
9. Riscrivere `WelcomeController` + `welcome.blade.php` (10 sezioni)
10. Aggiornare `layouts/app.blade.php` (CSS custom property)
