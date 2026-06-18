<x-filament-widgets::widget>
    @php
        $s     = $this->getStats();
        $color = $s['rate'] >= 75 ? 'bg-emerald-500' : ($s['rate'] >= 45 ? 'bg-indigo-500' : 'bg-amber-500');
    @endphp

    <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
        <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl {{ $color }}"></span>

        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-3 pl-1">Tasso di occupazione</p>

        <div class="flex items-end gap-3 pl-1 mb-2">
            <p class="text-3xl font-bold tabular-nums text-gray-900 dark:text-white leading-none">{{ $s['rate'] }}%</p>
        </div>

        <div class="flex items-center gap-1 pl-1">
            <span class="text-sm font-semibold tabular-nums text-gray-700 dark:text-gray-300">{{ $s['bookedHours'] }}h</span>
            <span class="text-xs text-gray-400">prenotate su</span>
            <span class="text-sm font-semibold tabular-nums text-gray-700 dark:text-gray-300">{{ $s['availableHours'] }}h</span>
            <span class="text-xs text-gray-400">disponibili</span>
        </div>

        <p class="text-[11px] text-gray-400 pl-1 mt-2">Appt. attivi (escluse disdette)</p>
    </div>
</x-filament-widgets::widget>
