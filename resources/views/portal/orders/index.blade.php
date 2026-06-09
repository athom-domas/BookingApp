@extends('layouts.app')

@section('title', 'I miei ordini')

@section('content')
<section class="space-y-8">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">I miei ordini</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Storico acquisti prodotti.</p>
    </div>

    @if ($orders->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">Nessun ordine ancora.</p>
    @else
        <div class="space-y-4">
            @foreach ($orders as $order)
                @php
                    $statusLabels = [
                        'pending'   => 'In attesa di pagamento',
                        'confirmed' => 'Confermato',
                        'ready'     => 'Pronto per il ritiro',
                        'completed' => 'Completato',
                        'cancelled' => 'Cancellato',
                    ];
                    $statusColors = [
                        'pending'   => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                        'confirmed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                        'ready'     => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                        'completed' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                        'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                    ];
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Ordine #{{ $order->id }} · {{ $order->created_at->format('d/m/Y H:i') }}
                            </p>
                            <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                                {{ number_format($order->total, 2, ',', '.') }} €
                            </p>
                        </div>
                        <span class="inline-block rounded-full px-3 py-1 text-xs font-medium {{ $statusColors[$order->status] ?? '' }}">
                            {{ $statusLabels[$order->status] ?? $order->status }}
                        </span>
                    </div>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        @foreach ($order->items as $item)
                            <li>{{ $item->product?->name ?? 'Prodotto' }} × {{ $item->quantity }} — {{ number_format($item->subtotal, 2, ',', '.') }} €</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</section>
@endsection
