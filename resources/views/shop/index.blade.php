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
.sf-cart-fab-icon { position: relative; display: flex; align-items: center; justify-content: center; margin-right: 4px; }
.sf-cart-fab-count {
    position: absolute; top: -5px; right: -8px;
    min-width: 15px; height: 15px; padding: 0 5px;
    background: var(--sf-gold); color: #000;
    border-radius: 100px; font-size: 10px; font-weight: 700;
    line-height: 15px; text-align: center;
    font-family: var(--sf-font-body); letter-spacing: 0;
}
.sf-cart-fab-label { display: none; }
@media (max-width: 768px) {
    .sf-cart-fab {
        bottom: 20px; left: 50%; right: auto;
        transform: translateX(-50%);
        width: auto; height: auto; border-radius: var(--sf-radius);
        padding: 10px 40px;
        font-family: var(--sf-font-body);
        font-size: 11px; letter-spacing: 2.5px; text-transform: uppercase;
        box-shadow: 0 4px 24px rgba(0,0,0,0.24);
        white-space: nowrap; font-weight: 700;
    }
    .sf-cart-fab:hover { transform: translateX(-50%) scale(1.03); }
    .sf-cart-fab-icon { display: none; }
    .sf-cart-fab-label { display: block; }
}
@media (prefers-reduced-motion: reduce) {
    .sf-cart-fab { transition: none; }
}
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
                    @php $inCart = $cartItems->firstWhere('product.id', $product->id); @endphp
                    <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:min(var(--sf-radius),16px);overflow:hidden;display:flex;flex-direction:column">
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
                            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px">
                                <p style="font-size:1.2rem;font-weight:700;color:var(--sf-accent);margin:0">
                                    {{ number_format($product->price, 2, ',', '.') }} €
                                </p>
                                @if ($inCart)
                                    <form method="POST" action="{{ route('shop.cart.remove', $product->id) }}" style="display:inline;flex-shrink:0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:none;border:none;cursor:pointer;font-size:0.78rem;color:var(--sf-muted);padding:0;text-decoration:underline;text-underline-offset:2px">
                                            Rimuovi
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div style="margin-top:auto">
                                @if ($product->stock === 0)
                                    <span style="display:inline-block;background:var(--sf-bg-alt);border:1px solid var(--sf-border);border-radius:100px;padding:6px 14px;font-size:0.8rem;color:var(--sf-body)">
                                        Esaurito
                                    </span>
                                @else
                                    <form method="POST" action="{{ route('shop.cart.update') }}">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <input type="number" name="quantity" value="{{ $inCart['quantity'] ?? 1 }}"
                                                   min="1" max="{{ $product->stock }}"
                                                   style="width:72px;border:1px solid var(--sf-border);border-radius:min(var(--sf-radius),20px);padding:8px 10px;font-size:0.875rem;background:var(--sf-bg);color:var(--sf-ink)">
                                            <button type="submit" class="sf-btn" style="flex:1;text-align:center">
                                                {{ $inCart ? 'Aggiorna' : 'Aggiungi' }}
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@if($cartItems->isNotEmpty())
@php $cartCount = $cartItems->sum('quantity'); @endphp
<a href="{{ route('shop.checkout') }}" class="sf-cart-fab"
   aria-label="Carrello: {{ $cartCount }} {{ $cartCount === 1 ? 'articolo' : 'articoli' }}">
    <span class="sf-cart-fab-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <span class="sf-cart-fab-count">{{ $cartCount }}</span>
    </span>
    <span class="sf-cart-fab-label">Vai al checkout ({{ $cartCount }})</span>
</a>
@endif
@endsection
