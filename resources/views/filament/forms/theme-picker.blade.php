@php
$themes = [
    'luxury' => [
        'label'       => 'Luxury',
        'description' => 'Oro e crema',
        'bg'          => '#f7f3ec',
        'bg_nav'      => '#f2ece2',
        'bg_card'     => '#fffdf8',
        'accent'      => '#7a5c38',
        'text'        => '#1a1008',
        'btn'         => '#1a1008',
        'btn_fg'      => '#f7f3ec',
        'swatches'    => ['#0d0b08', '#c9a96e', '#f7f3ec'],
    ],
    'rosa' => [
        'label'       => 'Rosa',
        'description' => 'Cipria e borgogna',
        'bg'          => '#fdf2f0',
        'bg_nav'      => '#f7e8e5',
        'bg_card'     => '#fef8f7',
        'accent'      => '#9e4858',
        'text'        => '#2c1015',
        'btn'         => '#2c1015',
        'btn_fg'      => '#fdf2f0',
        'swatches'    => ['#160a0d', '#d4847a', '#fdf2f0'],
    ],
    'verde' => [
        'label'       => 'Verde',
        'description' => 'Salvia e bosco',
        'bg'          => '#f1f5ef',
        'bg_nav'      => '#e8ede5',
        'bg_card'     => '#f8faf7',
        'accent'      => '#4a7040',
        'text'        => '#1a2a18',
        'btn'         => '#1a2a18',
        'btn_fg'      => '#f1f5ef',
        'swatches'    => ['#061510', '#5eb870', '#f1f5ef'],
    ],
    'notte' => [
        'label'       => 'Notte',
        'description' => 'Blu e periwinkle',
        'bg'          => '#f0f2fa',
        'bg_nav'      => '#e8ecf5',
        'bg_card'     => '#f8f9fd',
        'accent'      => '#3a50a0',
        'text'        => '#0c1430',
        'btn'         => '#0c1430',
        'btn_fg'      => '#f0f2fa',
        'swatches'    => ['#06081a', '#7a96d4', '#f0f2fa'],
    ],
    'minimal' => [
        'label'       => 'Minimal',
        'description' => 'Bianco e nero',
        'bg'          => '#ffffff',
        'bg_nav'      => '#f4f4f2',
        'bg_card'     => '#fafafa',
        'accent'      => '#1a1a1a',
        'text'        => '#111111',
        'btn'         => '#111111',
        'btn_fg'      => '#ffffff',
        'swatches'    => ['#0f0f0f', '#888888', '#ffffff'],
    ],
    'viola' => [
        'label'       => 'Viola',
        'description' => 'Lavanda e prugna',
        'bg'          => '#f5f0fa',
        'bg_nav'      => '#ede5f5',
        'bg_card'     => '#faf8fd',
        'accent'      => '#6a38a8',
        'text'        => '#1c0c38',
        'btn'         => '#1c0c38',
        'btn_fg'      => '#f5f0fa',
        'swatches'    => ['#110b18', '#c090d8', '#f5f0fa'],
    ],
    'terracotta' => [
        'label'       => 'Terracotta',
        'description' => 'Arancio e terra',
        'bg'          => '#faf4ee',
        'bg_nav'      => '#f3e8da',
        'bg_card'     => '#fefaf6',
        'accent'      => '#b85a20',
        'text'        => '#3a1e0a',
        'btn'         => '#3a1e0a',
        'btn_fg'      => '#faf4ee',
        'swatches'    => ['#180d06', '#d4784a', '#faf4ee'],
    ],
    'acqua' => [
        'label'       => 'Acqua',
        'description' => 'Teal e mare',
        'bg'          => '#ecf8f8',
        'bg_nav'      => '#d5f0ee',
        'bg_card'     => '#f5fbfb',
        'accent'      => '#1a8880',
        'text'        => '#0a2e30',
        'btn'         => '#0a2e30',
        'btn_fg'      => '#ecf8f8',
        'swatches'    => ['#031012', '#5adad0', '#ecf8f8'],
    ],
    'cipria' => [
        'label'       => 'Cipria',
        'description' => 'Pesca e avorio',
        'bg'          => '#fdf6f2',
        'bg_nav'      => '#f8e8e0',
        'bg_card'     => '#fefaf8',
        'accent'      => '#c87860',
        'text'        => '#3c1a14',
        'btn'         => '#3c1a14',
        'btn_fg'      => '#fdf6f2',
        'swatches'    => ['#140806', '#e09888', '#fdf6f2'],
    ],
];

