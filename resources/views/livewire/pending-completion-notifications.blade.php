<div>
@if (auth()->user()?->isAdmin())
    <div x-data="{ open: false }" class="relative">
        <button
            type="button"
            @click="open = !open"
            class="relative flex h-9 w-9 items-center justify-center rounded-full text-gray-500 transition-colors hover:bg-gray-100 focus:outline-none dark:text-gray-400 dark:hover:bg-white/10"
            aria-label="Notifiche appuntamenti"
        >
            <x-filament::icon icon="heroicon-o-bell" class="h-5 w-5" />

            @if ($this->pendingCount > 0)
                <span class="pointer-events-none absolute -top-1 -right-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-md bg-red-500 px-1 text-[11px] font-bold leading-none text-white ring-2 ring-white dark:ring-gray-900">
                    {{ $this->pendingCount > 9 ? '9+' : $this->pendingCount }}
                </span>
            @endif
        </button>

        <div
            x-show="open"
            x-transition
            @click.away="open = false"
            class="absolute right-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-white/10 dark:bg-gray-900"
            style="display: none"
        >
            <div class="border-b border-gray-100 px-3 py-2.5 dark:border-white/10">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Appuntamenti da completare</p>
            </div>

            @if ($this->pendingAppointments->isEmpty())
                <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-7 w-7 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm text-gray-400 dark:text-gray-500">Tutto in ordine</p>
                </div>
            @else
                <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                    @foreach ($this->pendingAppointments as $appointment)
                        <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $appointment->user->name }}
                                </p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $appointment->services_label }}
                                </p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $appointment->scheduled_date->format('d/m/Y H:i') }} → {{ $appointment->end_time->format('H:i') }}
                                </p>
                            </div>
                            <a
                                href="{{ \App\Filament\Resources\AppointmentResource::getUrl('edit', ['record' => $appointment->id]) }}"
                                @click="open = false"
                                class="shrink-0 rounded-md bg-blue-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-blue-700"
                            >
                                Visualizza
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
</div>
