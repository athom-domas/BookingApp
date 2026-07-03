@php
$blockClass   = $field->getExtraAttributes()['data-block-class'] ?? null;
$blockVariants = ($blockClass && method_exists($blockClass, 'variants')) ? $blockClass::variants() : [];
$currentValue = $getState() ?? '';
$statePath    = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ selected: @js($currentValue) }"
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
    >
        @foreach ($blockVariants as $key => $variant)
            <button
                type="button"
                x-on:click="selected = @js($key); $wire.set(@js($statePath), @js($key))"
                :class="selected !== @js($key) && 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500'"
                :style="selected === @js($key)
                    ? 'border-color: #C4714A; box-shadow: 0 0 0 3px rgba(196,113,74,0.18);'
                    : ''"
                class="group relative flex flex-col border rounded-xl overflow-hidden transition-all duration-150 focus:outline-none cursor-pointer w-full text-left"
            >
                @if (!empty($variant['preview']))
                    <div class="w-full bg-gray-50 dark:bg-gray-800 p-2">
                        {!! $variant['preview'] !!}
                    </div>
                @else
                    <div class="w-full h-[72px] bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm0 8a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zm12 0a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                    </div>
                @endif

                <div class="flex items-start justify-between px-3 py-2 bg-white dark:bg-gray-900">
                    <div class="flex-1 min-w-0 pr-2">
                        <p class="text-xs font-semibold leading-tight text-gray-700 dark:text-gray-200">{{ $variant['label'] }}</p>
                        @if (!empty($variant['description']))
                            <p class="text-[10px] leading-tight mt-0.5 text-gray-400">{{ $variant['description'] }}</p>
                        @endif
                    </div>
                    <svg
                        x-show="selected === @js($key)"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="#C4714A"
                        class="w-4 h-4 shrink-0 mt-0.5"
                    >
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
