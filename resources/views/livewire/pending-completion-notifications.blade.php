<div x-data="{ open: false }" class="relative flex items-center" wire:poll.60s>
    <button
        @click="open = !open"
        class="relative flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:hover:text-gray-300 dark:hover:bg-white/5 transition"
        :class="open ? 'text-gray-600 bg-gray-100 dark:text-gray-300 dark:bg-white/5' : ''"
    >
        <x-heroicon-o-bell class="w-5 h-5" />
        @if ($this->pendingCount > 0)
            <span class="absolute top-0.5 right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-danger-500 text-[10px] font-bold text-white leading-none">
                {{ $this->pendingCount > 9 ? '9+' : $this->pendingCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 top-full mt-2 w-80 z-50 rounded-xl shadow-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 overflow-hidden"
        style="display: none"
    >
        <div class="px-4 py-3 border-b border-gray-100 dark:border-white/10">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Da completare</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Appuntamenti terminati in attesa di conferma</p>
        </div>

        @if ($this->pendingAppointments->isEmpty())
            <div class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                Nessun appuntamento da completare
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/5 max-h-96 overflow-y-auto">
                @foreach ($this->pendingAppointments as $appointment)
                    <div class="px-4 py-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $appointment->user->name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                    {{ $appointment->services_label }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    {{ $appointment->scheduled_date->format('d/m/Y H:i') }} → {{ $appointment->end_time->format('H:i') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <button
                                wire:click="openCompleteModal({{ $appointment->id }})"
                                class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-success-50 text-success-700 hover:bg-success-100 dark:bg-success-500/10 dark:text-success-400 dark:hover:bg-success-500/20 transition"
                            >
                                Completa
                            </button>
                            <button
                                @click="open = false"
                                class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-white/5 transition"
                            >
                                Lascia com'è
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</div>
