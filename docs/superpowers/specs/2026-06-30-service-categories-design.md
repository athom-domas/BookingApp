# Service Categories Design

**Data:** 2026-06-30

## Contesto

Il sistema di prenotazione gestisce servizi per-business (massaggi, unghie, ecc.). I saloni con molti servizi hanno bisogno di raggrupparli in categorie per facilitare la navigazione sia nel pannello admin che nella pagina pubblica di prenotazione. Le categorie sono opzionali: i saloni che non le usano non vedono nessun campo aggiuntivo.

## Obiettivo

1. Permettere a ogni salone di creare le proprie categorie di servizi.
2. Collegare ogni servizio a una categoria (opzionale, una sola).
3. Mostrare le categorie come filtro/raggruppamento nella pagina pubblica di prenotazione.

## Architettura

**Approccio scelto:** Resource Filament dedicata per `ServiceCategory`, identica struttura di `ServiceResource`. Il campo categoria su `ServiceResource` è nascosto se il tenant non ha ancora categorie.

## Database

### Nuova tabella `service_categories`

```
- id
- business_id (FK → businesses, cascade delete)
- name (string)
- description (text, nullable)
- image_path (string, nullable) — post-MVP
- sort_order (unsigned int, default 0)
- is_active (boolean, default true)
- created_at, updated_at
```

Vincoli e indici:
- `UNIQUE (business_id, name)`
- `INDEX (business_id, is_active, sort_order)`

### Modifica tabella `services`

Nuova colonna: `service_category_id` (nullable FK → service_categories, `nullOnDelete`).

Nuovi indici:
- `INDEX (business_id, service_category_id)`
- `INDEX (business_id, service_category_id, sort_order)`

Nullable perché i servizi esistenti non hanno categoria e un servizio senza categoria è valido.

## Modelli

### `ServiceCategory`

- Trait `BelongsToBusiness` — auto-scoping per `business_id`, stesso pattern di `Service`
- Trait `HasFactory`
- Fillable: `business_id`, `name`, `description`, `image_path`, `sort_order`, `is_active`
- Cast: `is_active` → boolean
- Relazione `services()` → `HasMany(Service::class)`
- Scope `active()` → filtra `is_active = true`
- Metodo `imageUrl()` — stesso pattern di `Service` (post-MVP)

### `Service` (modifiche)

- Aggiunge `service_category_id` al fillable
- Aggiunge relazione `category()` → `BelongsTo(ServiceCategory::class)`

## Filament: ServiceCategoryResource

- Navigation group: "Salone"
- Icon: `heroicon-o-tag`
- Sort order: 4 (dopo `ServiceResource` a 3)
- Accesso: stesso guard di `ServiceResource` (solo admin)

### Form

| Campo | Tipo | Note |
|-------|------|------|
| `name` | TextInput | Obbligatorio, unico per business |
| `description` | Textarea | Opzionale, 3 righe |
| `is_active` | Toggle | Default true |

`image_path` (FileUpload WebP) — post-MVP.

### Tabella

| Colonna | Note |
|---------|------|
| `name` | Ricercabile, ordinabile |
| Conteggio servizi | `withCount('services')` |
| `is_active` | ToggleColumn |

- Riordinabile drag-and-drop su `sort_order`
- Action Delete: la FK `nullOnDelete` imposta automaticamente `service_category_id = null` sui servizi associati — i servizi restano visibili come "senza categoria"

## Filament: ServiceResource (modifiche)

Nella sezione "Informazioni" del form aggiungo un `Select` per `service_category_id`:

```php
Select::make('service_category_id')
    ->label('Categoria')
    ->relationship('category', 'name') // già scoped al business corrente via BelongsToBusiness
    ->options(fn () => ServiceCategory::orderBy('sort_order')->pluck('name', 'id'))
    ->searchable()
    ->preload()
    ->nullable()
    ->placeholder('Nessuna categoria')
    ->rule(Rule::exists('service_categories', 'id')->where('business_id', app('current_business_id')))
    ->hidden(fn () => ServiceCategory::count() === 0)
```

