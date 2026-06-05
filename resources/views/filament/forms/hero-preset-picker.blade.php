@php
$presets      = \App\Models\SalonProfile::heroPresets();
$currentValue = $getState() ?? '';
$statePath    = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ selected: @js($currentValue) }"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
    >
        {{-- Nessuna immagine --}}
        <button
            type="button"
            x-on:click="selected = ''; $wire.set(@js($statePath), '')"
            :class="selected !== '' && 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500'"
            :style="selected === ''
                ? 'border-color: #C4714A; box-shadow: 0 0 0 3px rgba(196,113,74,0.18);'
                : ''"
            class="group relative flex flex-col border rounded-xl overflow-hidden transition-all duration-150 focus:outline-none cursor-pointer w-full text-left"
        >
            <div class="w-full h-[104px] bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
            <div class="flex items-center justify-between px-3 py-2 bg-white dark:bg-gray-900">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold leading-tight truncate text-gray-700 dark:text-gray-200">Nessuna</p>
                    <p class="text-[10px] leading-tight mt-0.5 text-gray-400 truncate">Usa il colore del tema</p>
                </div>
                <svg
                    x-show="selected === ''"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="#C4714A"
                    class="w-4 h-4 shrink-0 ml-1"
                >
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                </svg>
            </div>
        </button>

        {{-- Preset images --}}
        @foreach ($presets as $key => $preset)
            <button
                type="button"
                x-on:click="selected = @js($key); $wire.set(@js($statePath), @js($key))"
                :class="selected !== @js($key) && 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500'"
                :style="selected === @js($key)
                    ? 'border-color: #C4714A; box-shadow: 0 0 0 3px rgba(196,113,74,0.18);'
                    : ''"
                class="group relative flex flex-col border rounded-xl overflow-hidden transition-all duration-150 focus:outline-none cursor-pointer w-full text-left"
            >
                <div class="w-full h-[104px] overflow-hidden bg-gray-200 dark:bg-gray-700">
                    <img
                        src="{{ $preset['thumb'] }}"
                        alt="{{ $preset['label'] }}"
                        class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    >
                </div>
                <div class="flex items-center justify-between px-3 py-2 bg-white dark:bg-gray-900">
                    <p class="text-xs font-semibold leading-tight truncate text-gray-700 dark:text-gray-200 flex-1 min-w-0">{{ $preset['label'] }}</p>
                    <svg
                        x-show="selected === @js($key)"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="#C4714A"
                        class="w-4 h-4 shrink-0 ml-1"
                    >
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
