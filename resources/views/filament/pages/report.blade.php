<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3 mb-6">
        <div class="flex gap-2">
            @foreach (['today' => 'Oggi', 'week' => 'Settimana', 'month' => 'Mese', 'year' => 'Anno'] as $key => $label)
                <button
                    wire:click="setPeriod('{{ $key }}')"
                    class="px-3 py-1.5 text-sm rounded-lg border transition-colors
                        {{ $period === $key
                            ? 'bg-primary-600 text-white border-primary-600'
                            : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <input
                type="date"
                wire:model.live="dateFrom"
                class="px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm"
            >
            <span>→</span>
            <input
                type="date"
                wire:model.live="dateTo"
                class="px-2 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm"
            >
        </div>
    </div>

    <x-filament-widgets::widgets
        :widgets="$this->getWidgets()"
        :data="$this->getWidgetData()"
    />
</x-filament-panels::page>
