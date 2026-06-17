<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-end mt-6">
            <x-filament::button type="submit">
                Salva disponibilità
            </x-filament::button>
        </div>
    </form>

    <div class="mt-10">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Periodi di assenza</h3>

        @if(count($this->blockouts))
        <div class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
            @foreach($this->blockouts as $blockout)
            <div class="flex items-center justify-between px-4 py-3">
                <div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $blockout['start_date'] }} — {{ $blockout['end_date'] }}</span>
                    @if($blockout['reason'])
                    <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">· {{ $blockout['reason'] }}</span>
                    @endif
                </div>
                <button wire:click="deleteBlockout({{ $blockout['id'] }})"
                    wire:confirm="Eliminare questo periodo di assenza?"
                    class="text-sm text-red-600 dark:text-red-400 hover:underline ml-4">
                    Elimina
                </button>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Nessun periodo di assenza impostato.</p>
        @endif

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Aggiungi periodo</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Dal</label>
                    <input type="date" wire:model="newStart"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('newStart') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Al</label>
                    <input type="date" wire:model="newEnd"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('newEnd') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Motivo (opzionale)</label>
                    <input type="text" wire:model="newReason" placeholder="es. Ferie agosto"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            <div class="mt-3">
                <x-filament::button wire:click="addBlockout" size="sm">
                    Aggiungi periodo
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
