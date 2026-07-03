# Page Builder — Design Document

**Data:** 2026-06-26
**Stato:** approvato, pronto per implementation plan

---

## Sommario

Sostituire il template fisso `welcome.blade.php` con un **section builder guidato**: blocchi predefiniti in codice, riordinabili e personalizzabili per salone, con template base gestiti dal superadmin.

Il salone non può rompere il design: niente CSS libero, niente font personalizzati, solo varianti controllate.

---

## Architettura

### Tre livelli

```
Superadmin
└── crea page_templates con blocchi default (page_template_blocks)

Admin salone
└── sceglie template → snapshot in business_page_blocks
└── personalizza: ordine, visibilità, contenuti, variante

Frontend pubblico
└── legge business_page_blocks, renderizza tramite <x-page-block>
```

### Blocchi definiti in codice

I tipi di blocco sono classi PHP in `app/PageBlocks/`. Il DB salva solo la configurazione; il comportamento resta controllato dal codice. Aggiungere un nuovo tipo di blocco richiede una nuova classe PHP + view Blade — nessuna UI nel superadmin per creare tipi custom.

---

## Data Model

### `page_templates`

```
id
name
slug                    unique
description
is_active               boolean default true
is_default              boolean default false
created_at
updated_at
```

**Vincolo:** un solo record può avere `is_default = true`. Gestito a livello applicativo: quando si imposta un template come default, gli altri vengono resettati.

### `page_template_blocks`

```
id
page_template_id        FK → page_templates
block_type              string (es. 'hero', 'services')
variant                 string (es. 'classic', 'grid_cards')
sort_order              integer
is_enabled              boolean default true
is_required             boolean default false
is_locked               boolean default false
content                 json        -- default '{}'
settings                json        -- default '{}'
schema_version          integer default 1
created_at
updated_at

INDEX (page_template_id, sort_order)
```

### `business_page_blocks`

```
id
business_id             FK → businesses
page_template_id        nullable FK → page_templates
page_template_block_id  nullable FK → page_template_blocks
block_type              string
variant                 string
sort_order              integer
is_enabled              boolean default true
is_required             boolean default false
is_locked               boolean default false
content                 json        -- default '{}'
settings                json        -- default '{}'
schema_version          integer default 1
created_at
updated_at

INDEX (business_id, is_enabled, sort_order)
INDEX (business_id, block_type)
```

**Nota:** `page_template_id` e `page_template_block_id` sono riferimento storico, non usati per il rendering. Il frontend legge solo `business_page_blocks`. Servono per "ripristina blocco al default" (post-MVP).

**Nota:** `is_required` e `is_locked` vengono copiati dallo snapshot del template. Anche se il template base cambia, il comportamento del singolo salone resta stabile.

### `salon_profiles` — modifica

Aggiungere:
```
page_template_id        nullable FK → page_templates
```

Serve come riferimento storico (quale template ha scelto il salone) e per un eventuale reset completo. Non viene usato per renderizzare la pagina pubblica.

### Model casts

Entrambi i model (`PageTemplateBlock`, `BusinessPageBlock`) devono castare:

```php
protected function casts(): array
{
    return [
        'content'     => 'array',
        'settings'    => 'array',
        'is_enabled'  => 'boolean',
        'is_required' => 'boolean',
        'is_locked'   => 'boolean',
    ];
}
```

`content` e `settings` sono **sempre array** — mai null. Il default DB è `'{}'`; il model restituisce `[]` se vuoto.

---

## Comportamento is_required e is_locked

| Flag | Comportamento per il tenant |
|------|-----------------------------|
| `is_required = true` | non può disabilitare il blocco, non può eliminarlo |
| `is_locked = true` | può vedere il blocco nell'elenco, ma non può modificare contenuti, settings o variant |
| entrambi false | piena libertà su ordine, visibilità, contenuto, variant |

**MVP:** `is_required` è attivo e applicato. `is_locked` è in schema e copiato nello snapshot, ma la UI tenant lo implementa come "nessun pulsante Modifica" — semplice blocco in sola lettura. Se non si presenta il caso d'uso nell'MVP, può restare inapplicato senza impatto.

---

## Block System PHP

### Contratto

