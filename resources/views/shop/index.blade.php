@extends('layouts.storefront')

@section('title', 'Prodotti')

@section('content')
@php
    $shopProfile = \App\Models\SalonProfile::current();
    $shopVariant = $shopProfile->shop_header_variant ?? 'classic';
    $_shopContent = [
        'title'        => $shopProfile->shop_header_title ?? 'Prodotti',
        'subtitle'     => $shopProfile->shop_header_subtitle ?? 'Acquista i prodotti del salone con ritiro in sede.',
        'image'        => $shopProfile->shop_header_image,
        'image_mobile' => $shopProfile->shop_header_image_mobile,
        'cta_label'    => '',
    ];
    $_shopSettings  = ['show_cta' => false];
    $_shopPreset    = $shopProfile->shop_header_image_preset;
    $_heroPresetUrl = $_shopPreset ? (\App\Models\SalonProfile::heroPresets()[$_shopPreset]['url'] ?? null) : null;
@endphp
@include("page-blocks.hero.{$shopVariant}", [
    'content'         => $_shopContent,
    'settings'        => $_shopSettings,
    'hero_preset_url' => $_heroPresetUrl,
    'business'        => $business,
    'block'           => null,
])

<section class="sf-section">
    <div style="max-width:1100px;margin:0 auto">
        @if ($cartItems->isNotEmpty())
            <div style="display:flex;justify-content:flex-end;margin-bottom:40px">
                <a href="{{ route('shop.checkout') }}" class="sf-btn" style="text-decoration:none">
                    Vai al checkout ({{ $cartItems->sum('quantity') }})
                </a>
            </div>
        @endif

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
@endsection
