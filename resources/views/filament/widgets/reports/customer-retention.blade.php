<x-filament-widgets::widget>
    @php $s = $this->getStats() @endphp

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-700/50 p-4 flex flex-col gap-3">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Clienti nuovi vs. ricorrenti</p>

        @if($s['total'] === 0)
            <p class="text-sm text-gray-400 italic">Nessun dato per il periodo selezionato</p>
        @else
            {{-- Bar --}}
            <div class="flex rounded-full overflow-hidden h-3 bg-gray-100 dark:bg-gray-800 gap-px">
                @if($s['newPct'] > 0)
                    <div class="h-full rounded-l-full bg-indigo-400" style="width:{{ $s['newPct'] }}%"></div>
                @endif
                @if($s['returningPct'] > 0)
                    <div class="h-full {{ $s['newPct'] === 0 ? 'rounded-full' : 'rounded-r-full' }} bg-emerald-400" style="width:{{ $s['returningPct'] }}%"></div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="flex flex-col gap-0.5">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-400 flex-shrink-0"></span>
                        <span class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Nuovi</span>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-3.5 leading-none">{{ $s['new'] }}</p>
                    <p class="text-xs text-gray-400 pl-3.5">{{ $s['newPct'] }}% del totale</p>
                </div>

                <div class="flex flex-col gap-0.5">
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0"></span>
                        <span class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Ricorrenti</span>
                    </div>
                    <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-3.5 leading-none">{{ $s['returning'] }}</p>
                    <p class="text-xs text-gray-400 pl-3.5">{{ $s['returningPct'] }}% del totale</p>
                </div>
            </div>

            @if($s['avgReturnWeeks'] !== null)
                <div class="pt-2 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-[10px] font-semibold uppercase tracking-widest text-gray-400">Frequenza media di ritorno</span>
                    <p class="text-xl font-bold text-gray-900 dark:text-white mt-0.5">
                        {{ $s['avgReturnWeeks'] }} <span class="text-sm font-medium text-gray-400">sett.</span>
                    </p>
                </div>
            @endif
        @endif
    </div>
</x-filament-widgets::widget>
