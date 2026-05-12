@php
    $classes = match ($status) {
        'pending' => 'bg-yellow-100 text-yellow-800',
        'completed' => 'bg-green-100 text-green-800',
        'refunded' => 'bg-sky-100 text-sky-800',
        'failed', 'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };

    $label = match ($status) {
        'pending' => 'In attesa',
        'completed' => 'Pagato',
        'refunded' => 'Rimborsato',
        'failed' => 'Fallito',
        'cancelled' => 'Annullato',
        default => $status,
    };
@endphp

<span class="rounded-md px-2 py-1 text-xs font-medium {{ $classes }}">{{ $label }}</span>
