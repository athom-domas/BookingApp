@php
$textures = [
    'none' => [
        'label' => 'Nessuna',
        'sub'   => 'Sfondo piatto',
        'css'   => '',
    ],
    'noise' => [
        'label' => 'Rumore',
        'sub'   => 'Grana fotografica',
        'css'   => "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.65' numOctaves='3' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.06'/%3E%3C/svg%3E\")",
    ],
    'dots' => [
        'label' => 'Puntini',
        'sub'   => 'Griglia di dot',
        'css'   => "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20'%3E%3Ccircle cx='2' cy='2' r='1.2' fill='%23ffffff' fill-opacity='.07'/%3E%3C/svg%3E\")",
    ],
    'lines' => [
        'label' => 'Linee',
        'sub'   => 'Diagonali sottili',
        'css'   => "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Cline x1='0' y1='40' x2='40' y2='0' stroke='%23ffffff' stroke-opacity='.07' stroke-width='1'/%3E%3C/svg%3E\")",
    ],
    'grid' => [
        'label' => 'Griglia',
        'sub'   => 'Reticolo fine',
        'css'   => "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Cpath d='M 40 0 L 0 0 0 40' fill='none' stroke='%23ffffff' stroke-opacity='.06' stroke-width='.5'/%3E%3C/svg%3E\")",
    ],
];

$currentValue = $getState() ?? 'none';
$statePath    = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ selected: @js($currentValue) }"
        class="grid grid-cols-3 gap-3 sm:grid-cols-5"
    >
        @foreach ($textures as $value => $texture)
            <button
                type="button"
                x-on:click="selected = @js($value); $wire.set(@js($statePath), @js($value))"
                :class="selected === @js($value)
                    ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-900'
                    : 'ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-400 dark:hover:ring-gray-500'"
                class="relative flex flex-col overflow-hidden rounded-lg transition-all focus:outline-none"
            >
                {{-- Preview box --}}
                <div
                    class="h-16 w-full bg-gray-900"
                    @if($texture['css'])
                        style="background-color:#0d0b08; background-image: {{ $texture['css'] }};"
                    @else
                        style="background-color:#0d0b08;"
                    @endif
                ></div>

                {{-- Label --}}
                <div class="flex flex-col items-center gap-0.5 px-2 py-2">
                    <div class="flex items-center gap-1">
                        <span
                            class="text-xs font-medium"
                            :class="selected === @js($value) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300'"
                        >{{ $texture['label'] }}</span>
                        <svg
                            x-show="selected === @js($value)"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            class="w-3 h-3 text-primary-500 shrink-0"
                        ><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    </div>
                    <span class="text-[10px] text-gray-400">{{ $texture['sub'] }}</span>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