La `Rule::exists` con `business_id` protegge il boundary tenant lato backend: anche se un attore malevolo inviasse un `service_category_id` di un altro business, la validazione lo rigetta.

In tabella: colonna `category.name` opzionale, non visibile di default.

## Pagina pubblica di prenotazione

### `BookingController::create()`

```php
$categories = ServiceCategory::active()
    ->whereHas('services', fn ($q) => $q->where('active', true))
    ->orderBy('sort_order')
    ->get();
```

Solo categorie attive con almeno un servizio attivo — evita di mostrare tab vuote.

### Step 1 — Selezione servizio (Blade + Alpine)

Se `$categories` non è vuota:
- Tab sopra la griglia: "Tutti" + una tab per categoria + "Altri" (solo se esistono servizi con `service_category_id = null`)
- Alpine gestisce `selectedCategory` nello state per filtrare i servizi mostrati
- "Tutti" mostra tutti i servizi

Se `$categories` è vuota: nessuna modifica visiva, comportamento identico all'attuale.

## Invarianti

- Un servizio appartiene a zero o una categoria.
- Le categorie sono per-business: un salone non vede le categorie di un altro.
- Un servizio non può essere associato a una categoria di un altro business (validato lato backend con `Rule::exists`).
- La cancellazione di una categoria imposta `service_category_id = null` sui servizi — non li cancella.
- I saloni senza categorie non vedono il campo categoria nel form dei servizi.
- Nel pubblico compaiono solo categorie attive con almeno un servizio attivo.
- "Altri" compare nel pubblico solo se esistono servizi senza categoria.

## Scope MVP / Post-MVP

**MVP:**
- Tabella `service_categories` (senza `image_path`)
- Relazione opzionale `services.service_category_id`
- `ServiceCategoryResource` con `is_active`, `sort_order`, validazione tenant-safe
- Select categoria su `ServiceResource`
- Filtro/tab categorie nella pagina pubblica
- Gruppo "Altri"

**Post-MVP:**
- `image_path` su categoria (upload WebP, storage, preview)
- Slug pubblico + SEO per categoria
- Descrizione categoria mostrata nel booking

## File coinvolti

| File | Azione |
|------|--------|
| `database/migrations/YYYY_MM_DD_create_service_categories_table.php` | Nuova tabella |
| `database/migrations/YYYY_MM_DD_add_service_category_id_to_services.php` | FK nullable + indici |
| `app/Models/ServiceCategory.php` | Nuovo modello |
| `app/Models/Service.php` | Fillable + relazione `category()` |
| `app/Filament/Resources/ServiceCategoryResource.php` | Nuova resource |
| `app/Filament/Resources/ServiceCategoryResource/Pages/ListServiceCategories.php` | List page |
| `app/Filament/Resources/ServiceCategoryResource/Pages/CreateServiceCategory.php` | Create page |
| `app/Filament/Resources/ServiceCategoryResource/Pages/EditServiceCategory.php` | Edit page |
| `app/Filament/Resources/ServiceResource.php` | Aggiunge Select categoria con validazione |
| `app/Http/Controllers/Portal/BookingController.php` | Carica categorie |
| `resources/views/portal/booking/index.blade.php` | Tab/filtro categorie in Step 1 |
| `database/factories/ServiceCategoryFactory.php` | Factory per test |

## Test

- Categoria può essere creata, modificata, eliminata per un business
- Un business non vede le categorie di un altro business
- Un servizio può essere associato a una categoria dello stesso business
- Un servizio non può essere associato a una categoria di un altro business
- Cancellare una categoria imposta `service_category_id = null` sui servizi — i servizi restano visibili
- Il campo categoria non appare nel form servizi se non esistono categorie
- Il campo categoria appare nel form servizi se esiste almeno una categoria
- Il riordino aggiorna `sort_order` correttamente
- La pagina pubblica non mostra tab categorie se non esistono categorie attive con servizi attivi
- La pagina pubblica mostra tab categorie e filtra i servizi correttamente
- Il gruppo "Altri" compare solo se esistono servizi senza categoria
- Disattivare una categoria (`is_active = false`) la nasconde nel pubblico senza scollegar i servizi
