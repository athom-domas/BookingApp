@extends('layouts.app')

@section('title', 'Iscriviti alla lista d\'attesa')

@section('content')
    <section class="max-w-xl space-y-6">
        <div>
            <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Lista d'attesa</h1>
            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Ti notificheremo quando si libera uno slot compatibile con le tue preferenze.</p>
        </div>

        <form method="POST" action="{{ route('portal.waitlist.store') }}" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Servizi <span class="text-red-500">*</span></label>
                <div class="mt-2 space-y-2">
                    @foreach($services as $service)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="service_ids[]" value="{{ $service->id }}"
                                {{ in_array($service->id, $prefilledServiceIds) ? 'checked' : '' }}
                                class="rounded border-gray-300 dark:border-gray-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $service->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('service_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="preferred_staff_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Operatore (opzionale)</label>
                <select name="preferred_staff_id" id="preferred_staff_id"
                    class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    <option value="">Qualsiasi operatore</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->id }}" {{ old('preferred_staff_id') == $member->id ? 'selected' : '' }}>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="preferred_date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dal <span class="text-red-500">*</span></label>
                    <input type="date" name="preferred_date_from" id="preferred_date_from"
                        value="{{ old('preferred_date_from') }}" min="{{ today()->toDateString() }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    @error('preferred_date_from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="preferred_date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Al <span class="text-red-500">*</span></label>
                    <input type="date" name="preferred_date_to" id="preferred_date_to"
                        value="{{ old('preferred_date_to') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    @error('preferred_date_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="preferred_time_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dalle <span class="text-red-500">*</span></label>
                    <input type="time" name="preferred_time_from" id="preferred_time_from"
                        value="{{ old('preferred_time_from', '09:00') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                </div>
                <div>
                    <label for="preferred_time_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alle <span class="text-red-500">*</span></label>
                    <input type="time" name="preferred_time_to" id="preferred_time_to"
                        value="{{ old('preferred_time_to', '18:00') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Giorni preferiti <span class="text-red-500">*</span></label>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach(['monday' => 'Lun', 'tuesday' => 'Mar', 'wednesday' => 'Mer', 'thursday' => 'Gio', 'friday' => 'Ven', 'saturday' => 'Sab', 'sunday' => 'Dom'] as $value => $label)
                        <label class="flex items-center gap-1.5">
                            <input type="checkbox" name="preferred_days[]" value="{{ $value }}"
                                {{ !old('preferred_days') || in_array($value, old('preferred_days', [])) ? 'checked' : '' }}
                                class="rounded border-gray-300 dark:border-gray-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('preferred_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-4">
                <button type="submit" class="btn-primary rounded-md px-5 py-2.5 text-sm font-semibold text-white">
                    Iscriviti alla lista d'attesa
                </button>
                <a href="{{ route('portal.waitlist.index') }}" class="self-center text-sm text-gray-600 hover:underline dark:text-gray-400">Annulla</a>
            </div>
        </form>
    </section>
@endsection
