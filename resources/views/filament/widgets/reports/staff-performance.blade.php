<x-filament-widgets::widget>
    <x-filament::section heading="Performance Staff">
        @php $rows = $this->getRows() @endphp

        @if($rows->isEmpty())
            <p class="text-sm text-gray-400">Nessun dato per il periodo selezionato.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <th class="pb-2.5 pr-3 text-left w-6">#</th>
                            <th class="pb-2.5 pr-4 text-left">Staff</th>
                            <th class="pb-2.5 pr-4 text-right">Appuntamenti</th>
                            <th class="pb-2.5 pr-4 text-right">Incasso</th>
                            <th class="pb-2.5 pr-4 text-right">Canc.</th>
                            <th class="pb-2.5 text-left">Servizio top</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800/60">
                        @foreach($rows as $i => $row)
                            <tr>
                                <td class="py-2.5 pr-3">
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold
                                        {{ $i === 0 ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                                        {{ $i + 1 }}
                                    </span>
                                </td>
                                <td class="py-2.5 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row->name }}</td>
                                <td class="py-2.5 pr-4 text-right tabular-nums text-gray-600 dark:text-gray-300">{{ $row->total }}</td>
                                <td class="py-2.5 pr-4 text-right tabular-nums font-semibold text-gray-900 dark:text-white">
                                    € {{ number_format((float) $row->revenue, 2, ',', '.') }}
                                </td>
                                <td class="py-2.5 pr-4 text-right">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ $row->cancellation_rate > 20
                                            ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                        {{ $row->cancellation_rate }}%
                                    </span>
                                </td>
                                <td class="py-2.5 text-gray-500 dark:text-gray-400 truncate max-w-[160px]">{{ $row->top_service }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
