<div>
    @if (auth()->user()?->isAdmin() || auth()->user()?->isStaff())
        <div
            x-data="{ open: false }"
            @keydown.ctrl.k.window.prevent="$refs.searchInput.focus()"
            @keydown.escape.window="open = false"
            @click.outside="open = false"
            class="relative"
        >
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    x-ref="searchInput"
                    wire:model.live.debounce.300ms="query"
                    @input="open = $event.target.value.length >= 2"
                    type="text"
                    placeholder="Cerca cliente... (Ctrl+K)"
                    autocomplete="off"
                    class="block w-52 rounded-lg border border-gray-200 bg-white py-1.5 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-500"
                />
            </div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="absolute right-0 top-full z-50 mt-2 w-96 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900"
                style="display: none"
            >
                <div wire:loading wire:target="query" class="flex items-center justify-center py-6">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                </div>

                <div wire:loading.remove wire:target="query">
                    @if ($this->results->isEmpty())
                        <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                            <x-filament::icon icon="heroicon-o-users" class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                            <p class="text-sm text-gray-400 dark:text-gray-500">Nessun cliente trovato</p>
                        </div>
                    @else
                        <div class="max-h-96 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                            @foreach ($this->results as $customer)
                                <div class="px-3 py-2.5">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $customer->name }}</p>
                                    <p class="mb-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $customer->email }}</p>

                                    @if ($customer->appointmentsAsCustomer->isEmpty())
                                        <p class="text-xs text-gray-400 dark:text-gray-500">Nessun appuntamento</p>
                                    @else
                                        <div class="space-y-0.5">
                                            @foreach ($customer->appointmentsAsCustomer as $appointment)
                                                @php
                                                    $statusConfig = match($appointment->status) {
                                                        'pending'   => ['label' => 'In attesa',   'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400'],
                                                        'confirmed' => ['label' => 'Confermato',  'class' => 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'],
                                                        'completed' => ['label' => 'Completato',  'class' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400'],
                                                        'cancelled' => ['label' => 'Annullato',   'class' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400'],
                                                        default     => ['label' => $appointment->status, 'class' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400'],
                                                    };
                                                @endphp
                                                <a
                                                    href="{{ \App\Filament\Resources\AppointmentResource::getUrl('edit', ['record' => $appointment->id]) }}"
                                                    @click="open = false"
                                                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                                                >
                                                    <span class="shrink-0 font-medium text-gray-700 dark:text-gray-300">
                                                        {{ $appointment->scheduled_date->format('d/m/Y H:i') }}
                                                    </span>
                                                    <span class="min-w-0 flex-1 truncate text-gray-500 dark:text-gray-400">
                                                        {{ $appointment->services_label_preloaded }}
                                                    </span>
                                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold {{ $statusConfig['class'] }}">
                                                        {{ $statusConfig['label'] }}
                                                    </span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
