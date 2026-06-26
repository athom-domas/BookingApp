@extends('layouts.storefront')

@section('title', 'Prodotti')

@section('content')
<section class="sf-section">
    <div style="max-width:1100px;margin:0 auto">
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:40px">
            <div>
                <h1 style="font-size:clamp(1.75rem,4vw,2.5rem);font-family:var(--sf-font-display);font-weight:600;color:var(--sf-ink);margin:0 0 8px">Prodotti</h1>
                <p style="color:var(--sf-body);font-size:0.95rem;margin:0">Acquista i prodotti del salone con ritiro in sede.</p>
            </div>
            @if ($cartItems->isNotEmpty())
                <a href="{{ route('shop.checkout') }}" class="sf-btn" style="text-decoration:none">
                    Vai al checkout ({{ $cartItems->sum('quantity') }})
                </a>
            @endif
        </div>

        @if ($errors->any())
            <div style="background:rgba(220,38,38,0.08);border:1px solid rgba(220,38,38,0.2);border-radius:var(--sf-radius);padding:14px 18px;margin-bottom:24px;color:#dc2626;font-size:0.9rem">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($products->isEmpty())
            <p style="color:var(--sf-body);text-align:center;padding:60px 0">Nessun prodotto disponibile al momento.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px">
                @foreach ($products as $product)
                    @php $inCart = $cartItems->firstWhere('product.id', $product->id); @endphp
                    <div style="background:var(--sf-surface);border:1px solid var(--sf-border);border-radius:var(--sf-radius);overflow:hidden;display:flex;flex-direction:column">
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

                            @if ($product->stock === 0)
                                <span style="display:inline-block;background:var(--sf-bg-alt);border:1px solid var(--sf-border);border-radius:100px;padding:4px 14px;font-size:0.8rem;color:var(--sf-body)">
                                    Esaurito
                                </span>
                            @else
                                <form method="POST" action="{{ route('shop.cart.update') }}" style="margin-top:auto">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <div style="display:flex;align-items:center;gap:10px">
                                        <input type="number" name="quantity" value="{{ $inCart['quantity'] ?? 1 }}"
                                               min="1" max="{{ $product->stock }}"
                                               style="width:72px;border:1px solid var(--sf-border);border-radius:var(--sf-radius);padding:8px 10px;font-size:0.875rem;background:var(--sf-bg);color:var(--sf-ink)">
                                        <button type="submit" class="sf-btn" style="flex:1;text-align:center">
                                            {{ $inCart ? 'Aggiorna' : 'Aggiungi' }}
                                        </button>
                                    </div>
                                </form>
                                @if ($inCart)
                                    <form method="POST" action="{{ route('shop.cart.remove', $product->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:none;border:none;cursor:pointer;font-size:0.8rem;color:var(--sf-body);text-decoration:underline;padding:0">
                                            Rimuovi dal carrello
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
