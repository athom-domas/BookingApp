<x-filament-widgets::widget>
    @php $s = $this->getStats() @endphp
    <div class="grid grid-cols-2 {{ $s['showRevenue'] ? 'sm:grid-cols-4' : 'sm:grid-cols-3' }} gap-3">

        @if($s['showRevenue'])
            <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
                <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-indigo-400"></span>
                <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Incasso medio</p>
                <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none">
                    € {{ number_format($s['avgRevenue'], 2, ',', '.') }}
                </p>
                <p class="text-xs text-gray-400 pl-1 mt-0.5">per appuntamento</p>
            </div>
        @endif

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-teal-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Clienti unici</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none">
                {{ $s['uniqueCustomers'] }}
            </p>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-amber-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Servizio top</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white pl-1 leading-tight truncate">
                {{ $s['topServiceName'] }}
            </p>
            @if($s['topServiceCount'] > 0)
                <p class="text-xs text-gray-400 pl-1 mt-0.5">{{ $s['topServiceCount'] }} prenotazioni</p>
            @endif
        </div>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl {{ $s['pendingCount'] > 0 ? 'bg-amber-400' : 'bg-emerald-500' }}"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">In attesa</p>
            <p class="text-2xl font-bold tabular-nums pl-1 leading-none {{ $s['pendingCount'] > 0 ? 'text-amber-500' : 'text-gray-900 dark:text-white' }}">
                {{ $s['pendingCount'] }}
            </p>
        </div>

    </div>
</x-filament-widgets::widget>
