@php
    $classes = match ($status) {
        'pending' => 'bg-yellow-100 text-yellow-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'completed' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };

    $label = match ($status) {
        'pending' => 'In attesa',
        'confirmed' => 'Confermato',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
        default => $status,
    };
@endphp

<span class="rounded-md px-2 py-1 text-xs font-medium {{ $classes }}">{{ $label }}</span>
