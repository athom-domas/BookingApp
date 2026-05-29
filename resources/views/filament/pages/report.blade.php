<x-filament-panels::page>
    <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-5">
        <div class="flex items-center gap-0.5 p-0.5 bg-gray-100 dark:bg-gray-800 rounded-lg self-start sm:self-auto">
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

        <div class="flex items-center gap-1.5 sm:ml-auto">
            <input
                type="date"
                wire:model.live="dateFrom"
                class="flex-1 sm:flex-none px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
            <span class="text-gray-400 text-xs flex-shrink-0">—</span>
            <input
                type="date"
                wire:model.live="dateTo"
                class="flex-1 sm:flex-none px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
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
    </style>
</x-filament-panels::page>
