# Page Nav — Sticky Section Menu

## Goal

Add a horizontal sticky nav that appears immediately after the hero block on the storefront, listing the enabled sections with anchor links. Becomes sticky below `#sf-nav` when the hero scrolls out of view. Visible on mobile (horizontally scrollable).

## Scope

- No database changes
- No admin config — automatic from block type
- Only affects the new page-builder storefront (`welcome.blade.php`), not the legacy view

---

## 1. Anchor ID standardization

All block templates must output `id="{{ $block->block_type }}"` on their root `<section>`. Current IDs are inconsistent and must be updated:

| Template group | Current ID | New ID |
|---|---|---|
| `services/*` | `id="servizi"` | `id="services"` |
| `staff/*` | `id="team"` | `id="staff"` |
| `gallery/*` | `id="galleria"` | `id="gallery"` |
| `reviews/*` | `id="recensioni"` | `id="reviews"` |
| `about/*` | `id="about-{{ $block->id }}"` | `id="about"` |
| `faq/*` | `id="faq-{{ $block->id }}"` | `id="faq"` |
| `cta/*` | `id="cta-{{ $block->id }}"` | `id="cta"` |
| `contact_info/*` | `id="contact-{{ $block->id }}"` | `id="contact_info"` |
| `map/*` | `id="map-{{ $block->id }}"` | `id="map"` |

Hero templates have no anchor ID and do not need one (hero is never in the nav).

---

## 2. `navLabel()` on block classes

Add to `AbstractPageBlock`:

```php
public static function navLabel(): ?string
{
    return null; // default: excluded from nav
}
```

Each concrete class overrides it:

| Class | `navLabel()` |
|---|---|
| `HeroBlock` | `null` |
| `ServicesBlock` | `'Servizi'` |
| `AboutBlock` | `'Il salone'` |
| `StaffBlock` | `'Il team'` |
| `GalleryBlock` | `'Galleria'` |
| `ReviewsBlock` | `'Recensioni'` |
| `ContactInfoBlock` | `'Contatti'` |
| `FaqBlock` | `'FAQ'` |
| `CtaBlock` | `null` |
| `MapBlock` | `null` |

---

## 3. Nav rendering — `welcome.blade.php`

Compute nav links from `$blocks` (already available in the view). Insert the nav element immediately after the hero block in the loop. If fewer than 2 nav-eligible links exist, render nothing.

```blade
@php
    $navLinks = $blocks
        ->filter(fn($b) => $b->is_enabled)
        ->map(fn($b) => [
            'href'  => '#' . $b->block_type,
            'label' => \App\PageBlocks\PageBlockRegistry::find($b->block_type)::navLabel(),
            'type'  => $b->block_type,
        ])
        ->filter(fn($l) => $l['label'] !== null)
        ->values();
@endphp

@foreach($blocks as $block)
    <x-page-block :business="$business" :block="$block" />
    @if($block->block_type === 'hero' && $navLinks->count() >= 2)
        <nav class="sf-page-nav" aria-label="Sezioni">
            @foreach($navLinks as $link)
                <a href="{{ $link['href'] }}" class="sf-page-nav-link" data-section="{{ $link['type'] }}">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    @endif
@endforeach
```

---

## 4. CSS — `styles.blade.php`

```css
.sf-page-nav {
    position: sticky;
    top: var(--sf-nav-h, 65px);
    z-index: 99;
    display: flex;
    gap: 0;
    overflow-x: auto;
    white-space: nowrap;
    scrollbar-width: none;
    background: var(--sf-bg);
    border-bottom: 1px solid var(--sf-border);
    padding: 0 48px;
}
.sf-page-nav::-webkit-scrollbar { display: none; }

.sf-page-nav-link {
    display: inline-block;
    padding: 12px 0;
    margin-right: 28px;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--sf-body);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
    flex-shrink: 0;
}
.sf-page-nav-link:hover,
.sf-page-nav-link.is-active {
    color: var(--sf-gold);
    border-bottom-color: var(--sf-gold);
}

@media (max-width: 640px) {
    .sf-page-nav { padding: 0 20px; }
}
```

---

## 5. JS — `welcome.blade.php` `@push('scripts')`

```js
(function () {
    // Set --sf-nav-h so page nav sticks just below main nav
    var sfNav = document.getElementById('sf-nav');
    if (sfNav) {
        document.documentElement.style.setProperty('--sf-nav-h', sfNav.offsetHeight + 'px');
    }

    // Active link highlighting via IntersectionObserver
    var links = document.querySelectorAll('.sf-page-nav-link');
    if (!links.length) return;

    var sectionMap = {};
    links.forEach(function (link) {
        var section = document.getElementById(link.dataset.section);
        if (section) sectionMap[link.dataset.section] = { link: link, section: section };
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var key = entry.target.id;
            if (sectionMap[key]) {
                sectionMap[key].link.classList.toggle('is-active', entry.isIntersecting);
            }
        });
    }, { rootMargin: '-30% 0px -60% 0px' });

    Object.values(sectionMap).forEach(function (item) {
        observer.observe(item.section);
    });
})();
```

The `rootMargin: '-30% 0px -60% 0px'` fires active when a section is in the middle band of the viewport.

---

## Files changed

| File | Change |
|---|---|
| `app/PageBlocks/AbstractPageBlock.php` | Add `navLabel(): ?string` returning `null` |
| `app/PageBlocks/HeroBlock.php` … `FaqBlock.php` | Override `navLabel()` per table above |
| `resources/views/page-blocks/*/**.blade.php` | Standardize `id` to `block_type` (all variants) |
| `resources/views/welcome.blade.php` | Compute `$navLinks`, render nav after hero, add JS |
| `resources/views/page-blocks/styles.blade.php` | Add `.sf-page-nav` CSS |
