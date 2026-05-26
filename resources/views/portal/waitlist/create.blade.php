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
    .day-active {
        background-color: var(--color-primary);
        border-color: var(--color-primary);
        color: #fff;
    }
    .btn-wl {
        background-color: var(--color-primary);
        color: #fff;
        font-size: .875rem;
        font-weight: 600;
        padding: .625rem 1.5rem;
        border-radius: .375rem;
        transition: filter .15s;
    }
    .btn-wl:hover { filter: brightness(0.85); }
</style>
@endpush

@section('content')
    @php
        $oldServices = old('service_ids', $prefilledServiceIds);
        $oldServices = array_map('intval', (array) $oldServices);

        $oldStaffRaw = old('preferred_staff_id');
        if ($oldStaffRaw !== null) {
            $initStaff = $oldStaffRaw === '' ? null : (int) $oldStaffRaw;
        } else {
            $initStaff = $prefilledStaffId;
        }

        $oldDays = old('preferred_days', []);
    @endphp

    <div class="mx-auto max-w-2xl space-y-4">
        <div class="space-y-1">
            <h1 class="font-display text-2xl font-semibold text-gray-950 dark:text-gray-50">Lista d'attesa</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Ti notificheremo quando si libera uno slot compatibile con le tue preferenze.</p>
        </div>

        <div
            x-data="{
                selectedServices: {{ Illuminate\Support\Js::from($oldServices) }},
                staffId: {{ Illuminate\Support\Js::from($initStaff) }},
                selectedDays: {{ Illuminate\Support\Js::from($oldDays) }},
                toggleService(id) {
                    const idx = this.selectedServices.indexOf(id);
                    if (idx === -1) this.selectedServices.push(id);
                    else this.selectedServices.splice(idx, 1);
                },
                toggleDay(d) {
                    const idx = this.selectedDays.indexOf(d);
                    if (idx === -1) this.selectedDays.push(d);
                    else this.selectedDays.splice(idx, 1);
                },
                hasService(id) { return this.selectedServices.includes(id); },
                hasDay(d) { return this.selectedDays.includes(d); },
            }"
        >
            <form method="POST" action="{{ route('portal.waitlist.store') }}" class="space-y-3">
                @csrf

                <template x-for="id in selectedServices" :key="id">
                    <input type="hidden" name="service_ids[]" :value="id">
                </template>
                <input type="hidden" name="preferred_staff_id" :value="staffId ?? ''">
                <template x-for="d in selectedDays" :key="d">
                    <input type="hidden" name="preferred_days[]" :value="d">
                </template>

                {{-- Servizi --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                    <div class="border-b border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Servizi</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Seleziona i servizi per cui vuoi essere avvisato</p>
                    </div>
                    <div class="px-5 pb-5 pt-4">
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
                    </div>
                </div>

                {{-- Operatore --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                    <div class="border-b border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Operatore</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Vuoi un operatore specifico o va bene chiunque sia libero?</p>
                    </div>
                    <div class="space-y-2 px-5 pb-5 pt-4">
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
                </div>

                {{-- Periodo --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                    <div class="border-b border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Periodo preferito</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">In quale intervallo di date vorresti l'appuntamento?</p>
                    </div>
                    <div class="px-5 pb-5 pt-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="preferred_date_from" class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Dal</label>
                                <input type="date" name="preferred_date_from" id="preferred_date_from"
                                    value="{{ old('preferred_date_from') }}" min="{{ today()->toDateString() }}"
                                    class="mt-1.5 block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                @error('preferred_date_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="preferred_date_to" class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Al</label>
                                <input type="date" name="preferred_date_to" id="preferred_date_to"
                                    value="{{ old('preferred_date_to') }}" min="{{ today()->toDateString() }}"
                                    class="mt-1.5 block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                @error('preferred_date_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Fascia oraria --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                    <div class="border-b border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Fascia oraria</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">In quale orario preferiresti l'appuntamento?</p>
                    </div>
                    <div class="px-5 pb-5 pt-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="preferred_time_from" class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Dalle</label>
                                <input type="time" name="preferred_time_from" id="preferred_time_from"
                                    value="{{ old('preferred_time_from', '09:00') }}"
                                    class="mt-1.5 block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                @error('preferred_time_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="preferred_time_to" class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Alle</label>
                                <input type="time" name="preferred_time_to" id="preferred_time_to"
                                    value="{{ old('preferred_time_to', '18:00') }}"
                                    class="mt-1.5 block w-full rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                @error('preferred_time_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Giorni --}}
                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                    <div class="border-b border-gray-100 dark:border-gray-700 px-5 py-4">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Giorni preferiti</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">In quali giorni della settimana vorresti l'appuntamento?</p>
                    </div>
                    <div class="px-5 pb-5 pt-4">
                        <div class="flex flex-wrap gap-2">
                            @foreach(['monday' => 'Lunedì', 'tuesday' => 'Martedì', 'wednesday' => 'Mercoledì', 'thursday' => 'Giovedì', 'friday' => 'Venerdì', 'saturday' => 'Sabato', 'sunday' => 'Domenica'] as $value => $label)
                                <button
                                    type="button"
                                    @click="toggleDay('{{ $value }}')"
                                    class="rounded-full border px-4 py-1.5 text-sm font-medium transition-colors"
                                    :class="hasDay('{{ $value }}')
                                        ? 'day-active border-transparent'
                                        : 'border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-gray-400 hover:bg-gray-50 dark:hover:border-gray-500 dark:hover:bg-gray-800'"
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                        @error('preferred_days')
                            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Azioni --}}
                <div class="flex items-center justify-between pt-1">
                    <a href="{{ route('portal.waitlist.index') }}" class="text-sm text-gray-600 hover:underline dark:text-gray-400">Annulla</a>
                    <button type="submit" class="btn-wl">Iscriviti alla lista d'attesa</button>
                </div>
            </form>
        </div>
    </div>
@endsection
