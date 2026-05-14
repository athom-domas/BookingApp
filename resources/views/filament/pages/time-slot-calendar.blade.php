<x-filament-panels::page>
    <style>
        .slot-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 0.5rem; }
        .slot-day { min-height: 120px; }
        .slot-week-label { min-width: 150px; }
        .slot-available { background-color: #dcfce7; color: #166534; }
        .slot-occupied  { background-color: #fee2e2; color: #991b1b; }
        @media (prefers-color-scheme: dark) {
            .slot-available { background-color: rgba(20,83,45,.4); color: #86efac; }
            .slot-occupied  { background-color: rgba(127,29,29,.4); color: #fca5a5; }
        }
    </style>
    <div class="space-y-4">

        {{-- Controls --}}
        <div class="flex flex-wrap items-center gap-3">
            <select
                wire:model.live="staffId"
                class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500 py-1.5 px-3"
            >
                <option value="">Seleziona staff...</option>
                @foreach ($this->staffOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-1">
                <button
                    type="button"
                    wire:click="previousWeek"
                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400"
                    title="Settimana precedente"
                >
                    <x-heroicon-o-chevron-left class="w-5 h-5" />
                </button>

                <span class="slot-week-label text-sm font-medium text-center text-gray-700 dark:text-gray-300">
                    {{ $this->weekLabel }}
                </span>

                <button
                    type="button"
                    wire:click="nextWeek"
                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400"
                    title="Settimana successiva"
                >
                    <x-heroicon-o-chevron-right class="w-5 h-5" />
                </button>
            </div>
        </div>

        @if (! $staffId)
            <div class="flex items-center justify-center py-16 text-gray-400 dark:text-gray-500 text-sm">
                Seleziona uno staff per vedere il calendario.
            </div>
        @else
            {{-- Calendar grid --}}
            <div class="slot-grid">
                @foreach ($this->weekDays as $day)
                    @php
                        $key = $day->format('Y-m-d');
                        $daySlots = $this->slots->get($key, collect());
                    @endphp
                    <div class="slot-day rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 space-y-1">
                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 capitalize">
                            {{ $day->isoFormat('ddd D MMM') }}
                        </div>

                        @forelse ($daySlots as $slot)
                            @php
                                $available = $slot->is_available && is_null($slot->appointment_id);
                            @endphp
                            <div class="rounded px-1.5 py-0.5 text-xs font-mono leading-tight {{ $available ? 'slot-available' : 'slot-occupied' }}">
                                {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}
                            </div>
                        @empty
                            <span class="text-gray-300 dark:text-gray-600 text-sm">—</span>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</x-filament-panels::page>
