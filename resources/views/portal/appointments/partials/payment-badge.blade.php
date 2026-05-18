@php
    $classes = match ($status) {
        'pending'            => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        'completed'          => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'refunded'           => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        'failed', 'cancelled'=> 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        default              => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };

    $label = match ($status) {
        'pending'   => 'In attesa',
        'completed' => 'Pagato',
        'refunded'  => 'Rimborsato',
        'failed'    => 'Fallito',
        'cancelled' => 'Annullato',
        default     => $status,
    };
@endphp

<span class="rounded-md px-2 py-1 text-xs font-medium {{ $classes }}">{{ $label }}</span>
