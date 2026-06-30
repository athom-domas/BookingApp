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
- image_path (string, nullable)
- sort_order (unsigned int, default 0)
- created_at, updated_at
```

Index: `[business_id, sort_order]`

### Modifica tabella `services`

Nuova colonna: `service_category_id` (nullable FK → service_categories, `nullOnDelete`).

Nullable perché i servizi esistenti non hanno categoria e un servizio senza categoria è valido.

## Modelli

### `ServiceCategory`

- Trait `BelongsToBusiness` — auto-scoping per `business_id`, stesso pattern di `Service`
- Trait `HasFactory`
- Fillable: `business_id`, `name`, `description`, `image_path`, `sort_order`
- Relazione `services()` → `HasMany(Service::class)`
- Metodo `imageUrl()` — stesso pattern di `Service` (public disk)

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
| `image_path` | FileUpload | Opzionale, WebP via `AbstractPageBlock::storeAsWebp()`, max 10MB |

### Tabella

| Colonna | Note |
|---------|------|
| `name` | Ricercabile, ordinabile |
| Conteggio servizi | `withCount('services')` |

- Riordinabile drag-and-drop su `sort_order`
- Action Delete: la FK `nullOnDelete` imposta automaticamente `service_category_id = null` sui servizi associati

## Filament: ServiceResource (modifiche)

Nella sezione "Informazioni" del form aggiungo un `Select` per `service_category_id`:

- Label: "Categoria"
- Nullable (non obbligatorio, placeholder "Nessuna categoria")
- Opzioni: `ServiceCategory::orderBy('sort_order')->pluck('name', 'id')` — già scopate al business corrente via `BelongsToBusiness`
- **Nascosto** (`->hidden(fn() => ServiceCategory::count() === 0)`) — non compare nei saloni senza categorie
- In tabella: colonna `category.name` opzionale, non visibile di default

## Pagina pubblica di prenotazione

### `BookingController::create()`

Aggiunge al payload della view:

```php
$categories = ServiceCategory::orderBy('sort_order')->get();
```

### Step 1 — Selezione servizio (Blade + Alpine)

Se `$categories` non è vuota:
- Mostro tab/sezioni sopra la griglia dei servizi (una per categoria + eventuale "Altri" per servizi senza categoria)
- Alpine gestisce `selectedCategory` nello state per filtrare i servizi mostrati
- Cliccando "Tutti" si torna alla vista completa

Se `$categories` è vuota: nessuna modifica visiva, comportamento identico all'attuale.

## Invarianti

- Un servizio appartiene a zero o una categoria.
- Le categorie sono per-business: un salone non vede le categorie di un altro.
- La cancellazione di una categoria non cancella i servizi: imposta `service_category_id = null`.
- I saloni senza categorie non vedono il campo categoria nel form dei servizi.

## File coinvolti

| File | Azione |
|------|--------|
| `database/migrations/YYYY_MM_DD_create_service_categories_table.php` | Nuova tabella |
| `database/migrations/YYYY_MM_DD_add_service_category_id_to_services.php` | FK nullable |
| `app/Models/ServiceCategory.php` | Nuovo modello |
| `app/Models/Service.php` | Fillable + relazione `category()` |
| `app/Filament/Resources/ServiceCategoryResource.php` | Nuova resource |
| `app/Filament/Resources/ServiceCategoryResource/Pages/ListServiceCategories.php` | List page |
| `app/Filament/Resources/ServiceCategoryResource/Pages/CreateServiceCategory.php` | Create page |
| `app/Filament/Resources/ServiceCategoryResource/Pages/EditServiceCategory.php` | Edit page |
| `app/Filament/Resources/ServiceResource.php` | Aggiunge Select categoria |
| `app/Http/Controllers/Portal/BookingController.php` | Carica categorie |
| `resources/views/portal/booking/index.blade.php` | Tab/filtro categorie in Step 1 |
| `database/factories/ServiceCategoryFactory.php` | Factory per test |

## Test

- Categoria può essere creata, modificata, eliminata per un business
- Un business non vede le categorie di un altro business
- Un servizio può essere associato a una categoria
- Cancellare una categoria imposta `service_category_id = null` sui servizi (non li cancella)
- Il campo categoria non appare nel form servizi se non esistono categorie
- Il campo categoria appare nel form servizi se esiste almeno una categoria
- La pagina pubblica non mostra tab categorie se non esistono categorie
- La pagina pubblica mostra tab categorie e filtra i servizi correttamente se esistono categorie
