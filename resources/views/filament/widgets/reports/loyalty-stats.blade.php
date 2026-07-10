<x-filament-widgets::widget>
    @php $s = $this->getStats() @endphp

    @if(! $s['enabled'])
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-700/50 p-4">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Programma fedeltà</p>
            <p class="text-sm text-gray-400 italic mt-2">Programma fedeltà non attivato</p>
        </div>
    @else
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-700/50 p-4 flex flex-col gap-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Programma fedeltà</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="relative overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800/50 p-3">
                    <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-lg bg-violet-500"></span>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 pl-1">Membri attivi</p>
                    <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none mt-1">
                        {{ $s['activeMembers'] }}
                    </p>
                    <p class="text-xs text-gray-400 pl-1 mt-0.5">con punti > 0</p>
                </div>

                <div class="relative overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800/50 p-3">
                    <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-lg bg-emerald-500"></span>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 pl-1">Punti accumulati</p>
                    <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none mt-1">
                        {{ number_format($s['earnTotal'], 0, ',', '.') }}
                    </p>
                    @if($s['reverseTotal'] > 0)
                        <p class="text-xs text-gray-400 pl-1 mt-0.5">{{ $s['reverseTotal'] }} stornati</p>
                    @endif
                </div>

                <div class="relative overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800/50 p-3">
                    <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-lg bg-amber-500"></span>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 pl-1">Punti riscattati</p>
                    <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none mt-1">
                        {{ number_format($s['redeemTotal'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="relative overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800/50 p-3">
                    <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-lg bg-indigo-500"></span>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 pl-1">Sconti applicati</p>
                    <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none mt-1">
                        € {{ number_format($s['discountTotal'], 2, ',', '.') }}
                    </p>
                    @if($s['discountCount'] > 0)
                        <p class="text-xs text-gray-400 pl-1 mt-0.5">{{ $s['discountCount'] }} prenotazioni</p>
                    @endif
                </div>
            </div>

            @if($s['hasTiers'] && count($s['tierBreakdown']) > 0)
                <div class="pt-3 border-t border-gray-100 dark:border-gray-800">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2">Distribuzione tier</p>
                    @php
                        $totalMembers = collect($s['tierBreakdown'])->sum('count');
                    @endphp
                    @if($totalMembers > 0)
                        <div class="flex rounded-full overflow-hidden h-3 bg-gray-100 dark:bg-gray-800 gap-px">
                            @php $colors = ['bg-violet-400', 'bg-indigo-400', 'bg-blue-400', 'bg-cyan-400', 'bg-emerald-400', 'bg-amber-400'] @endphp
                            @foreach($s['tierBreakdown'] as $i => $tier)
                                @if($tier['count'] > 0)
                                    <div class="h-full {{ $loop->first ? 'rounded-l-full' : '' }} {{ $loop->last ? 'rounded-r-full' : '' }} {{ $colors[$i % count($colors)] }}"
                                         style="width:{{ round($tier['count'] / $totalMembers * 100) }}%"></div>
                                @endif
                            @endforeach
                        </div>
                        <div class="grid grid-cols-{{ min(count($s['tierBreakdown']), 4) }} gap-2 mt-2">
                            @foreach($s['tierBreakdown'] as $i => $tier)
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full {{ $colors[$i % count($colors)] }} flex-shrink-0"></span>
                                    <span class="text-xs text-gray-600 dark:text-gray-300 truncate">
                                        {{ $tier['label'] }}
                                        <span class="font-semibold">{{ $tier['count'] }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 italic">Nessun membro con punti</p>
                    @endif
                </div>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
