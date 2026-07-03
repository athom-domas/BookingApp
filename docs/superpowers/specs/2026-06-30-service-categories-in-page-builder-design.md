# Categorie servizi nel sito vetrina (page builder)

**Data:** 2026-06-30

## Contesto

Il page builder espone un blocco "Servizi" in tre varianti di layout: `grid_cards`, `compact_list`, `price_list`. I servizi sono visualizzati in un'unica lista piatta. Con l'introduzione delle categorie (spec `2026-06-30-service-categories-design.md`), ogni salone può raggruppare i servizi per categoria. Questo spec descrive come mostrare quei gruppi nel sito vetrina.

## Obiettivo

Aggiungere intestazioni di sezione per categoria in tutti e tre i layout, rispettando lo stile visivo di ciascuno. La logica `featured_only` si applica per gruppo di categoria. Se non esistono categorie il comportamento è identico all'attuale.

## Architettura

**Approccio:** Pre-raggruppamento in `ServicesBlock::resolveData()`. Il metodo calcola i gruppi in PHP e restituisce `$grouped_services` — array di entry `{category, services}`. Le view iterano i gruppi invece di iterare `$services` direttamente. La chiave `$services` (flat collection) rimane nel payload per retrocompatibilità.

## Data layer — `ServicesBlock::resolveData()`

```php
public static function resolveData(Business $business, BusinessPageBlock $block): array
{
    $services = Service::withoutGlobalScope('business')
        ->where('business_id', $business->id)
        ->where('active', true)
        ->with('category')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();

    $categories = \App\Models\ServiceCategory::withoutGlobalScope('business')
        ->where('business_id', $business->id)
        ->where('is_active', true)
        ->whereHas('services', fn ($q) => $q
            ->where('business_id', $business->id)
            ->where('active', true))
        ->orderBy('sort_order')
        ->get();

    if ($categories->isEmpty()) {
        $grouped = [['category' => null, 'services' => $services]];
    } else {
        $grouped = [];
        foreach ($categories as $cat) {
            $catServices = $services->where('service_category_id', $cat->id)->values();
            if ($catServices->isNotEmpty()) {
                $grouped[] = ['category' => $cat, 'services' => $catServices];
            }
        }
        $uncategorized = $services->whereNull('service_category_id')->values();
        if ($uncategorized->isNotEmpty()) {
            $grouped[] = ['category' => null, 'services' => $uncategorized];
        }
    }

    return ['services' => $services, 'grouped_services' => $grouped];
}
```

## Struttura `$grouped_services`

```
[
  ['category' => ServiceCategory,  'services' => Collection],  // gruppo categorizzato
  ['category' => null,             'services' => Collection],  // "Altri" — solo se coesiste con gruppi categorizzati
]
```

- Se non ci sono categorie: un solo entry con `category = null`, nessuna intestazione mostrata.
- "Altri" (servizi senza categoria) appare solo se ci sono anche gruppi categorizzati (`count($grouped_services) > 1`).
- L'heading "Altri" non compare se è l'unico gruppo.

## Featured_only per gruppo

Per ogni gruppo:
```php
$featuredInGroup = $featuredOnly ? $group['services']->where('featured', true) : collect();
$otherInGroup    = $featuredOnly ? $group['services']->where('featured', false) : collect();
```

Il toggle "Mostra tutti i servizi (N)" appare per gruppo, non globalmente. Ogni gruppo ha il proprio `x-data="{ open: false }"`.

## Layout grid_cards

Intestazione categoria: `<h3 class="sf-svc-category-heading">{{ $group['category']->name }}</h3>` con classe CSS da aggiungere al foglio di stile del sito vetrina:

```css
.sf-svc-category-heading {
    font-size: 1rem;
    font-weight: 600;
    color: var(--sf-text, #1e293b);
    margin: 32px 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--sf-border, #e2e8f0);
}
.sf-svc-category-heading:first-child { margin-top: 0; }
```

Struttura per gruppo:

```blade
@foreach($grouped_services as $group)
    @if($group['category'] && count($grouped_services) > 1)
        <h3 class="sf-svc-category-heading">{{ $group['category']->name }}</h3>
    @elseif(!$group['category'] && count($grouped_services) > 1)
        <h3 class="sf-svc-category-heading">Altri</h3>
    @endif
    @php
        $featuredInGroup = $featuredOnly ? $group['services']->where('featured', true) : collect();
        $otherInGroup    = $featuredOnly ? $group['services']->where('featured', false) : collect();
    @endphp
    <div class="sf-svc-grid">
        @foreach($featuredOnly ? $featuredInGroup : $group['services'] as $service)
            {{-- card esistente --}}
        @endforeach
    </div>
    @if($featuredOnly && $otherInGroup->isNotEmpty())
        <div x-data="{ open: false }">
            {{-- toggle esistente, scoped al gruppo --}}
        </div>
    @endif
@endforeach
```

## Layout compact_list

Stesso schema di `grid_cards`. L'intestazione ha la stessa classe `sf-svc-category-heading`. Ogni gruppo ha il proprio `<ul class="sf-svc-list">` e il proprio Alpine toggle per featured_only.

## Layout price_list

Intestazione categoria in stile menù (senza classe CSS esterna, inline per rispettare il sistema di variabili CSS del layout):

```blade
@if($group['category'] && count($grouped_services) > 1)
<p style="font-family:var(--sf-font-display);font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--sf-muted);padding:24px 0 4px;margin:0">
    {{ $group['category']->name }}
</p>
@elseif(!$group['category'] && count($grouped_services) > 1)
<p style="font-family:var(--sf-font-display);font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--sf-muted);padding:24px 0 4px;margin:0">
    Altri
</p>
@endif
```

Ogni gruppo ha il proprio `<ul style="list-style:none">` e il proprio Alpine toggle.

Il CTA button (`Prenota ora`) rimane una sola volta, fuori dal loop, in fondo alla sezione — invariato.

## Invarianti

- Se non ci sono categorie: comportamento e markup identici all'attuale.
- Il guard `@if($services->isNotEmpty())` resta immutato come condizione sulla sezione.
- `$services` (flat) rimane nel payload per retrocompatibilità con qualsiasi codice che la usa direttamente.
- "Altri" non appare se è l'unico gruppo.
- La logica `featured_only` si applica per gruppo, non globalmente.

## File coinvolti

| File | Azione |
|------|--------|
| `app/PageBlocks/ServicesBlock.php` | Modifica `resolveData()` |
| `resources/views/page-blocks/services/grid_cards.blade.php` | Refactor loop su `$grouped_services` + heading categoria |
| `resources/views/page-blocks/services/compact_list.blade.php` | Stessa struttura |
| `resources/views/page-blocks/services/price_list.blade.php` | Stessa struttura, heading stile menù |
| CSS del sito vetrina (da individuare — cerca `.sf-svc-grid` per localizzare il foglio di stile) | Aggiunge `.sf-svc-category-heading` |

## Test

- Nessuna categoria → nessuna intestazione, markup identico all'attuale
- Categorie attive con servizi → una intestazione per categoria, servizi raggruppati correttamente
- Servizi senza categoria + categorie presenti → gruppo "Altri" in fondo
- Servizi senza categoria + nessuna categoria → nessuna intestazione
- featured_only attivo → toggle per gruppo, non globale
- Categoria disattivata → non appare nel sito vetrina
- Categoria senza servizi attivi → non appare nel sito vetrina
