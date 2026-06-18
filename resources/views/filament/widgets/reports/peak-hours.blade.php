<x-filament-widgets::widget>
    @php
        $data     = $this->getHeatmap();
        $matrix   = $data['matrix'];
        $maxCount = $data['maxCount'] ?: 1;
        $days     = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab', 7 => 'Dom'];
        $hours    = range(7, 20);
    @endphp

    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-700/50 p-4">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-3">Orari e giorni di punta</p>

        <div class="overflow-x-auto -mx-4 px-4">
            <table class="w-full text-xs" style="min-width: 480px">
                <thead>
                    <tr>
                        <th class="w-9 pr-2"></th>
                        @foreach($hours as $h)
                            <th class="text-center text-[10px] font-normal text-gray-400 pb-1.5 px-0.5">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($days as $dow => $dayLabel)
                        <tr>
                            <td class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 pr-2 text-right whitespace-nowrap py-0.5">{{ $dayLabel }}</td>
                            @foreach($hours as $h)
                                @php
                                    $cnt     = $matrix[$dow][$h] ?? 0;
                                    $opacity = $cnt > 0 ? round(max(0.12, $cnt / $maxCount), 2) : 0;
                                @endphp
                                <td class="p-0.5">
                                    <div
                                        class="w-full rounded flex items-center justify-center font-semibold leading-none"
                                        style="height:26px; background:rgba(99,102,241,{{ $opacity }});"
                                        title="{{ $cnt > 0 ? $cnt . ' appt.' : '' }}"
                                    >
                                        @if($cnt > 0)
                                            <span style="color:rgba(55,48,163,{{ min(1, $opacity * 2) }});" class="text-[10px]">{{ $cnt }}</span>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="text-[10px] text-gray-400 mt-2">Tutti gli appuntamenti del periodo selezionato</p>
    </div>
</x-filament-widgets::widget>
