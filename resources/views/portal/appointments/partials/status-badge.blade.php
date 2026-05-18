@php
    $classes = match ($status) {
        'pending'   => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        'confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        default     => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };

    $label = match ($status) {
        'pending'   => 'In attesa',
        'confirmed' => 'Confermato',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
        default     => $status,
    };
@endphp

<span class="rounded-md px-2 py-1 text-xs font-medium {{ $classes }}">{{ $label }}</span>
