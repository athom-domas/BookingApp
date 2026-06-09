@extends('layouts.app')

@section('title', 'Prodotti')

@section('content')
<section class="space-y-8">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Prodotti</h1>
            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Acquista i prodotti del salone con ritiro in sede.</p>
        </div>
        @if ($cartItems->isNotEmpty())
            <a href="{{ route('portal.products.checkout') }}" class="btn-primary inline-block rounded-md px-5 py-2.5 text-sm font-semibold text-center text-white">
                Vai al checkout ({{ $cartItems->count() }})
            </a>
        @endif
    </div>

    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($products->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">Nessun prodotto disponibile al momento.</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                @php $inCart = $cartItems->firstWhere('product.id', $product->id); @endphp
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 overflow-hidden">
                    @if ($product->hasMedia('photo'))
                        <img src="{{ $product->getFirstMediaUrl('photo', 'thumb') }}" alt="{{ $product->name }}"
                             class="h-48 w-full object-cover">
                    @else
                        <div class="h-48 w-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <span class="text-gray-400 text-sm">Nessuna foto</span>
                        </div>
                    @endif
                    <div class="p-5 space-y-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</h3>
                            @if ($product->description)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $product->description }}</p>
                            @endif
                        </div>
                        <p class="text-lg font-semibold text-gray-950 dark:text-gray-50">
                            {{ number_format($product->price, 2, ',', '.') }} €
                        </p>
                        @if ($product->stock === 0)
                            <span class="inline-block rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                Esaurito
                            </span>
                        @else
                            <form method="POST" action="{{ route('portal.cart.update') }}">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <div class="flex items-center gap-3">
                                    <input type="number" name="quantity" value="{{ $inCart['quantity'] ?? 1 }}"
                                           min="1" max="{{ $product->stock }}"
                                           class="w-20 rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                    <button type="submit" class="btn-primary rounded-md px-4 py-1.5 text-sm font-semibold text-white">
                                        {{ $inCart ? 'Aggiorna' : 'Aggiungi' }}
                                    </button>
                                </div>
                            </form>
                            @if ($inCart)
                                <form method="POST" action="{{ route('portal.cart.remove', $product->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline dark:text-red-400">
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
</section>
@endsection