$currentValue = $getState() ?? 'luxury';
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
        @foreach ($themes as $value => $theme)
            <button
                type="button"
                x-on:click="selected = @js($value); $wire.set(@js($statePath), @js($value))"
                :class="selected !== @js($value) && 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500'"
                :style="selected === @js($value)
                    ? 'border-color: #C4714A; box-shadow: 0 0 0 3px rgba(196,113,74,0.18);'
                    : ''"
                class="group relative flex flex-col border rounded-xl overflow-hidden transition-all duration-150 focus:outline-none cursor-pointer w-full text-left"
            >
                {{-- SVG thumbnail (light variant preview) --}}
                <svg
                    viewBox="0 0 160 100"
                    xmlns="http://www.w3.org/2000/svg"
                    class="w-full block"
                >
                    <rect width="160" height="100" fill="{{ $theme['bg'] }}"/>

                    {{-- nav bar --}}
                    <rect width="160" height="14" fill="{{ $theme['bg_nav'] }}"/>
                    <rect x="8" y="4.5" width="18" height="5" rx="1" fill="{{ $theme['accent'] }}" opacity="0.9"/>
                    <rect x="29" y="5.5" width="9" height="3" rx="1" fill="{{ $theme['accent'] }}" opacity="0.35"/>
                    <rect x="86" y="5.5" width="12" height="3" rx="1" fill="{{ $theme['text'] }}" opacity="0.3"/>
                    <rect x="102" y="5.5" width="12" height="3" rx="1" fill="{{ $theme['text'] }}" opacity="0.3"/>
                    {{-- mode toggle icon --}}
                    <circle cx="130" cy="7" r="4" fill="none" stroke="{{ $theme['accent'] }}" stroke-width="0.8" opacity="0.6"/>
                    <rect x="139" y="3" width="14" height="8" rx="2" fill="{{ $theme['btn'] }}" opacity="0.95"/>
                    <rect x="142" y="5.5" width="8" height="3" rx="1" fill="{{ $theme['btn_fg'] }}" opacity="0.7"/>

                    {{-- hero --}}
                    <rect x="36" y="22" width="88" height="5.5" rx="1.5" fill="{{ $theme['text'] }}" opacity="0.88"/>
                    <rect x="44" y="31" width="72" height="3.5" rx="1" fill="{{ $theme['text'] }}" opacity="0.42"/>
                    <rect x="52" y="37.5" width="56" height="2.5" rx="1" fill="{{ $theme['text'] }}" opacity="0.22"/>
                    <rect x="46" y="44" width="68" height="10" rx="2.5" fill="{{ $theme['btn'] }}" opacity="0.95"/>
                    <rect x="58" y="47.5" width="44" height="3" rx="1" fill="{{ $theme['btn_fg'] }}" opacity="0.75"/>

                    {{-- service cards --}}
                    <rect x="6"   y="60" width="46" height="34" rx="3" fill="{{ $theme['bg_card'] }}" stroke="{{ $theme['accent'] }}" stroke-width="0.5" stroke-opacity="0.35"/>
                    <rect x="57"  y="60" width="46" height="34" rx="3" fill="{{ $theme['bg_card'] }}" stroke="{{ $theme['accent'] }}" stroke-width="0.5" stroke-opacity="0.35"/>
                    <rect x="108" y="60" width="46" height="34" rx="3" fill="{{ $theme['bg_card'] }}" stroke="{{ $theme['accent'] }}" stroke-width="0.5" stroke-opacity="0.35"/>

                    @foreach ([6, 57, 108] as $cx)
                        <circle cx="{{ $cx + 8 }}" cy="69.5" r="3.5" fill="{{ $theme['accent'] }}" opacity="0.65"/>
                        <rect x="{{ $cx + 15 }}" y="67" width="24" height="3" rx="1" fill="{{ $theme['text'] }}" opacity="0.65"/>
                        <rect x="{{ $cx + 7 }}" y="76.5" width="32" height="2" rx="1" fill="{{ $theme['text'] }}" opacity="0.28"/>
                        <rect x="{{ $cx + 7 }}" y="81" width="22" height="2" rx="1" fill="{{ $theme['text'] }}" opacity="0.18"/>
                        <rect x="{{ $cx + 7 }}" y="86.5" width="32" height="5.5" rx="1.5" fill="{{ $theme['btn'] }}" opacity="0.82"/>
                    @endforeach
                </svg>

                {{-- info strip --}}
                <div
                    class="flex items-center gap-2 px-3 py-2"
                    style="background: {{ $theme['bg_nav'] }}"
                >
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold leading-tight truncate" style="color: {{ $theme['text'] }}">{{ $theme['label'] }}</p>
                        <p class="text-[10px] leading-tight mt-0.5 truncate" style="color: {{ $theme['text'] }}; opacity: 0.55">{{ $theme['description'] }}</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        @foreach ($theme['swatches'] as $swatch)
                            <span
                                class="block w-2.5 h-2.5 rounded-full"
                                style="background: {{ $swatch }}; box-shadow: inset 0 0 0 0.75px rgba(255,255,255,0.15), inset 0 0 0 0.75px rgba(0,0,0,0.12)"
                            ></span>
                        @endforeach
                        <svg
                            x-show="selected === @js($value)"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="#C4714A"
                            class="w-4 h-4 shrink-0 ml-0.5"
                        >
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
