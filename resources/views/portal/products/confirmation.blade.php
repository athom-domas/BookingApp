@extends('layouts.app')

@section('title', 'Ordine confermato')

@section('content')
<section class="space-y-8 max-w-2xl mx-auto text-center">
    <div>
        <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
            <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1 class="mt-4 font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Ordine confermato!</h1>
        <p class="mt-2 text-gray-500 dark:text-gray-400">
            @if ($order->payment_method === 'cash')
                Il tuo ordine è stato ricevuto. Passa in salone per ritirarlo e pagare.
            @else
                Pagamento ricevuto. Passa in salone per ritirare i tuoi prodotti.
            @endif
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6 text-left space-y-4">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Riepilogo ordine #{{ $order->id }}</h2>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($order->items as $item)
                    <tr>
                        <td class="py-3 text-gray-900 dark:text-gray-100">{{ $item->product?->name ?? 'Prodotto' }}</td>
                        <td class="py-3 text-center text-gray-600 dark:text-gray-400">× {{ $item->quantity }}</td>
                        <td class="py-3 text-right font-medium text-gray-900 dark:text-gray-100">
                            {{ number_format($item->subtotal, 2, ',', '.') }} €
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td colspan="2" class="pt-3 font-semibold text-right text-gray-900 dark:text-gray-100">Totale</td>
                    <td class="pt-3 text-right font-bold text-gray-950 dark:text-gray-50">
                        {{ number_format($order->total, 2, ',', '.') }} €
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex justify-center gap-4">
        <a href="{{ route('portal.products.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">
            Continua gli acquisti
        </a>
        <a href="{{ route('portal.orders.index') }}" class="btn-primary rounded-md px-5 py-2 text-sm font-semibold text-white">
            I miei ordini
        </a>
    </div>
</section>
@endsection
