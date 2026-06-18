<x-filament-panels::page>
    {{-- Toolbar: periodo + date picker + export --}}
    <div class="flex flex-col gap-2 mb-5">
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-0.5 p-0.5 bg-gray-100 dark:bg-gray-800 rounded-lg">
                @foreach (['today' => 'Oggi', 'week' => 'Sett.', 'month' => 'Mese', 'year' => 'Anno'] as $key => $label)
                    <button
                        wire:click="setPeriod('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-all duration-150
                            {{ $period === $key
                                ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-1.5 ml-auto">
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    class="w-32 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                >
                <span class="text-gray-400 text-xs flex-shrink-0">—</span>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    class="w-32 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                >
            </div>
        </div>

        <div class="flex justify-end">
            <button
                wire:click="exportCsv"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-medium hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-60"
            >
                <svg wire:loading.remove wire:target="exportCsv" class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <svg wire:loading wire:target="exportCsv" class="w-3.5 h-3.5 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span wire:loading.remove wire:target="exportCsv">Esporta CSV</span>
                <span wire:loading wire:target="exportCsv">Preparando...</span>
            </button>
        </div>
    </div>

    <div id="report-widgets">
        <x-filament-widgets::widgets
            :widgets="$this->getWidgets()"
            :data="$this->getWidgetData()"
            :columns="2"
        />
    </div>

    <style>
        #report-widgets .fi-section {
            box-shadow: none !important;
            --tw-ring-shadow: 0 0 #0000 !important;
            --tw-shadow: 0 0 #0000 !important;
        }
        /* Uniform card height for side-by-side half-width widgets */
        #report-widgets .fi-wi {
            height: 100%;
        }
    </style>
</x-filament-panels::page>
