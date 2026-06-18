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

    {{-- Notifiche --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
         x-data="{ channel: '{{ old('notification_channel', $preferences->notification_channel) }}' }">
        <div class="border-b border-gray-100 dark:border-gray-800 px-6 py-4">
            <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">Notifiche</h2>
        </div>
        <div class="p-6">
            @if (session('notifications_updated'))
                <div class="mb-5 rounded border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('notifications_updated') }}
                </div>
            @endif

            <form method="POST" action="{{ route('portal.settings.notifications') }}" class="max-w-md space-y-5">
                @csrf
                @method('PATCH')

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

                <div x-cloak x-show="channel === 'whatsapp'">
                    <label for="phone_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Numero di telefono <span class="text-red-500">*</span>
                    </label>
                    <div class="flex rounded border border-gray-200 dark:border-gray-700 transition focus-within:border-gray-900 dark:focus-within:border-gray-200 focus-within:ring-1 focus-within:ring-gray-900 dark:focus-within:ring-gray-200">
                        <span class="inline-flex items-center border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 text-sm text-gray-500 dark:text-gray-400 rounded-l">
                            +39
                        </span>
                        <input type="tel" id="phone_number" name="phone_number"
                            value="{{ old('phone_number', $preferences->phone_number ? preg_replace('/^\+39/', '', $preferences->phone_number) : '') }}"
                            placeholder="334 1234567"
                            class="block w-full rounded-r border-0 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 focus:outline-none">
                    </div>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Es. 334 1234567</p>
                    @error('phone_number')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <button type="submit" class="btn-primary rounded px-5 py-2.5 text-sm font-semibold text-white">
                        Salva notifiche
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Comunicazioni --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
        <div class="border-b border-gray-100 dark:border-gray-800 px-6 py-4">
            <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">Comunicazioni</h2>
        </div>
        <div class="p-6">
            @if (session('communications_updated'))
                <div class="mb-5 rounded border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('communications_updated') }}
                </div>
            @endif

            <form method="POST" action="{{ route('portal.settings.communications') }}" class="max-w-md space-y-5">
                @csrf
                @method('PATCH')

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
