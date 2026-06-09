@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<section class="space-y-8">
    <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Checkout</h1>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 space-y-4">
        @foreach ($cartItems as $item)
            <div class="flex justify-between items-center">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $item['product']->name }}</p>
                    <p class="text-sm text-gray-500">x{{ $item['quantity'] }}</p>
                </div>
                <p class="font-semibold text-gray-900 dark:text-gray-100">
                    {{ number_format($item['product']->price * $item['quantity'], 2, ',', '.') }} €
                </p>
            </div>
        @endforeach

        <div class="border-t border-gray-200 dark:border-gray-700 pt-4 flex justify-between">
            <p class="font-bold text-gray-950 dark:text-gray-50">Totale</p>
            <p class="font-bold text-gray-950 dark:text-gray-50">{{ number_format($total, 2, ',', '.') }} €</p>
        </div>
    </div>

    <form method="POST" action="/portal/products/order" class="space-y-4">
        @csrf

        @if ($paymentMode === 'both')
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Metodo di pagamento</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="payment_method" value="stripe" class="text-primary-600">
                        <span class="text-sm">Online (carta)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="payment_method" value="cash" class="text-primary-600">
                        <span class="text-sm">In salone</span>
                    </label>
                </div>
            </div>
        @endif

        <div class="space-y-2">
            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Note (opzionale)</label>
            <textarea id="notes" name="notes" rows="3"
                      class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"></textarea>
        </div>

        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="flex gap-4">
            <a href="{{ route('portal.products.index') }}" class="rounded-md border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300">
                Torna ai prodotti
            </a>
            <button type="submit" class="btn-primary rounded-md px-5 py-2.5 text-sm font-semibold text-white">
                Conferma ordine
            </button>
        </div>
    </form>
</section>
@endsection
