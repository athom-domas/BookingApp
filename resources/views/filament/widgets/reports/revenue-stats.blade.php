<x-filament-widgets::widget>
    @php $s = $this->getStats() @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-indigo-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Incasso</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none">
                € {{ number_format($s['totalRevenue'], 2, ',', '.') }}
            </p>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-sky-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Appuntamenti</p>
            <p class="text-2xl font-bold tabular-nums text-gray-900 dark:text-white pl-1 leading-none">
                {{ $s['totalAppointments'] }}
            </p>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl {{ $s['cancellationRate'] > 20 ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Cancellazioni</p>
            <p class="text-2xl font-bold tabular-nums pl-1 leading-none {{ $s['cancellationRate'] > 20 ? 'text-rose-500' : 'text-gray-900 dark:text-white' }}">
                {{ $s['cancellationRate'] }}%
            </p>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 dark:border-gray-700/50 bg-white dark:bg-gray-900 p-4">
            <span class="absolute inset-y-0 left-0 w-[3px] rounded-l-xl bg-violet-500"></span>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-2 pl-1">Staff top</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white pl-1 leading-tight truncate">
                {{ $s['topStaffName'] }}
            </p>
            @if($s['topStaffCount'] > 0)
                <p class="text-xs text-gray-400 pl-1 mt-0.5">{{ $s['topStaffCount'] }} completati</p>
            @endif
        </div>

    </div>
</x-filament-widgets::widget>
