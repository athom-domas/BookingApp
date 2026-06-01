@php
use Illuminate\Support\HtmlString;

$themes = [
    'dark' => [
        'label'   => 'Luxury Dark',
        'bg'      => '#0d0b08',
        'bg_nav'  => '#161208',
        'bg_card' => '#131008',
        'accent'  => '#c9a96e',
        'text'    => '#e8d5a3',
        'btn'     => '#c9a96e',
        'btn_fg'  => '#0a0806',
    ],
    'light' => [
        'label'   => 'Chiaro Caldo',
        'bg'      => '#f7f3ec',
        'bg_nav'  => '#f2ece2',
        'bg_card' => '#fffdf8',
        'accent'  => '#a08060',
        'text'    => '#1a1008',
        'btn'     => '#1a1008',
        'btn_fg'  => '#f7f3ec',
    ],
    'rose' => [
        'label'   => 'Rosa & Oro',
        'bg'      => '#160a0d',
        'bg_nav'  => '#200f14',
        'bg_card' => '#1f1015',
        'accent'  => '#d4847a',
        'text'    => '#f5d0c5',
        'btn'     => '#d4847a',
        'btn_fg'  => '#160a0d',
    ],
    'emerald' => [
        'label'   => 'Verde Smeraldo',
        'bg'      => '#061510',
        'bg_nav'  => '#0c2018',
        'bg_card' => '#0b2018',
        'accent'  => '#5eb870',
        'text'    => '#c0eed0',
        'btn'     => '#5eb870',
        'btn_fg'  => '#061510',
    ],
    'midnight' => [
        'label'   => 'Blu Notte',
        'bg'      => '#06081a',
        'bg_nav'  => '#0e1230',
        'bg_card' => '#0d1228',
        'accent'  => '#7a96d4',
        'text'    => '#c4d0f5',
        'btn'     => '#7a96d4',
        'btn_fg'  => '#06081a',
    ],
    'minimal' => [
        'label'   => 'Minimal',
        'bg'      => '#ffffff',
        'bg_nav'  => '#f4f4f2',
        'bg_card' => '#fafafa',
        'accent'  => '#1a1a1a',
        'text'    => '#111111',
        'btn'     => '#111111',
        'btn_fg'  => '#ffffff',
    ],
];

$currentValue = $getState() ?? 'dark';
$statePath    = $getStatePath();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{ selected: @js($currentValue) }"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3"
    >
        @foreach ($themes as $value => $theme)
            <button
                type="button"
                x-on:click="selected = @js($value); $wire.set(@js($statePath), @js($value))"
                :class="selected === @js($value)
                    ? 'ring-2 ring-primary-500 ring-offset-2 ring-offset-white dark:ring-offset-gray-900'
                    : 'ring-1 ring-gray-200 dark:ring-gray-700 hover:ring-gray-400 dark:hover:ring-gray-500'"
                class="relative flex flex-col items-center gap-2 rounded-lg overflow-hidden p-2 transition-all focus:outline-none"
            >
                {{-- SVG preview --}}
                <svg
                    viewBox="0 0 160 100"
                    xmlns="http://www.w3.org/2000/svg"
                    class="w-full rounded"
                    style="display:block"
                >
                    {{-- background --}}
                    <rect width="160" height="100" fill="{{ $theme['bg'] }}"/>

                    {{-- nav bar --}}
                    <rect width="160" height="15" fill="{{ $theme['bg_nav'] }}"/>
                    {{-- nav logo dot --}}
                    <rect x="8" y="5" width="24" height="5" rx="1" fill="{{ $theme['accent'] }}" opacity="0.8"/>
                    {{-- nav links --}}
                    <rect x="90" y="6" width="14" height="3" rx="1" fill="{{ $theme['text'] }}" opacity="0.3"/>
                    <rect x="110" y="6" width="14" height="3" rx="1" fill="{{ $theme['text'] }}" opacity="0.3"/>
                    {{-- nav cta --}}
                    <rect x="130" y="4" width="22" height="7" rx="1" fill="{{ $theme['btn'] }}"/>

                    {{-- hero eyebrow --}}
                    <rect x="56" y="22" width="48" height="2.5" rx="1" fill="{{ $theme['accent'] }}" opacity="0.6"/>
                    {{-- hero title --}}
                    <rect x="28" y="28" width="104" height="6" rx="1" fill="{{ $theme['text'] }}" opacity="0.85"/>
                    <rect x="40" y="37" width="80" height="4" rx="1" fill="{{ $theme['text'] }}" opacity="0.5"/>
                    {{-- hero subtitle --}}
                    <rect x="52" y="44" width="56" height="3" rx="1" fill="{{ $theme['text'] }}" opacity="0.3"/>
                    {{-- cta button --}}
                    <rect x="52" y="51" width="56" height="9" rx="1" fill="{{ $theme['btn'] }}"/>
                    <rect x="62" y="54" width="36" height="3" rx="1" fill="{{ $theme['btn_fg'] }}" opacity="0.7"/>

                    {{-- service cards row --}}
                    <rect x="8"   y="67" width="43" height="27" rx="2" fill="{{ $theme['bg_card'] }}" stroke="{{ $theme['accent'] }}" stroke-width="0.4" stroke-opacity="0.4"/>
                    <rect x="58"  y="67" width="43" height="27" rx="2" fill="{{ $theme['bg_card'] }}" stroke="{{ $theme['accent'] }}" stroke-width="0.4" stroke-opacity="0.4"/>
                    <rect x="108" y="67" width="43" height="27" rx="2" fill="{{ $theme['bg_card'] }}" stroke="{{ $theme['accent'] }}" stroke-width="0.4" stroke-opacity="0.4"/>
                    {{-- card content lines --}}
                    @foreach ([8, 58, 108] as $cx)
                        <rect x="{{ $cx + 6 }}" y="73" width="22" height="2.5" rx="1" fill="{{ $theme['text'] }}" opacity="0.6"/>
                        <rect x="{{ $cx + 6 }}" y="79" width="16" height="2" rx="1" fill="{{ $theme['text'] }}" opacity="0.3"/>
                        <rect x="{{ $cx + 6 }}" y="87" width="31" height="4" rx="1" fill="{{ $theme['accent'] }}" opacity="0.7"/>
                    @endforeach
                </svg>

                {{-- label + check --}}
                <div class="flex items-center justify-center gap-1 w-full">
                    <span
                        class="text-xs font-medium"
                        :class="selected === @js($value) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300'"
                    >{{ $theme['label'] }}</span>
                    <svg
                        x-show="selected === @js($value)"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="w-3.5 h-3.5 text-primary-500 shrink-0"
                    >
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </button>
        @endforeach
    </div>
</x-dynamic-component>
