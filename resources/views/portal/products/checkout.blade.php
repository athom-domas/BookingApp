@extends('layouts.app')

@section('title', 'Checkout')

@section('content')
<section class="space-y-8 max-w-2xl mx-auto">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Checkout</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Riepilogo e conferma ordine.</p>
    </div>

    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 space-y-4">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Riepilogo ordine</h2>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <th class="pb-2 font-medium">Prodotto</th>
                    <th class="pb-2 font-medium text-center">Qtà</th>
                    <th class="pb-2 font-medium text-right">Prezzo</th>
                    <th class="pb-2 font-medium text-right">Subtotale</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($cartItems as $item)
                    <tr>
                        <td class="py-3 text-gray-900 dark:text-gray-100">{{ $item['product']->name }}</td>
                        <td class="py-3 text-center text-gray-600 dark:text-gray-400">{{ $item['quantity'] }}</td>
                        <td class="py-3 text-right text-gray-600 dark:text-gray-400">{{ number_format($item['product']->price, 2, ',', '.') }} €</td>
                        <td class="py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                            {{ number_format($item['product']->price * $item['quantity'], 2, ',', '.') }} €
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td colspan="3" class="pt-3 font-semibold text-right text-gray-900 dark:text-gray-100">Totale</td>
                    <td class="pt-3 text-right font-bold text-gray-950 dark:text-gray-50">
                        {{ number_format($total, 2, ',', '.') }} €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <form method="POST" action="{{ route('portal.products.order') }}" class="space-y-6">
        @csrf

        @if ($paymentMode === 'both')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 space-y-4">
                <h2 class="font-semibold text-gray-900 dark:text-gray-100">Metodo di pagamento</h2>
                <div class="space-y-2">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="payment_method" value="stripe" checked class="text-primary-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Online con carta (Stripe)</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="radio" name="payment_method" value="cash" class="text-primary-600">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Al ritiro in salone (contanti)</span>
                    </label>
                </div>
            </div>
        @elseif ($paymentMode === 'online')
            <input type="hidden" name="payment_method" value="stripe">
            <p class="text-sm text-gray-600 dark:text-gray-400">Pagamento online con carta.</p>
        @else
            <input type="hidden" name="payment_method" value="cash">
            <p class="text-sm text-gray-600 dark:text-gray-400">Pagamento al ritiro in salone.</p>
        @endif

        <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Note (opzionale)</label>
            <textarea name="notes" rows="2"
                      class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                      placeholder="Informazioni per il ritiro..."></textarea>
        </div>

        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('portal.products.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">
                ← Torna ai prodotti
            </a>
            <button type="submit" class="btn-primary rounded-md px-6 py-2.5 text-sm font-semibold text-white">
                Conferma ordine
            </button>
        </div>
    </form>
</section>
@endsection
