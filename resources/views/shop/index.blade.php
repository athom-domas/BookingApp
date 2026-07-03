@extends('layouts.storefront')

@section('title', 'Prodotti')

@push('head')
<style>
/* ── SHOP CART FAB ── */
.sf-cart-fab {
    position: fixed; z-index: 95; text-decoration: none;
    bottom: 28px; right: 28px;
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--sf-btn-bg); color: var(--sf-btn-fg);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.30);
    transition: transform 0.15s, box-shadow 0.15s;
}
.sf-cart-fab:hover { transform: scale(1.06); box-shadow: 0 6px 28px rgba(0,0,0,0.36); }
.sf-cart-fab-icon { position: relative; display: flex; align-items: center; justify-content: center; margin-right: 5px; }
.sf-cart-fab-count {
    position: absolute; top: -10px; right: -10px;
    min-width: 20px; height: 20px; padding: 0 5px;
    background: var(--sf-btn-fg); color: var(--sf-btn-bg);
    outline: 2px solid var(--sf-btn-bg);
    border-radius: 100px; font-size: 10px; font-weight: 700;
    line-height: 20px; text-align: center;
    font-family: var(--sf-font-body); letter-spacing: 0;
}
.sf-cart-fab-label { display: none; }
.sf-cart-fab {
    transition: transform 0.2s, box-shadow 0.15s, opacity 0.2s;
}
.sf-cart-hidden {
    opacity: 0 !important;
    pointer-events: none !important;
    transform: scale(0.7) !important;
}
@media (prefers-reduced-motion: reduce) {
    .sf-cart-fab { transition: none; }
}

/* ── PRODUCT STEPPER ── */
.sf-stepper { display: flex; align-items: center; gap: 8px; justify-content: space-between; }
.sf-stepper-dec {
    display: none;
    background: none; border: 1px solid var(--sf-border);
    border-radius: min(var(--sf-radius), 20px);
    padding: 8px 15px; cursor: pointer;
    font-family: var(--sf-font-body);
    font-size: 10px; letter-spacing: 2px; text-transform: uppercase;
    color: var(--sf-body); white-space: nowrap;
    transition: border-color 0.15s, color 0.15s;
}
.sf-stepper-dec:hover { border-color: var(--sf-gold); color: var(--sf-ink); }
.sf-stepper--active .sf-stepper-dec { display: block; }
.sf-stepper-count {
    display: none; min-width: 28px; text-align: center;
    font-weight: 700; font-size: 1rem; color: var(--sf-ink);
}
.sf-stepper--active .sf-stepper-count { display: block; }
.sf-stepper-inc { flex: 1; text-align: center; }
.sf-stepper--active .sf-stepper-inc { flex: none; }
</style>
@endpush

@section('content')
@php
    $_shopVariant = in_array($shopProfile->shop_header_variant, ['classic', 'editorial', 'centered'])
        ? $shopProfile->shop_header_variant
        : 'classic';
    $_shopContent = [
        'title'        => $shopProfile->shop_header_title ?? 'Prodotti',
        'subtitle'     => $shopProfile->shop_header_subtitle ?? 'Acquista i prodotti del salone con ritiro in sede.',
        'image'        => $shopProfile->shop_header_image,
        'image_mobile' => $shopProfile->shop_header_image_mobile,
    ];
    $_shopPreset    = $shopProfile->shop_header_image_preset;
    $_heroPresetUrl = $_shopPreset ? (\App\Models\SalonProfile::heroPresets()[$_shopPreset]['url'] ?? null) : null;
    $_cartCount     = $cartItems->sum('quantity');
@endphp
@include('shop._header', [
    'content'         => $_shopContent,
    'hero_preset_url' => $_heroPresetUrl,
    'variant'         => $_shopVariant,
])

