@extends('layouts.app')

@section('title', 'Impostazioni')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Impostazioni</h1>
    </div>

    {{-- Profilo --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div class="border-b border-gray-100 dark:border-gray-800 px-6 py-4">
            <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">Profilo</h2>
        </div>
        <div class="p-6">
            @if (session('profile_updated'))
                <div class="mb-5 rounded border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('profile_updated') }}
                </div>
            @endif

            <form method="POST" action="{{ route('portal.settings.profile') }}" class="max-w-md space-y-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nome</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                        class="block w-full rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 transition focus:border-gray-900 dark:focus:border-gray-200 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-gray-200">
                    @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                        class="block w-full rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 transition focus:border-gray-900 dark:focus:border-gray-200 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-gray-200">
                    @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Numero di telefono</label>
                    <div class="flex max-w-xs rounded border border-gray-200 dark:border-gray-700 transition focus-within:border-gray-900 dark:focus-within:border-gray-200 focus-within:ring-1 focus-within:ring-gray-900 dark:focus-within:ring-gray-200">
                        <span class="inline-flex items-center border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 text-sm text-gray-500 dark:text-gray-400 rounded-l">+39</span>
                        <input type="tel" id="phone_number" name="phone_number"
                            value="{{ old('phone_number', $preferences->phone_number ? preg_replace('/^\+39/', '', $preferences->phone_number) : '') }}"
                            placeholder="334 1234567"
                            class="block w-full rounded-r border-0 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Obbligatorio per ricevere notifiche WhatsApp.</p>
                    @error('phone_number')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800 pt-5">
                    <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">Lascia vuoto per non cambiare la password.</p>

                    <div class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Password attuale</label>
                            <input type="password" id="current_password" name="current_password"
                                class="block w-full rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm transition focus:border-gray-900 dark:focus:border-gray-200 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-gray-200">
                            @error('current_password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nuova password</label>
                            <input type="password" id="new_password" name="new_password"
                                class="block w-full rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm transition focus:border-gray-900 dark:focus:border-gray-200 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-gray-200">
                            @error('new_password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Conferma nuova password</label>
                            <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                                class="block w-full rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm transition focus:border-gray-900 dark:focus:border-gray-200 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-gray-200">
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" class="btn-primary rounded px-5 py-2.5 text-sm font-semibold text-white">
                        Salva profilo
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Notifiche, Comunicazioni e Preferenze prenotazione --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
         x-data="{ channel: '{{ old('notification_channel', $preferences->notification_channel) }}' }">
        <div class="border-b border-gray-100 dark:border-gray-800 px-6 py-4">
            <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">Preferenze</h2>
        </div>
        <div class="p-6">
            @if (session('preferences_updated'))
                <div class="mb-5 rounded border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('preferences_updated') }}
                </div>
            @endif

            <form method="POST" action="{{ route('portal.settings.preferences') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                {{-- Notifiche --}}
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Notifiche</p>

                    <div>
                        <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-300">Canale di notifica</label>
                        <div class="space-y-2">
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="radio" name="notification_channel" value="email"
                                    x-model="channel"
                                    class="h-4 w-4 border-gray-300 dark:border-gray-600"
                                    style="accent-color: var(--color-primary)">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Email</span>
                            </label>
                            @if (\App\Models\IntegrationSetting::hasMetaWhatsApp())
                            <label class="flex cursor-pointer items-center gap-3">
                                <input type="radio" name="notification_channel" value="whatsapp"
                                    x-model="channel"
                                    class="h-4 w-4 border-gray-300 dark:border-gray-600"
                                    style="accent-color: var(--color-primary)">
                                <span class="text-sm text-gray-700 dark:text-gray-300">WhatsApp</span>
                            </label>
                            @endif
                        </div>
                        @error('notification_channel')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                </div>

                <div class="border-t border-gray-100 dark:border-gray-800"></div>

                {{-- Comunicazioni --}}
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Comunicazioni</p>

                    <div class="flex items-start gap-3">
                        <input type="hidden" name="follow_up_reminders_enabled" value="0">
                        <input type="checkbox" id="follow_up_reminders_enabled" name="follow_up_reminders_enabled"
                            value="1"
                            {{ old('follow_up_reminders_enabled', $preferences->follow_up_reminders_enabled) ? 'checked' : '' }}
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 dark:border-gray-600"
                            style="accent-color: var(--color-primary)">
                        <div>
                            <label for="follow_up_reminders_enabled" class="block text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                                Ricevi promemoria per prenotare un nuovo appuntamento
                            </label>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                Ti invieremo un promemoria se è passato un po' dal tuo ultimo appuntamento e non hai ancora una nuova prenotazione.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 dark:border-gray-800"></div>

                {{-- Preferenze prenotazione --}}
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Prenotazione</p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Usiamo queste informazioni per suggerirti gli slot più adatti.</p>
                    </div>

                    @php
                        $dayLabels   = [0 => 'Dom', 1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab'];
                        $savedDays   = $preferences->preferred_days ?? [];
                        $timeOptions = [];
                        for ($h = 7; $h <= 21; $h++) {
                            $timeOptions[sprintf('%02d:00', $h)] = sprintf('%02d:00', $h);
                            if ($h < 21) $timeOptions[sprintf('%02d:30', $h)] = sprintf('%02d:30', $h);
                        }
                    @endphp
                    <div class="flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-6">
                        <div class="flex-1">
                            <p class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Giorni preferiti</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($openDayNums as $num)
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" name="preferred_days[]" value="{{ $num }}"
                                               {{ in_array($num, $savedDays) ? 'checked' : '' }}
                                               class="rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
                                        <span class="text-sm text-gray-900 dark:text-gray-100">{{ $dayLabels[$num] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex items-end gap-3 sm:shrink-0">
                            <div>
                                <label class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Dalle</label>
                                <select name="preferred_time_from"
                                    class="block w-28 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                    <option value="">—</option>
                                    @foreach($timeOptions as $val => $label)
                                        <option value="{{ $val }}" {{ ($preferences->preferred_time_from ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('preferred_time_from')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Alle</label>
                                <select name="preferred_time_to"
                                    class="block w-28 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-gray-900 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:border-gray-200 dark:focus:ring-gray-200">
                                    <option value="">—</option>
                                    @foreach($timeOptions as $val => $label)
                                        <option value="{{ $val }}" {{ ($preferences->preferred_time_to ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('preferred_time_to')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" class="btn-primary rounded px-5 py-2.5 text-sm font-semibold text-white">
                        Salva preferenze
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
