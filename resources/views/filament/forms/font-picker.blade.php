@php
$fonts = [
    'classic' => [
        'label'   => 'Classic Luxury',
        'sub'     => 'DM Serif + Inter',
        'display' => "'DM Serif Display', Georgia, serif",
        'body'    => "'Inter', sans-serif",
        'sample'  => 'Il tuo Salone',
        'italic'  => true,
    ],
    'modern' => [
        'label'   => 'Modern Clean',
        'sub'     => 'Plus Jakarta Sans',
        'display' => "'Plus Jakarta Sans', sans-serif",
        'body'    => "'Plus Jakarta Sans', sans-serif",
        'sample'  => 'Il tuo Salone',
        'italic'  => false,
    ],
    'elegant' => [
        'label'   => 'Elegant Serif',
        'sub'     => 'Cormorant + Nunito',
        'display' => "'Cormorant Garamond', Georgia, serif",
        'body'    => "'Nunito', sans-serif",
        'sample'  => 'Il tuo Salone',
        'italic'  => true,
    ],
    'minimal' => [
        'label'   => 'Minimal Sans',
        'sub'     => 'Space Grotesk',
        'display' => "'Space Grotesk', sans-serif",
        'body'    => "'Space Grotesk', sans-serif",
        'sample'  => 'Il tuo Salone',
        'italic'  => false,
    ],
];

$currentValue = $getState() ?? 'classic';
$statePath    = $getStatePath();
@endphp

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@400;600&family=Plus+Jakarta+Sans:wght@400;700&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Nunito:wght@400;600&family=Space+Grotesk:wght@400;600&display=swap" rel="stylesheet">

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ selected: @js($currentValue) }"
        class="grid grid-cols-2 gap-3 sm:grid-cols-4"
    >
        @foreach ($fonts as $value => $font)
            <button
                type="button"
                x-on:click="selected = @js($value); $wire.set(@js($statePath), @js($value))"
                :class="selected === @js($value)
                    ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-900'
                    : 'ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-400 dark:hover:ring-gray-500'"
                class="relative flex flex-col overflow-hidden rounded-lg transition-all focus:outline-none"
            >
                {{-- Preview area --}}
                <div class="flex flex-col items-center justify-center gap-1 bg-gray-900 px-3 py-5">
                    <span
                        style="font-family: {{ $font['display'] }}; font-size: 18px; color: #c9a96e; line-height: 1.2; {{ $font['italic'] ? 'font-style: italic;' : '' }}"
                    >{{ $font['sample'] }}</span>
                    <span
                        style="font-family: {{ $font['body'] }}; font-size: 10px; color: #888; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px;"
                    >Prenota ora</span>
                </div>

                {{-- Label --}}
                <div class="flex flex-col items-center gap-0.5 px-2 py-2">
                    <div class="flex items-center gap-1">
                        <span
                            class="text-xs font-medium"
                            :class="selected === @js($value) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300'"
                        >{{ $font['label'] }}</span>
                        <svg
                            x-show="selected === @js($value)"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            class="w-3.5 h-3.5 text-primary-500 shrink-0"
                        ><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    </div>
                    <span class="text-[10px] text-gray-400">{{ $font['sub'] }}</span>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
