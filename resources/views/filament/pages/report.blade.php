<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3 mb-6 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
        <div class="flex items-center gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
            @foreach (['today' => 'Oggi', 'week' => 'Settimana', 'month' => 'Mese', 'year' => 'Anno'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-150
                        {{ $period === $key
                            ? 'bg-white dark:bg-gray-700 text-primary-600 dark:text-primary-400 shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 ml-auto">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
            </svg>
            <input
                type="date"
                wire:model.live="dateFrom"
                class="px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent text-gray-700 dark:text-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
            <span class="text-gray-400">—</span>
            <input
                type="date"
                wire:model.live="dateTo"
                class="px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-transparent text-gray-700 dark:text-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
        </div>
    </div>

    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :data="$this->getWidgetData()"
    />
</x-filament-panels::page>
