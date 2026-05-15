@extends('layouts.app')

@section('title', 'Nuova prenotazione')

@section('content')
    @php
        $servicesJson = $services->map(fn ($s) => [
            'id'          => $s->id,
            'name'        => $s->name,
            'description' => $s->description ?? '',
            'duration'    => $s->duration_minutes,
            'price'       => (float) $s->price,
            'staff_ids'   => $s->staff->pluck('id')->values()->all(),
        ])->values()->all();

        $staffJson = $staff->map(fn ($m) => [
            'id'          => $m->id,
            'name'        => $m->name,
            'service_ids' => $m->services->pluck('id')->values()->all(),
        ])->values()->all();
    @endphp

    <div class="mx-auto max-w-2xl space-y-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-gray-950">Nuova prenotazione</h1>
            <p class="text-sm text-gray-600">Completa i passi in ordine per prenotare il tuo appuntamento.</p>
        </div>

        <div
            x-data="bookingWizard({{ Illuminate\Support\Js::from($servicesJson) }}, {{ Illuminate\Support\Js::from($staffJson) }})"
            class="space-y-3"
        >
            {{-- CSRF + hidden inputs per il form submit --}}
            <form method="POST" action="{{ route('portal.bookings.store') }}" x-ref="bookingForm">
                @csrf
                <template x-for="id in selectedServiceIds" :key="id">
                    <input type="hidden" name="service_ids[]" :value="id">
                </template>
                <input type="hidden" name="staff_id" :value="staffId ?? ''">
                <input type="hidden" name="scheduled_date" :value="scheduledDateTime">
                <input type="hidden" name="payment_method" :value="paymentMethod ?? ''">
                <input type="hidden" name="notes" :value="notes">
            </form>

            {{-- Step 1: Servizi --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(1) && !isOpen(1) ? goTo(1) : null"
                    :class="isCompleted(1) && !isOpen(1) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(1) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(1)">1</span>
                            <svg x-show="isCompleted(1)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Scegli i servizi</p>
                            <p x-show="isCompleted(1) && !isOpen(1)" class="text-xs text-gray-500" x-text="servicesSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(1)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <template x-for="service in allServices" :key="service.id">
                            <button
                                type="button"
                                @click="toggleService(service.id)"
                                class="rounded-lg border p-4 text-left transition-colors"
                                :class="isSelectedService(service.id)
                                    ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-900" x-text="service.name"></span>
                                    <span class="shrink-0 rounded bg-blue-50 px-1.5 py-0.5 text-xs font-semibold text-blue-700"
                                          x-text="'€ ' + service.price.toFixed(2).replace('.', ',')"></span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500" x-text="service.description"></p>
                                <p class="mt-2 text-xs text-gray-400" x-text="service.duration + ' min'"></p>
                            </button>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <p x-show="selectedServiceIds.length > 0" class="text-xs text-gray-500"
                           x-text="'Totale: ' + totalDuration + ' min · € ' + totalPrice.toFixed(2).replace('.', ',')"></p>
                        <button
                            type="button"
                            @click="completeStep(1)"
                            :disabled="selectedServiceIds.length === 0"
                            class="ml-auto rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 2: Operatore --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(1) && !isOpen(2) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(2) && !isOpen(2) ? goTo(2) : null"
                    :class="isCompleted(2) && !isOpen(2) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(2) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(2)">2</span>
                            <svg x-show="isCompleted(2)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Scegli l'operatore</p>
                            <p x-show="isCompleted(2) && !isOpen(2)" class="text-xs text-gray-500" x-text="staffSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(2)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    <div class="space-y-2">
                        <button
                            type="button"
                            @click="staffId = null"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="staffId === null
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                        >
                            <p class="text-sm font-semibold text-gray-900">Qualsiasi operatore disponibile</p>
                            <p class="mt-0.5 text-xs text-gray-500">Il sistema assegnerà il miglior operatore libero</p>
                        </button>

                        <template x-for="member in filteredStaff" :key="member.id">
                            <button
                                type="button"
                                @click="staffId = member.id"
                                class="w-full rounded-lg border p-4 text-left transition-colors"
                                :class="staffId === member.id
                                    ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                            >
                                <p class="text-sm font-semibold text-gray-900" x-text="member.name"></p>
                            </button>
                        </template>

                        <p x-show="filteredStaff.length === 0" class="text-sm text-gray-500">
                            Nessun operatore disponibile per tutti i servizi selezionati.
                        </p>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(2)"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 3: Data e ora --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(2) && !isOpen(3) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(3) && !isOpen(3) ? goTo(3) : null"
                    :class="isCompleted(3) && !isOpen(3) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(3) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(3)">3</span>
                            <svg x-show="isCompleted(3)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Scegli data e ora</p>
                            <p x-show="isCompleted(3) && !isOpen(3)" class="text-xs text-gray-500" x-text="dateSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(3)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    <div class="mb-4 flex items-center justify-between">
                        <button type="button" @click="prevMonth()" class="rounded p-1 hover:bg-gray-100">
                            <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <p class="text-sm font-semibold text-gray-900" x-text="monthLabel"></p>
                        <button type="button" @click="nextMonth()" class="rounded p-1 hover:bg-gray-100">
                            <svg class="h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center">
                        <template x-for="d in ['Lu','Ma','Me','Gi','Ve','Sa','Do']">
                            <div class="py-1 text-xs font-medium text-gray-400" x-text="d"></div>
                        </template>
                        <template x-for="(cell, i) in calendarGrid" :key="i">
                            <div>
                                <template x-if="cell === null">
                                    <div></div>
                                </template>
                                <template x-if="cell !== null">
                                    <button
                                        type="button"
                                        @click="selectDate(cell)"
                                        :disabled="!isAvailableDate(cell)"
                                        class="w-full rounded-md py-1.5 text-sm transition-colors"
                                        :class="{
                                            'bg-blue-700 text-white font-semibold': date === cell,
                                            'hover:bg-blue-50 text-gray-900': isAvailableDate(cell) && date !== cell,
                                            'text-gray-300 cursor-not-allowed': !isAvailableDate(cell),
                                        }"
                                        x-text="cell.split('-')[2]"
                                    ></button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div x-show="loadingDates" class="mt-3 text-center text-xs text-gray-500">Caricamento disponibilità...</div>

                    <div x-show="date !== null" class="mt-4">
                        <p class="mb-2 text-xs font-medium text-gray-700">Orari disponibili</p>
                        <div x-show="loadingSlots" class="text-xs text-gray-500">Caricamento orari...</div>
                        <div x-show="!loadingSlots && availableSlots.length === 0 && date !== null" class="text-xs text-gray-500">
                            Nessun orario disponibile per questa data.
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="s in availableSlots" :key="s.start">
                                <button
                                    type="button"
                                    @click="slot = s.start"
                                    class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                    :class="slot === s.start
                                        ? 'border-blue-600 bg-blue-700 text-white'
                                        : 'border-gray-300 text-gray-700 hover:border-blue-400 hover:text-blue-700'"
                                    x-text="s.start"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(3)"
                            :disabled="!date || !slot"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Metodo di pagamento --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(3) && !isOpen(4) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(4) && !isOpen(4) ? goTo(4) : null"
                    :class="isCompleted(4) && !isOpen(4) ? 'cursor-pointer hover:bg-gray-50' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(4) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600'">
                            <span x-show="!isCompleted(4)">4</span>
                            <svg x-show="isCompleted(4)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Metodo di pagamento</p>
                            <p x-show="isCompleted(4) && !isOpen(4)" class="text-xs text-gray-500" x-text="paymentSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(4)" class="border-t border-gray-100 px-5 pb-5 pt-4">
                    <div class="space-y-3">
                        <button
                            type="button"
                            @click="paymentMethod = 'online'"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="paymentMethod === 'online'
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                        >
                            <p class="text-sm font-semibold text-gray-900">Paga ora</p>
                            <p class="mt-0.5 text-xs text-gray-500">Pagamento online con carta — la prenotazione viene confermata solo al completamento del pagamento</p>
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'in_salon'"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="paymentMethod === 'in_salon'
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                        >
                            <p class="text-sm font-semibold text-gray-900">Paga in salone</p>
                            <p class="mt-0.5 text-xs text-gray-500">Paghi direttamente al momento del servizio — la prenotazione è confermata subito</p>
                        </button>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(4)"
                            :disabled="paymentMethod === null"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 5: Riepilogo e conferma --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                 :class="!isCompleted(4) && !isOpen(5) ? 'opacity-50' : ''">
                <div class="flex items-center gap-3 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">5</span>
                    <p class="text-sm font-semibold text-gray-900">Riepilogo e conferma</p>
                </div>
                <div x-show="isOpen(5)" class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4">
                    @auth
                        <dl class="space-y-2 rounded-lg bg-gray-50 p-4 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Servizi</dt>
                                <dd class="font-medium text-gray-900" x-text="selectedServiceIds.map(id => serviceById(id)?.name).join(', ')"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Operatore</dt>
                                <dd class="font-medium text-gray-900" x-text="staffId ? (allStaff.find(s => s.id === staffId)?.name ?? '—') : 'Qualsiasi operatore'"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Data e ora</dt>
                                <dd class="font-medium text-gray-900" x-text="dateSummary"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Durata</dt>
                                <dd class="font-medium text-gray-900" x-text="totalDuration + ' min'"></dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2">
                                <dt class="font-semibold text-gray-900">Totale</dt>
                                <dd class="font-bold text-gray-900" x-text="'€ ' + totalPrice.toFixed(2).replace('.', ',')"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Pagamento</dt>
                                <dd class="font-medium text-gray-900" x-text="paymentSummary"></dd>
                            </div>
                        </dl>

                        <div>
                            <label class="block text-sm font-medium text-gray-900">Note (opzionale)</label>
                            <textarea
                                x-model="notes"
                                rows="3"
                                maxlength="1000"
                                class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
                            ></textarea>
                        </div>

                        <button
                            type="button"
                            @click="$refs.bookingForm.submit()"
                            class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800"
                            x-text="paymentMethod === 'online' ? 'Prenota e vai al pagamento' : 'Conferma prenotazione'"
                        ></button>
                    @else
                        <p class="text-sm text-gray-600">Accedi o crea un account per completare la prenotazione.</p>
                        <div class="flex gap-3">
                            <a href="{{ route('login') }}" class="flex-1 rounded-md border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-900 hover:bg-gray-50">Accedi</a>
                            <a href="{{ route('register') }}" class="flex-1 rounded-md bg-blue-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-800">Crea account</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </div>
@endsection