```php
// app/PageBlocks/Contracts/PageBlockContract.php

interface PageBlockContract
{
    public static function type(): string;
    public static function label(): string;
    public static function description(): string;
    public static function icon(): string;

    /** @return array<string, array{label: string, description: string}> */
    public static function variants(): array;
    public static function defaultVariant(): string;

    public static function defaultContent(): array;
    public static function defaultSettings(): array;

    public static function contentRules(): array;
    public static function settingsRules(): array;

    /** Filament form fields — usano state path content.* e settings.* (vedi convenzione) */
    public static function filamentFields(): array;

    /** View path per la variant specificata */
    public static function viewFor(string $variant): string;

    /**
     * Dati extra iniettati nella view (per blocchi dinamici).
     * I blocchi statici restituiscono [].
     */
    public static function resolveData(Business $business, BusinessPageBlock $block): array;
}
```

### Convenzione campi Filament

I campi di `filamentFields()` usano state path annidati che mappano direttamente su `content` e `settings`:

```php
TextInput::make('content.title'),
Textarea::make('content.subtitle'),
Toggle::make('settings.show_prices'),
Select::make('settings.alignment')->options(['left' => 'Sinistra', 'center' => 'Centrato']),
```

Al salvataggio il controller separa:
```php
$content  = $data['content']  ?? [];
$settings = $data['settings'] ?? [];
```

Questa convenzione rende `filamentFields()` autodocumentante: si vede subito se un campo è contenuto o impostazione.

### Registry

```php
// app/PageBlocks/PageBlockRegistry.php

class PageBlockRegistry
{
    public static function all(): array;           // type => class
    public static function find(string $type): ?string;
    public static function isValidVariant(string $type, string $variant): bool;
    public static function defaultVariant(string $type): string;
}
```

Classe con metodi statici — non un service container singleton. Se in futuro serve iniettabilità/testabilità si usa `app(PageBlockRegistry::class)`, ma per MVP i metodi statici sono sufficienti.

### Blocchi dinamici vs statici

| Tipo | Dati da | `resolveData()` |
|------|---------|----------------|
| Statico | Interamente da `content` (JSON del blocco) | restituisce `[]` |
| Dinamico | DB a runtime (servizi, staff, recensioni, media) | restituisce array dati |
| Misto | Config da `content`, dati da `SalonProfile` o DB | restituisce dati parziali |

Per MVP ogni blocco dinamico esegue le proprie query in `resolveData()` — una query per blocco, non N+1 per elemento. Ottimizzazione aggregata cross-block è post-MVP.

### Blocchi MVP (10)

| Tipo | Label | Categoria | Varianti MVP |
|------|-------|-----------|-------------|
| `hero` | Hero / Header | statico | `classic`, `editorial`, `centered` |
| `about` | Descrizione Salone | statico | `centered`, `split_image` |
| `services` | Servizi | dinamico | `grid_cards`, `compact_list`, `price_list` |
| `staff` | Team | dinamico | `cards`, `simple_list`, `editorial` |
| `gallery` | Galleria | dinamico | `grid_3col`, `masonry`, `slider` |
| `reviews` | Recensioni | dinamico | `cards`, `carousel`, `minimal` |
| `contact_info` | Orari & Contatti | misto | `simple`, `with_map` |
| `map` | Mappa | misto | `full_width`, `contained` |
| `cta` | CTA Prenotazione | statico | `simple`, `with_image` |
| `faq` | FAQ | statico | `accordion`, `list` |

