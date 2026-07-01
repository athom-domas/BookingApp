# Shop Header — Design Spec

**Data:** 2026-07-01  
**Scope:** Aggiunta di un header configurabile (variante + immagine + testo) alla pagina pubblica prodotti (`/prodotti`).

---

## Obiettivo

La pagina shop attualmente mostra solo un titolo "Prodotti" statico. L'obiettivo è aggiungere un header visivamente ricco, configurabile per-salone, che riusi interamente il sistema hero già esistente nel site builder.

---

## Architettura

### 1. Dati — `salon_profiles` (migrazione)

Aggiungere 6 colonne nullable a `salon_profiles`:

| Colonna | Tipo | Default | Note |
|---|---|---|---|
| `shop_header_variant` | `string` | `'classic'` | `classic` \| `editorial` \| `centered` |
| `shop_header_title` | `string(120)` | `null` | Se null, usa "Prodotti" |
| `shop_header_subtitle` | `string(200)` | `null` | Se null, usa testo default |
| `shop_header_image` | `string` | `null` | Path su disco `public` |
| `shop_header_image_mobile` | `string` | `null` | Path su disco `public`, opzionale |
| `shop_header_image_preset` | `string` | `null` | Chiave preset (es. `'barber'`) |

Tutti nullable, nessun default DB — i default sono gestiti a livello applicativo.

### 2. Modello — `SalonProfile`

- Aggiungere i 6 campi a `#[Fillable]`.
- Nessun cast aggiuntivo (tutti stringhe nullable).

### 3. Admin — `SalonProfilePage` (Filament)

Aggiungere un nuovo tab **"Shop"** nella pagina Profilo Salone, dopo i tab esistenti.

Campi del tab:

1. **Variante layout** — `Radio` o `Select` con le stesse 3 opzioni di `HeroBlock::variants()`:
   - `classic` — Sfondo immagine piena con testo centrato
   - `editorial` — Immagine laterale con testo a sinistra
   - `centered` — Sfondo tinta unita con testo centrato

2. **Titolo** — `TextInput`, maxLength 120, placeholder "Prodotti"

3. **Sottotitolo** — `Textarea`, maxLength 200, rows 2, placeholder testo default

4. **Immagine desktop** — `FileUpload`, `disk('public')`, conversione webp via `AbstractPageBlock::storeAsWebp($file, 'site-builder/shop-header')`

5. **Immagine mobile (opzionale)** — `FileUpload`, stesso pattern, helperText "Sostituisce l'immagine desktop su schermi ≤ 640px"

6. **Oppure scegli immagine predefinita** — `Radio` con view `filament.forms.hero-preset-picker`, stesse opzioni di `SalonProfile::heroPresets()`

Nota: nessun campo CTA (lo shop ha già il pulsante "Vai al checkout" nativo).

Il salvataggio avviene tramite il metodo `save()` esistente di `SalonProfilePage`, che aggiorna `SalonProfile::current()`.

### 4. Frontend — `shop/index.blade.php`

Prima della sezione `.sf-section` esistente, includere il sotto-template hero corrispondente alla variante:

```blade
@php
    $profile = \App\Models\SalonProfile::current();
    $variant = $profile->shop_header_variant ?? 'classic';
    $_content = [
        'title'        => $profile->shop_header_title ?? 'Prodotti',
        'subtitle'     => $profile->shop_header_subtitle ?? 'Acquista i prodotti del salone con ritiro in sede.',
        'image'        => $profile->shop_header_image,
        'image_mobile' => $profile->shop_header_image_mobile,
        'cta_label'    => '',
    ];
    $_settings = ['show_cta' => false];
    $preset = $profile->shop_header_image_preset;
    $_heroPresetUrl = $preset ? (\App\Models\SalonProfile::heroPresets()[$preset]['url'] ?? null) : null;
@endphp
@include("page-blocks.hero.{$variant}", [
    'content'        => $_content,
    'settings'       => $_settings,
    'hero_preset_url'=> $_heroPresetUrl,
    'business'       => $business,
    'block'          => null,
])
```

Le view hero esistenti gestiscono già:
- Fallback senza immagine (classe `.sf-hero--no-img`)
- `<picture>` con `<source>` per mobile
- Preload link ottimizzato
- Variante `centered` senza immagine (tinta unita)

### 5. Controller — `ProductController@index`

Passare `$business` alla view (se non già presente). Il profilo viene caricato direttamente nella view tramite `SalonProfile::current()` per evitare accoppiamento.

Verificare se `$business` è già disponibile nella view tramite il middleware tenant; se sì, nessuna modifica al controller.

---

## Comportamento con header non configurato

Se il salone non ha impostato nulla (tutti i campi null):

- Variante: `classic`
- Titolo: "Prodotti"
- Sottotitolo: "Acquista i prodotti del salone con ritiro in sede."
- Nessuna immagine → la view hero mostra la classe `.sf-hero--no-img` (sfondo `--sf-bg-alt`, testo centrato)

L'header è quindi sempre visibile — non c'è un toggle on/off. Il degradato graceful è sufficiente.

---

## File coinvolti

| File | Tipo modifica |
|---|---|
| `database/migrations/2026_07_01_*_add_shop_header_to_salon_profiles.php` | nuovo |
| `app/Models/SalonProfile.php` | aggiunta campi `#[Fillable]` |
| `app/Filament/Pages/SalonProfilePage.php` | nuovo tab "Shop" |
| `resources/views/shop/index.blade.php` | include header |
| `app/Http/Controllers/Portal/ProductController.php` | verifica/passa `$business` se mancante |

Nessun nuovo file CSS. Nessuna nuova view blade (riuso `page-blocks/hero/*.blade.php`).

---

## Fuori scope

- Toggle per abilitare/disabilitare l'header
- CTA personalizzata nello shop header
- Modifica alle view hero esistenti
- Gestione dello shop header nel pannello SuperAdmin
