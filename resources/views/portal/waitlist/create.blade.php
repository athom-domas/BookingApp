@extends('layouts.app')

@section('title', 'Iscriviti alla lista d\'attesa')

@push('head')
<style>
    .opt-selected {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 1px var(--color-primary);
        background-color: rgba(0,0,0,0.03);
    }
    .dark .opt-selected {
        border-color: rgba(255,255,255,0.35);
        box-shadow: 0 0 0 1px rgba(255,255,255,0.35);
        background-color: rgba(255,255,255,0.04);
    }
    .step-done { background-color: var(--color-primary) !important; }
    .cal-active { background-color: var(--color-primary); color: #fff; font-weight: 600; }
    .btn-wl {
        background-color: var(--color-primary);
        color: #fff;
        font-size: .875rem;
        font-weight: 600;
        padding: .5rem 1rem;
        border-radius: .375rem;
        transition: filter .15s;
    }
    .btn-wl:hover { filter: brightness(0.85); }
    .btn-wl:disabled { opacity: .4; cursor: not-allowed; }
    .btn-wl-full {
        background-color: var(--color-primary);
        color: #fff;
        font-size: .875rem;
        font-weight: 600;
        padding: .75rem 1rem;
        border-radius: .375rem;
        width: 100%;
        transition: filter .15s;
    }
    .btn-wl-full:hover { filter: brightness(0.85); }
</style>
@endpush

@section('content')
    @php
        $oldServices = array_map('intval', (array) old('service_ids', $prefilledServiceIds));

        $oldStaffRaw = old('preferred_staff_id');
        if ($oldStaffRaw !== null) {
            $initStaff = $oldStaffRaw === '' ? null : (int) $oldStaffRaw;
        } else {
            $initStaff = $prefilledStaffId;
        }

        $hasOld = old('service_ids') !== null;

        $oldDates        = $hasOld ? array_values((array) old('preferred_days', [])) : $prefilledDays;
        $defaultTimeFrom = $prefilledTimeFrom ?: '09:00';
        $defaultTimeTo   = $prefilledTimeTo   ?: '18:00';

        $timeOptions = [];
        for ($h = 7; $h <= 21; $h++) {
            $timeOptions[sprintf('%02d:00', $h)] = sprintf('%02d:00', $h);
            if ($h < 21) {
                $timeOptions[sprintf('%02d:30', $h)] = sprintf('%02d:30', $h);
            }
        }

        $hasPrefill      = count($prefilledServiceIds) > 0;
        $hasPrefillDates = count($prefilledDays) > 0;

        if ($hasOld) {
            if ($errors->hasAny(['service_ids', 'service_ids.*'])) {
                $initialStep = 1; $initialCompleted = [];
            } elseif ($errors->has('preferred_staff_id')) {
                $initialStep = 2; $initialCompleted = [1];
            } elseif ($errors->hasAny(['preferred_days', 'preferred_days.*'])) {
                $initialStep = 3; $initialCompleted = [1, 2];
            } else {
                $initialStep = 4; $initialCompleted = [1, 2, 3];
            }
        } elseif ($hasPrefill && $hasPrefillDates) {
            $initialStep = 5; $initialCompleted = [1, 2, 3, 4];
        } elseif ($hasPrefill) {
            $initialStep = 3; $initialCompleted = [1, 2];
        } else {
            $initialStep = 1; $initialCompleted = [];
        }
    @endphp

    <div class="mx-auto max-w-2xl space-y-4">
        <div class="space-y-1">
            <h1 class="font-display text-2xl font-semibold text-gray-950 dark:text-gray-50">Lista d'attesa</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Ti notificheremo quando si libera uno slot compatibile con le tue preferenze.</p>
        </div>

        <div
            x-data="{
                selectedServices: {{ Illuminate\Support\Js::from($oldServices) }},
                servicesData: {{ Illuminate\Support\Js::from($services->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()) }},
                staffId: {{ Illuminate\Support\Js::from($initStaff) }},
                staffData: {{ Illuminate\Support\Js::from($staff->mapWithKeys(fn ($m) => [$m->id => $m->name])->all()) }},
                selectedDates: {{ Illuminate\Support\Js::from($oldDates) }},
                openDayNums: {{ Illuminate\Support\Js::from($openDayNums) }},
                timeFrom: '{{ old('preferred_time_from', $defaultTimeFrom) }}',
                timeTo: '{{ old('preferred_time_to', $defaultTimeTo) }}',
                openStep: {{ $initialStep }},
                completedSteps: {{ Illuminate\Support\Js::from($initialCompleted) }},
                calYear: new Date().getFullYear(),
                calMonth: new Date().getMonth(),

                loadingSlots: false,
                foundSlots: [],
                slotsChecked: false,
                slotsPage: 0,

                isOpen(n) { return this.openStep === n; },
                isCompleted(n) { return this.completedSteps.includes(n); },
                allCompleted() { return [1,2,3,4].every(n => this.isCompleted(n)); },
                goTo(n) { this.slotsChecked = false; this.foundSlots = []; this.slotsPage = 0; this.completedSteps = this.completedSteps.filter(s => s < n); this.openStep = n; },
                completeStep(n) {
                    if (!this.completedSteps.includes(n)) this.completedSteps.push(n);
                    this.openStep = n + 1;
                },

                toggleService(id) {
                    const idx = this.selectedServices.indexOf(id);
                    if (idx === -1) this.selectedServices.push(id);
                    else this.selectedServices.splice(idx, 1);
                },
                hasService(id) { return this.selectedServices.includes(id); },
                get servicesSummary() {
                    const names = this.selectedServices.map(id => this.servicesData[id]).filter(Boolean);
                    if (!names.length) return '';
                    if (names.length <= 2) return names.join(', ');
                    return names[0] + ' +' + (names.length - 1) + ' altri';
                },

                get staffSummary() {
                    return this.staffId === null
                        ? 'Qualsiasi operatore'
                        : (this.staffData[this.staffId] ?? '');
                },

                prevMonth() {
                    if (this.calMonth === 0) { this.calYear--; this.calMonth = 11; }
                    else { this.calMonth--; }
                },
                nextMonth() {
                    if (this.calMonth === 11) { this.calYear++; this.calMonth = 0; }
                    else { this.calMonth++; }
                },
                get monthLabel() {
                    return new Date(this.calYear, this.calMonth, 1)
                        .toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
                },
                get calendarGrid() {
                    const year = this.calYear, month = this.calMonth;
                    const firstDow = (new Date(year, month, 1).getDay() + 6) % 7;
                    const days = new Date(year, month + 1, 0).getDate();
                    const cells = Array(firstDow).fill(null);
                    for (let d = 1; d <= days; d++) {
                        cells.push(year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0'));
                    }
                    return cells;
                },
                isSelectable(iso) {
                    const today = new Date(); today.setHours(0, 0, 0, 0);
                    const d = new Date(iso + 'T00:00:00');
                    return d >= today && this.openDayNums.includes(d.getDay());
                },
                isSelected(iso) { return this.selectedDates.includes(iso); },
                toggleDate(iso) {
                    if (!this.isSelectable(iso)) return;
                    const idx = this.selectedDates.indexOf(iso);
                    if (idx === -1) this.selectedDates.push(iso);
                    else this.selectedDates.splice(idx, 1);
                },
                formatDates(dates) {
                    return dates.slice().sort().map(iso =>
                        new Date(iso + 'T00:00:00').toLocaleDateString('it-IT', { day: 'numeric', month: 'short' })
                    ).join(', ');
                },
                get datesSummary() {
                    return this.selectedDates.length ? this.formatDates(this.selectedDates) : '';
                },
                get timeSummary() { return this.timeFrom + ' – ' + this.timeTo; },
                get returnUrl() {
                    let p = this.selectedServices.map(id => 'service_ids[]=' + id).join('&');
                    if (this.staffId !== null) p += (p ? '&' : '') + 'preferred_staff_id=' + this.staffId;
                    if (this.selectedDates.length) p += (p ? '&' : '') + this.selectedDates.map(d => 'preferred_days[]=' + d).join('&');
                    if (this.timeFrom) p += (p ? '&' : '') + 'preferred_time_from=' + this.timeFrom;
                    if (this.timeTo)   p += (p ? '&' : '') + 'preferred_time_to=' + this.timeTo;
                    return encodeURIComponent('{{ route('portal.waitlist.create') }}' + (p ? '?' + p : ''));
                },

                get visibleSlots() {
                    return this.foundSlots.slice(this.slotsPage * 6, (this.slotsPage + 1) * 6);
                },
                get totalSlotsPages() {
                    return Math.ceil(this.foundSlots.length / 6);
                },
                prevSlotsPage() { if (this.slotsPage > 0) this.slotsPage--; },
                nextSlotsPage() { if (this.slotsPage < this.totalSlotsPages - 1) this.slotsPage++; },

                formatDate(iso) {
                    const s = new Date(iso + 'T00:00:00').toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long' });
                    return s.charAt(0).toUpperCase() + s.slice(1);
                },

                bookSlot(date, time) {
                    sessionStorage.setItem('bookingWizardState', JSON.stringify({
                        selectedServiceIds: this.selectedServices,
                        staffId:            this.staffId,
                        date:               date,
                        slot:               time,
                        calendarMonth:      date.slice(0, 7),
                        step:               4,
                        completed:          [1, 2, 3],
                        waitlistEntryId:    null,
                        paymentMethod:      null,
                        notes:              '',
                    }));
                    window.location.href = '{{ route('booking.create') }}';
                },

                async checkAvailableSlots() {
                    if (!this.selectedServices.length || !this.selectedDates.length) return;
                    this.loadingSlots = true;
                    this.foundSlots   = [];
                    this.slotsChecked = false;
                    this.slotsPage    = 0;

                    const params = new URLSearchParams();
                    this.selectedServices.forEach(id => params.append('serviceIds[]', id));
                    if (this.staffId) params.append('staffId', this.staffId);
                    params.append('staffPreference', this.staffId ? 'specific' : 'any');

                    const results = [];
                    for (const date of this.selectedDates) {
                        params.set('date', date);
                        try {
                            const res  = await fetch('/api/booking/slots?' + params.toString(), { headers: { Accept: 'application/json' } });
                            const data = await res.json();
                            for (const slot of (data.data ?? [])) {
                                if (slot.start >= this.timeFrom && slot.start < this.timeTo) {
                                    results.push({ date, time: slot.start });
                                }
                            }
                        } catch (_) {}
                    }

                    results.sort((a, b) => a.date.localeCompare(b.date) || a.time.localeCompare(b.time));
                    this.foundSlots   = results;
                    this.slotsChecked = true;
                    this.loadingSlots = false;
                },
            }"
            x-init="$nextTick(() => allCompleted() && checkAvailableSlots())"
            class="space-y-3"
        >
            <form method="POST" action="{{ route('portal.waitlist.store') }}" class="space-y-3">
                @csrf
                <template x-for="id in selectedServices" :key="id">
                    <input type="hidden" name="service_ids[]" :value="id">
                </template>
                <input type="hidden" name="preferred_staff_id" :value="staffId ?? ''">
                <template x-for="d in selectedDates" :key="d">
                    <input type="hidden" name="preferred_days[]" :value="d">
                </template>
                <input type="hidden" name="preferred_time_from" :value="timeFrom">
                <input type="hidden" name="preferred_time_to" :value="timeTo">

            {{-- Step 1: Servizi --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(1) && !isOpen(1) ? goTo(1) : null"
                    :class="isCompleted(1) && !isOpen(1) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(1) ? 'step-done text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(1)">1</span>
                            <svg x-show="isCompleted(1)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scegli i servizi</p>
                            <p x-show="isCompleted(1) && !isOpen(1)" class="text-xs text-gray-500 dark:text-gray-400" x-text="servicesSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(1)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($services as $service)
                            <button
                                type="button"
                                @click="toggleService({{ $service->id }})"
                                class="rounded border p-4 text-left transition-colors"
                                :class="hasService({{ $service->id }})
                                    ? 'opt-selected border-transparent'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $service->name }}</span>
                                    <span class="shrink-0 rounded bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 text-xs font-semibold text-gray-700 dark:text-gray-300">€ {{ number_format($service->price, 2, ',', '') }}</span>
                                </div>
                                @if($service->description)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $service->description }}</p>
                                @endif
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ $service->duration_minutes }} min</p>
                            </button>
                        @endforeach
                    </div>
                    @error('service_ids')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(1)"
                            :disabled="selectedServices.length === 0"
                            class="btn-wl"
                        >Continua</button>
                    </div>
                </div>
            </div>

            {{-- Step 2: Operatore --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
                 :class="!isCompleted(1) && !isOpen(2) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(2) && !isOpen(2) ? goTo(2) : null"
                    :class="isCompleted(2) && !isOpen(2) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(2) ? 'step-done text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(2)">2</span>
                            <svg x-show="isCompleted(2)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scegli l'operatore</p>
                            <p x-show="isCompleted(2) && !isOpen(2)" class="text-xs text-gray-500 dark:text-gray-400" x-text="staffSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(2)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <div class="space-y-2">
                        <button
                            type="button"
                            @click="staffId = null"
                            class="w-full rounded border p-4 text-left transition-colors"
                            :class="staffId === null
                                ? 'opt-selected border-transparent'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                        >
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Qualsiasi operatore disponibile</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Verrai avvisato per il primo operatore libero</p>
                        </button>
                        @foreach($staff as $member)
                            <button
                                type="button"
                                @click="staffId = {{ $member->id }}"
                                class="w-full rounded border p-4 text-left transition-colors"
                                :class="staffId === {{ $member->id }}
                                    ? 'opt-selected border-transparent'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                            >
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $member->name }}</p>
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="completeStep(2)" class="btn-wl">Continua</button>
                    </div>
                </div>
            </div>

            {{-- Step 3: Date preferite --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
                 :class="!isCompleted(2) && !isOpen(3) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(3) && !isOpen(3) ? goTo(3) : null"
                    :class="isCompleted(3) && !isOpen(3) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(3) ? 'step-done text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(3)">3</span>
                            <svg x-show="isCompleted(3)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scegli le date</p>
                            <p x-show="isCompleted(3) && !isOpen(3)" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" x-text="datesSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(3)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Puoi selezionare più date. Quando si libera uno slot compatibile, riceverai una notifica insieme a tutti gli altri iscritti — chi prenota per primo avrà il posto.</p>
                    <div class="mb-4 flex items-center justify-between">
                        <button type="button" @click="prevMonth()" class="rounded p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="h-4 w-4 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="monthLabel"></p>
                        <button type="button" @click="nextMonth()" class="rounded p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="h-4 w-4 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        <template x-for="d in ['Lu','Ma','Me','Gi','Ve','Sa','Do']">
                            <div class="py-1 text-xs font-medium text-gray-400 dark:text-gray-500" x-text="d"></div>
                        </template>
                        <template x-for="(cell, i) in calendarGrid" :key="'wl' + i">
                            <div>
                                <template x-if="cell === null"><div></div></template>
                                <template x-if="cell !== null">
                                    <button
                                        type="button"
                                        @click="toggleDate(cell)"
                                        :disabled="!isSelectable(cell)"
                                        class="w-full rounded py-1.5 text-sm transition-colors"
                                        :class="{
                                            'cal-active': isSelected(cell),
                                            'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-900 dark:text-gray-100': isSelectable(cell) && !isSelected(cell),
                                            'text-gray-300 dark:text-gray-600 cursor-not-allowed': !isSelectable(cell),
                                        }"
                                        x-text="cell.split('-')[2]"
                                    ></button>
                                </template>
                            </div>
                        </template>
                    </div>
                    <p x-show="selectedDates.length > 0" class="mt-3 text-xs text-gray-500 dark:text-gray-400"
                       x-text="formatDates(selectedDates)"></p>
                    @error('preferred_days')
                        <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(3)"
                            :disabled="selectedDates.length === 0"
                            class="btn-wl"
                        >Continua</button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Fascia oraria --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
                 :class="!isCompleted(3) && !isOpen(4) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(4) && !isOpen(4) ? goTo(4) : null"
                    :class="isCompleted(4) && !isOpen(4) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(4) ? 'step-done text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(4)">4</span>
                            <svg x-show="isCompleted(4)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Fascia oraria</p>
                            <p x-show="isCompleted(4) && !isOpen(4)" class="text-xs text-gray-500 dark:text-gray-400" x-text="timeSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(4)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">Più è ampio l'intervallo, maggiori sono le possibilità che si liberi un posto.</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="preferred_time_from" class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Dalle</label>
                            <select id="preferred_time_from" x-model="timeFrom"
                                class="mt-1.5 block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                @foreach($timeOptions as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('preferred_time_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="preferred_time_to" class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Alle</label>
                            <select id="preferred_time_to" x-model="timeTo"
                                class="mt-1.5 block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                @foreach($timeOptions as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('preferred_time_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="completeStep(4); checkAvailableSlots()" class="btn-wl">Continua</button>
                    </div>
                </div>
            </div>

            {{-- Azioni (visibili solo quando tutti gli step sono completati) --}}
            <div x-show="allCompleted()"
                 class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                <div class="px-5 py-5">

                    {{-- Caricamento --}}
                    <div x-show="loadingSlots" class="flex items-center justify-center gap-2 py-3 text-sm text-gray-500 dark:text-gray-400">
                        <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Verifica disponibilità...
                    </div>

                    {{-- Slot disponibili trovati --}}
                    <div x-show="!loadingSlots && slotsChecked && foundSlots.length > 0">
                        <p class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Ci sono già slot disponibili nelle date selezionate:</p>
                        <div class="mb-2 space-y-2">
                            <template x-for="slot in visibleSlots" :key="slot.date + slot.time">
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="formatDate(slot.date)"></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="slot.time"></p>
                                    </div>
                                    <button type="button" @click="bookSlot(slot.date, slot.time)" class="btn-wl">Prenota</button>
                                </div>
                            </template>
                        </div>
                        <div x-show="totalSlotsPages > 1" class="mb-4 flex items-center justify-between">
                            <button type="button" @click="prevSlotsPage()" :disabled="slotsPage === 0"
                                    class="rounded p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                <svg class="h-4 w-4 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="(slotsPage + 1) + ' / ' + totalSlotsPages"></p>
                            <button type="button" @click="nextSlotsPage()" :disabled="slotsPage >= totalSlotsPages - 1"
                                    class="rounded p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                                <svg class="h-4 w-4 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                        @auth
                            <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-700 pt-4">
                                <a href="{{ route('portal.appointments.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">Annulla</a>
                                <button type="submit" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Iscriviti comunque alla lista d'attesa</button>
                            </div>
                        @else
                            <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
                                <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">Per iscriverti alla lista d'attesa accedi o crea un account.</p>
                                <div class="flex gap-3">
                                    <a :href="'{{ route('login') }}?return=' + returnUrl"
                                       class="flex-1 rounded border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-center text-sm font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                        Accedi
                                    </a>
                                    <a :href="'{{ route('register') }}?return=' + returnUrl"
                                       class="btn-wl-full flex-1 text-center">
                                        Crea account
                                    </a>
                                </div>
                            </div>
                        @endauth
                    </div>

                    {{-- Nessuno slot trovato o controllo non ancora eseguito --}}
                    <div x-show="!loadingSlots && (!slotsChecked || foundSlots.length === 0)">
                        @auth
                            <div class="flex items-center justify-between">
                                <a href="{{ route('portal.appointments.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">Annulla</a>
                                <button type="submit" class="btn-wl">Iscriviti alla lista d'attesa</button>
                            </div>
                        @else
                            <p class="text-sm text-gray-600 dark:text-gray-400">Accedi o crea un account per iscriverti alla lista d'attesa.</p>
                            <div class="mt-3 flex gap-3">
                                <a :href="'{{ route('login') }}?return=' + returnUrl"
                                   class="flex-1 rounded border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-center text-sm font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    Accedi
                                </a>
                                <a :href="'{{ route('register') }}?return=' + returnUrl"
                                   class="btn-wl-full flex-1 text-center">
                                    Crea account
                                </a>
                            </div>
                        @endauth
                    </div>

                </div>
            </div>

            </form>
        </div>
    </div>
@endsection
