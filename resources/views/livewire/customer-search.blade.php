<div>
    @if (auth()->user()?->isAdmin() || auth()->user()?->isStaff())
        <div
            x-data="{ open: false, mobileOpen: false }"
            @keydown.ctrl.k.window.prevent="mobileOpen = true; $nextTick(() => ($refs.searchInputMobile ?? $refs.searchInput)?.focus())"
            @keydown.escape.window="open = false; mobileOpen = false"
            class="relative"
        >
            {{-- ── Desktop ─────────────────────────────────────────────── --}}
            <div class="hidden sm:block w-60" @click.outside="open = false">
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

                {{-- Desktop results --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute right-0 top-full z-50 mt-2 w-[500px] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-gray-900"
                    style="display: none"
                >
                    @include('livewire.partials.customer-search-results')
                </div>
            </div>

            {{-- ── Mobile: icon button ────────────────────────────────── --}}
            <button
                @click="mobileOpen = !mobileOpen; if (mobileOpen) $nextTick(() => $refs.searchInputMobile?.focus())"
                class="sm:hidden rounded-lg p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"
                aria-label="Cerca cliente"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </button>

            {{-- ── Mobile: panel (input + risultati inline) ───────────── --}}
            <div
                x-show="mobileOpen"
                x-cloak
                @click.outside="mobileOpen = false; open = false"
                class="sm:hidden fixed inset-x-3 top-[3.75rem] z-50 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-gray-900"
                style="display: none"
            >
                <div class="p-2">
                    <x-filament::input.wrapper
                        prefix-icon="heroicon-o-magnifying-glass"
                        inline-prefix
                        wire:target="query"
                    >
                        <input
                            x-ref="searchInputMobile"
                            wire:model.live.debounce.300ms="query"
                            @input="open = $event.target.value.length >= 2"
                            type="search"
                            placeholder="Cerca cliente..."
                            autocomplete="off"
                            class="fi-input fi-input-has-inline-prefix"
                        />
                    </x-filament::input.wrapper>
                </div>

                <div x-show="open" style="display: none">
                    @include('livewire.partials.customer-search-results')
                </div>
            </div>
        </div>
    @endif
</div>