<section class="sf-section">
    <div style="max-width:1100px;margin:0 auto">
        @if ($errors->any())
            <div style="background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.2);border-radius:min(var(--sf-radius),12px);padding:14px 18px;margin-bottom:24px;color:#dc2626;font-size:0.9rem">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($products->isEmpty())
            <p style="color:var(--sf-body);text-align:center;padding:60px 0">Nessun prodotto disponibile al momento.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px">
                @foreach ($products as $product)
                    @php $inCart = $cartItems->firstWhere('product.id', $product->id); $qty = $inCart ? $inCart['quantity'] : 0; @endphp
                    <div data-product-id="{{ $product->id }}" data-stock="{{ $product->stock }}"
                         style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:min(var(--sf-radius),16px);overflow:hidden;display:flex;flex-direction:column">
                        @if ($product->hasMedia('photo'))
                            <img src="{{ $product->getFirstMediaUrl('photo', 'thumb') }}" alt="{{ $product->name }}"
                                 style="width:100%;height:220px;object-fit:cover;display:block">
                        @else
                            <div style="width:100%;height:220px;background:var(--sf-bg-alt);display:flex;align-items:center;justify-content:center">
                                <span style="color:var(--sf-body);font-size:0.85rem">Nessuna foto</span>
                            </div>
                        @endif

                        <div style="padding:20px;flex:1;display:flex;flex-direction:column;gap:12px">
                            <div>
                                <h3 style="font-weight:600;color:var(--sf-ink);margin:0 0 6px;font-size:1rem">{{ $product->name }}</h3>
                                @if ($product->description)
                                    <p style="color:var(--sf-body);font-size:0.875rem;margin:0;line-height:1.5">{{ $product->description }}</p>
                                @endif
                            </div>
                            <p style="font-size:1.2rem;font-weight:700;color:var(--sf-accent);margin:0">
                                {{ number_format($product->price, 2, ',', '.') }} €
                            </p>

                            <div style="margin-top:auto">
                                @if ($product->stock === 0)
                                    <span style="display:inline-block;background:var(--sf-bg-alt);border:1px solid var(--sf-border);border-radius:100px;padding:6px 14px;font-size:0.8rem;color:var(--sf-body)">
                                        Esaurito
                                    </span>
                                @else
                                    <div class="sf-stepper{{ $qty > 0 ? ' sf-stepper--active' : '' }}"
                                         data-update="{{ route('shop.cart.update') }}"
                                         data-remove="{{ route('shop.cart.remove', $product->id) }}">
                                        <input type="hidden" class="sf-csrf" value="{{ csrf_token() }}">
                                        <button type="button" class="sf-stepper-dec">Rimuovi</button>
                                        <span class="sf-stepper-count">{{ $qty > 0 ? $qty : '' }}</span>
                                        <button type="button" class="sf-stepper-inc sf-btn">Aggiungi</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

<a href="{{ route('shop.checkout') }}"
   class="sf-cart-fab{{ $_cartCount === 0 ? ' sf-cart-hidden' : '' }}"
   aria-label="Carrello: {{ $_cartCount }} {{ $_cartCount === 1 ? 'articolo' : 'articoli' }}">
    <span class="sf-cart-fab-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <span class="sf-cart-fab-count">{{ $_cartCount }}</span>
    </span>
    <span class="sf-cart-fab-label">Vai al checkout <span class="sf-cart-count-label">({{ $_cartCount }})</span></span>
</a>
@endsection

@push('scripts')
<script>
(function () {
    function updateFab(cartCount) {
        var fab = document.querySelector('.sf-cart-fab');
        if (!fab) return;
        fab.classList.toggle('sf-cart-hidden', cartCount === 0);
        var badge = fab.querySelector('.sf-cart-fab-count');
        var label = fab.querySelector('.sf-cart-count-label');
        if (badge) badge.textContent = cartCount;
        if (label) label.textContent = cartCount;
        fab.setAttribute('aria-label', 'Carrello: ' + cartCount + (cartCount === 1 ? ' articolo' : ' articoli'));
    }

    function updateStepper(stepper, newQty) {
        var count = stepper.querySelector('.sf-stepper-count');
        if (newQty > 0) {
            stepper.classList.add('sf-stepper--active');
            if (count) count.textContent = newQty;
        } else {
            stepper.classList.remove('sf-stepper--active');
            if (count) count.textContent = '';
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.sf-stepper-inc, .sf-stepper-dec');
        if (!btn) return;

        var stepper    = btn.closest('.sf-stepper');
        var card       = btn.closest('[data-product-id]');
        var productId  = parseInt(card.dataset.productId);
        var stock      = parseInt(card.dataset.stock);
        var countEl    = stepper.querySelector('.sf-stepper-count');
        var currentQty = parseInt(countEl.textContent) || 0;
        var isInc      = btn.classList.contains('sf-stepper-inc');
        var newQty     = isInc ? currentQty + 1 : currentQty - 1;

        if (isInc && newQty > stock) return;

        var csrf = card.querySelector('.sf-csrf').value;
        var fd   = new FormData();
        fd.append('_token', csrf);

        var url;
        if (newQty <= 0) {
            fd.append('_method', 'DELETE');
            url = stepper.dataset.remove;
        } else {
            fd.append('product_id', productId);
            fd.append('quantity', newQty);
            url = stepper.dataset.update;
        }

        btn.disabled = true;
        fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                updateStepper(stepper, newQty <= 0 ? 0 : newQty);
                updateFab(data.cartCount);
            })
            .catch(function () {})
            .finally(function () { btn.disabled = false; });
    });
})();
</script>
@endpush