**Priorità implementazione varianti:**
- **Varianti complete** (tutte funzionanti nell'MVP): `hero`, `services`, `staff`, `gallery`, `reviews`
- **Prima variante stabile + stub** per le altre: `about`, `contact_info`, `map`, `cta`, `faq`

I blocchi con stub dichiarano le varianti in `variants()` ma puntano alla stessa view della prima variante finché non sono implementate. Questo non impatta il contratto o il DB.

### Media e immagini

I blocchi con immagini (`hero`, `about` con `split_image`, `cta` con `with_image`) usano `FileUpload` Filament che salva su disk configurato (`public` o `s3`). Nel JSON viene salvato il **path relativo**:

```php
FileUpload::make('content.image')
    ->image()
    ->directory('site-builder/hero'),
```

Cleanup delle immagini orfane (es. dopo cambio template) è post-MVP. I blocchi dinamici con media (`gallery`, `staff`) continuano a usare Spatie MediaLibrary come oggi — non cambiano.

### View path

```
resources/views/page-blocks/{type}/{variant}.blade.php

Esempi:
resources/views/page-blocks/hero/classic.blade.php
resources/views/page-blocks/services/grid_cards.blade.php
resources/views/page-blocks/gallery/masonry.blade.php
```

---

## Rendering Frontend

### Componente Blade

`<x-page-block :business="$business" :block="$block" />`

Il componente (`app/View/Components/PageBlock.php`) centralizza:
1. Lookup del tipo nel Registry — se non trovato: log warning + skip silenzioso in production, errore visibile in local/staging
2. Validazione variant → fallback a `defaultVariant()` se non valida (log warning con `block_id`, `old_variant`, `fallback_variant`; **non modifica il DB**)
3. Chiamata `resolveData()` per blocchi dinamici
4. Rendering view

### Controller

```php
$hasAnyBlocks = BusinessPageBlock::where('business_id', $business->id)->exists();

if (! $hasAnyBlocks) {
    return view('welcome-legacy', $data); // fallback
}

$blocks = BusinessPageBlock::where('business_id', $business->id)
    ->where('is_enabled', true)
    ->orderBy('sort_order')
    ->get();
```

**Il fallback legacy scatta solo se il business non ha _alcun_ blocco**, non se tutti i blocchi sono disabilitati. Un salone che disabilita tutto mostra una pagina vuota — non il vecchio template.

### `welcome.blade.php` migrato

```blade
@foreach($blocks as $block)
    <x-page-block :business="$business" :block="$block" />
@endforeach
```

### Fallback legacy

Il vecchio `welcome.blade.php` viene rinominato `welcome-legacy.blade.php` e usato solo come fallback temporaneo. Rimosso dopo che tutti i business sono stati inizializzati con `page-builder:init`.

### SEO e meta

Il page builder renderizza solo il corpo della pagina. Meta title, meta description, Open Graph e favicon restano gestiti da `SalonProfile`. Il builder non gestisce SEO nell'MVP.

### Salvataggio live

Non esiste modalità bozza nell'MVP. Ogni modifica salvata dal salone è **immediatamente visibile** sul sito pubblico. La UI deve comunicarlo esplicitamente ("Le modifiche saranno visibili subito sul sito").

---

## Admin UI

### Superadmin — `PageTemplateResource`

**Path:** `App\Filament\SuperAdmin\Resources\PageTemplateResource`

**Lista template:** nome, slug, attivo, default, numero blocchi, azioni (modifica, clona, attiva/disattiva).

**Modifica template:**
- Campi: name, slug, description, is_active, is_default
- `PageTemplateBlocksRelationManager`: tabella blocchi con drag-and-drop per riordino
- Aggiungi blocco: slide-over con selezione tipo (card picker dal Registry, mostra `icon()` + `label()` + `description()`) + variant + content/settings default
- Modifica blocco: slide-over con campi del blocco
- Rimuovi blocco: azione distruttiva con confirm; blocchi `is_required` non rimovibili
- **Clona template**: copia il template con name `"{name} copia"`, slug rigenerato, `is_default = false`, `is_active = false`; copia tutti i `page_template_blocks` preservando sort_order, content, settings

**Template iniziali creati dal seeder:** Default, Minimal, Premium/Luxury.

---

### Tenant Admin — `SiteBuilderPage`

**Path:** `App\Filament\Admin\Pages\SiteBuilderPage`

**Menu:** "Il mio sito"

**Vista:** lista ordinata di `business_page_blocks` del business corrente. Ogni riga:
- Handle drag per riordino
- Icona + label del tipo blocco
- Badge variant attiva
- Toggle enabled/disabled (disabilitato se `is_required = true`)
- Pulsante "Modifica" (assente se `is_locked = true`)

**Modifica blocco** (slide-over):
- Selezione variant: radio/select tra `variants()` disponibili, con label descrittiva
- Campi da `filamentFields()` per content e settings
- Nessun campo per CSS, font, margini, padding, colori avanzati
- Validazione server-side via `contentRules()` + `settingsRules()`

**Azioni di pagina:**
- "Cambia template" → modal con picker template attivi + warning esplicito: "Cambiare template reimposterà l'ordine, i testi, le immagini e le varianti dei blocchi. Questa azione non è reversibile." + checkbox di conferma
- "Apri sito pubblico" → link al sito in nuova tab (non "Anteprima" — il salvataggio è live)

**Riordino:** drag-and-drop via Livewire action, aggiorna `sort_order` in DB.

**MVP:** il salone non può aggiungere nuovi blocchi, modifica solo quelli ereditati dal template scelto.
**Post-MVP:** il salone potrà aggiungere blocchi dal Registry, nei limiti definiti dal template/piano.

---

## Authorization

| Ruolo | Può fare |
|-------|---------|
| `super_admin` | CRUD su `page_templates` e `page_template_blocks` |
| `admin` (tenant) | Lettura/scrittura su `business_page_blocks` del **proprio** business |
| Frontend pubblico | Lettura blocchi del business corrente (scope by `business_id`) |

Nessun tenant può accedere a blocchi di un altro salone. Il scope tenant usa il business corrente dal middleware/tenant context dell'applicazione — non `auth()->user()->business_id` hardcodato.

---

## Migrazione Saloni Esistenti

### Seeder

Crea il template "Default" con i 10 blocchi nell'ordine attuale di `welcome.blade.php`, con `is_required = true` per `hero` e `contact_info`.

### Command

```bash
php artisan page-builder:init
php artisan page-builder:init --business=123
php artisan page-builder:init --force   # resetta anche business già inizializzati — richiede conferma esplicita in console
```

**Logica:**
1. Per ogni business senza `business_page_blocks`: copia blocchi del template Default nello snapshot, imposta `salon_profiles.page_template_id`
2. Idempotente: skip se già inizializzato (a meno di `--force`)
3. Tutta l'operazione è **transazionale** (DB::transaction)

### Flusso "Cambia template" (UI tenant)

Eseguito in `DB::transaction`:

1. Salva `salon_profiles.page_template_id` con il nuovo template
2. Elimina `business_page_blocks` esistenti (hard delete)
3. Per ogni `page_template_block` del nuovo template, crea una riga in `business_page_blocks` copiando: `block_type`, `variant`, `sort_order`, `is_enabled`, `is_required`, `is_locked`, `content`, `settings`, `schema_version`, più `page_template_id` e `page_template_block_id`
4. Il sito è immediatamente aggiornato con il nuovo template

---

## Implementation Safety Notes

- `content` e `settings` sono sempre castati ad `array`; le view assumono array, mai null.
- Le view usano escaping Blade standard `{{ }}` per tutti i contenuti testuali. `{!! !!}` è vietato nell'MVP salvo contenuti pre-sanitizzati con allowlist.
- Le immagini dei blocchi sono salvate tramite `FileUpload` Filament su disk configurato; nel JSON viene salvato il path relativo.
- Reset template e `page-builder:init --force` sono atomici tramite `DB::transaction`.
- Il fallback legacy scatta solo se il business non ha alcun `business_page_block` — non se tutti i blocchi sono disabilitati.
- La clonazione di un template copia anche tutti i `page_template_blocks`; il clone parte con `is_default = false`, `is_active = false` e slug rigenerato.
- Il renderer non modifica mai il DB — il fallback su `defaultVariant()` è solo per il rendering, non persiste.
- Il scope tenant usa il business corrente dal tenant context, non `auth()->user()->business_id`.

---

## Test MVP

Scenari da coprire nei test Feature/Unit:

- Crea template con blocchi → verifica struttura
- Snapshot template → `business_page_blocks` (tutti i campi copiati, inclusi `is_required`/`is_locked`/`schema_version`)
- Tenant modifica solo i propri blocchi (non quelli di altri business)
- "Cambia template" resetta i blocchi del business e crea nuovo snapshot in transazione
- Renderer salta blocco con tipo non registrato (production: log + skip)
- Variant non valida → usa `defaultVariant()` senza modificare il DB
- Business senza blocchi → fallback legacy `welcome-legacy.blade.php`
- Business con tutti i blocchi disabilitati → pagina vuota, **non** fallback legacy
- Blocco `is_required = true` non può essere disabilitato dal tenant
- `page-builder:init` è idempotente
- Clone template copia i blocchi e genera slug univoco

---

## Post-MVP (out of scope)

- Draft / publish workflow
- Preview separata dal live
- Cache Redis per rendering pagina (bust on save)
- Tenant può aggiungere blocchi dal Registry (con permessi superadmin per template/piano)
- `php artisan page-builder:repair` per riparare variant obsolete in batch
- Preloading aggregato cross-block (ottimizzazione query)
- Schema migration tooling per `content`/`settings` JSON (usare `schema_version`)
- Cleanup immagini orfane dopo cambio template
- Backup configurazione prima del reset template (versioning)
- Template marketplace / template aggiuntivi
- Blocchi aggiuntivi: `ProductsBlock`, `PromotionsBlock`, `LoyaltyBlock`, `BeforeAfterBlock`, `BrandsBlock`, `InstagramBlock`
- A/B testing varianti
- Versioning configurazione salone (ripristina versione precedente)
