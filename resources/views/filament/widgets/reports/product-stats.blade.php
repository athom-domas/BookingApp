<x-filament-widgets::widget>
    @php $s = $this->getStats() @endphp
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-emerald-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Vendite prodotti</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none">
                € {{ number_format($s['revenue'], 2, ',', '.') }}
            </p>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-sky-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Ordini prodotti</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none">
                {{ $s['orderCount'] }}
            </p>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-violet-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Prodotto più venduto</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white pl-1 leading-tight truncate">
                {{ $s['topProductName'] }}
            </p>
            @if($s['topProductQty'] > 0)
                <p class="text-xs text-gray-400 pl-1 mt-0.5">{{ $s['topProductQty'] }} pezzi</p>
            @endif
        </div>

    </div>
</x-filament-widgets::widget>
