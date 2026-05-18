<x-filament-panels::page>
    @if(auth()->user()->isAdmin())
        <div class="mb-4">
            {{ $this->staffFilterForm }}
        </div>
    @endif

    <x-filament-widgets::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="1"
    />
</x-filament-panels::page>
