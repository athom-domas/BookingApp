@php
$styles = [
    'sharp' => [
        'label'  => 'Sharp',
        'sub'    => 'Angoli netti',
        'rx'     => '0',
        'rx_card'=> '2',
    ],
    'rounded' => [
        'label'  => 'Rounded',
        'sub'    => 'Leggermente arrotondato',
        'rx'     => '6',
        'rx_card'=> '10',
    ],
    'pill' => [
        'label'  => 'Pill',
        'sub'    => 'Completamente tondeggiante',
        'rx'     => '50',
        'rx_card'=> '16',
    ],
];

$currentValue = $getState() ?? 'sharp';
$statePath    = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ selected: @js($currentValue) }"
        class="grid grid-cols-3 gap-3"
    >
        @foreach ($styles as $value => $style)
            <button
                type="button"
                x-on:click="selected = @js($value); $wire.set(@js($statePath), @js($value))"
                :class="selected === @js($value)
                    ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-900'
                    : 'ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-400 dark:hover:ring-gray-500'"
                class="relative flex flex-col overflow-hidden rounded-lg transition-all focus:outline-none"
            >
                {{-- Preview --}}
                <div class="flex flex-col items-center justify-center gap-3 bg-gray-900 px-4 py-6">
                    {{-- Button shape --}}
                    <svg viewBox="0 0 120 32" xmlns="http://www.w3.org/2000/svg" class="w-full max-w-[120px]">
                        <rect x="4" y="4" width="112" height="24" rx="{{ $style['rx'] }}" fill="#c9a96e"/>
                        <rect x="30" y="13" width="60" height="6" rx="2" fill="#0a0806" opacity="0.6"/>
                    </svg>
                    {{-- Card shape --}}
                    <svg viewBox="0 0 120 56" xmlns="http://www.w3.org/2000/svg" class="w-full max-w-[120px]">
                        <rect x="4" y="2" width="112" height="52" rx="{{ $style['rx_card'] }}" fill="#161208" stroke="#c9a96e" stroke-opacity="0.3" stroke-width="1"/>
                        <rect x="16" y="12" width="50" height="5" rx="2" fill="#e8d5a3" opacity="0.7"/>
                        <rect x="16" y="21" width="36" height="3" rx="1.5" fill="#888" opacity="0.5"/>
                        <rect x="16" y="38" width="40" height="10" rx="{{ $style['rx'] }}" fill="#c9a96e" opacity="0.8"/>
                    </svg>
                </div>

                {{-- Label --}}
                <div class="flex flex-col items-center gap-0.5 px-2 py-2">
                    <div class="flex items-center gap-1">
                        <span
                            class="text-xs font-medium"
                            :class="selected === @js($value) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300'"
                        >{{ $style['label'] }}</span>
                        <svg
                            x-show="selected === @js($value)"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                            class="w-3.5 h-3.5 text-primary-500 shrink-0"
                        ><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                    </div>
                    <span class="text-[10px] text-gray-400">{{ $style['sub'] }}</span>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
