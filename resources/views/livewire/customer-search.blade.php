<div>
    @if (auth()->user()?->isAdmin() || auth()->user()?->isStaff())
        <div
            x-data="{ open: false }"
            @keydown.ctrl.k.window.prevent="$refs.searchInput.focus()"
            @keydown.escape.window="open = false"
            @click.outside="open = false"
            class="relative"
        >
            <div class="w-60">
                <x-filament::input.wrapper
                    prefix-icon="heroicon-o-magnifying-glass"
                    inline-prefix
                    wire:target="query"
                >
                    <input
                        x-ref="searchInput"
                        wire:model.live.debounce.300ms="query"
                        @input="open = $event.target.value.length >= 2"
                        type="search"
                        placeholder="Cerca cliente (Ctrl+K)"
                        autocomplete="off"
                        class="fi-input fi-input-has-inline-prefix"
                    />
                </x-filament::input.wrapper>
            </div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="absolute right-0 top-full z-50 mt-2 w-[500px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-gray-900"
                style="display: none"
            >
                <div wire:loading wire:target="query" class="flex items-center justify-center py-8">
                    <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
                </div>

                <div wire:loading.remove wire:target="query">
                    @if ($this->results->isEmpty())
                        <div class="flex flex-col items-center gap-2 px-4 py-10 text-center">
                            <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                            <p class="text-sm text-gray-400 dark:text-gray-500">Nessun cliente trovato</p>
                        </div>
                    @else
                        <div class="max-h-[450px] divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                            @foreach ($this->results as $customer)
                                <div>
                                    <div class="bg-gray-50 px-4 py-2.5 dark:bg-white/5">
                                        <p class="text-sm font-semibold leading-tight text-gray-900 dark:text-white">{{ $customer->name }}</p>
                                        <p class="text-xs leading-snug text-gray-400 dark:text-gray-500">{{ $customer->email }}</p>
                                    </div>

                                    @if ($customer->appointmentsAsCustomer->isEmpty())
                                        <p class="px-4 py-2.5 text-xs text-gray-400 dark:text-gray-500">Nessun appuntamento</p>
                                    @else
                                        <div>
                                            @foreach ($customer->appointmentsAsCustomer as $appointment)
                                                @php
                                                    $statusConfig = match($appointment->status) {
                                                        'pending'   => ['label' => 'In attesa',  'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400'],
                                                        'confirmed' => ['label' => 'Confermato', 'class' => 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400'],
                                                        'completed' => ['label' => 'Completato', 'class' => 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400'],
                                                        'cancelled' => ['label' => 'Annullato',  'class' => 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400'],
                                                        default     => ['label' => $appointment->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400'],
                                                    };
                                                @endphp
                                                <a
                                                    href="{{ \App\Filament\Resources\AppointmentResource::getUrl('edit', ['record' => $appointment->id]) }}"
                                                    @click="open = false"
                                                    class="flex items-center gap-3 px-4 py-2 transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                                                >
                                                    <span class="w-28 shrink-0 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                                        {{ $appointment->scheduled_date->format('d/m/Y H:i') }}
                                                    </span>
                                                    <span class="min-w-0 flex-1 truncate text-xs text-gray-700 dark:text-gray-300">
                                                        {{ $appointment->services_label_preloaded ?: '—' }}
                                                    </span>
                                                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $statusConfig['class'] }}">
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
