<x-filament-widgets::widget>
    <x-filament::section heading="Performance Staff">
        @php $rows = $this->getRows() @endphp

        @if($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Nessun dato per il periodo selezionato.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-6 font-medium">Staff</th>
                            <th class="py-2 pr-6 font-medium text-right">Appuntamenti</th>
                            <th class="py-2 pr-6 font-medium text-right">Incasso</th>
                            <th class="py-2 pr-6 font-medium text-right">% Cancellazione</th>
                            <th class="py-2 font-medium">Servizio top</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-6 font-medium text-gray-900 dark:text-gray-100">{{ $row->name }}</td>
                                <td class="py-2 pr-6 text-right text-gray-700 dark:text-gray-300">{{ $row->total }}</td>
                                <td class="py-2 pr-6 text-right text-gray-700 dark:text-gray-300">
                                    € {{ number_format((float) $row->revenue, 2, ',', '.') }}
                                </td>
                                <td class="py-2 pr-6 text-right">
                                    <span class="{{ $row->cancellation_rate > 20 ? 'text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                        {{ $row->cancellation_rate }}%
                                    </span>
                                </td>
                                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $row->top_service }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
