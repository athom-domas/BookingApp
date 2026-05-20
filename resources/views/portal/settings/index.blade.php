@extends('layouts.app')

@section('title', 'Impostazioni')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-semibold text-gray-950 dark:text-gray-50">Impostazioni</h1>
    </div>

    {{-- Profilo --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h2 class="mb-6 text-lg font-semibold text-gray-950 dark:text-gray-50">Profilo</h2>

        @if (session('profile_updated'))
            <div class="mb-4 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                {{ session('profile_updated') }}
            </div>
        @endif

        <form method="POST" action="{{ route('portal.settings.profile') }}" class="max-w-md space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nome</label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Lascia vuoto per non cambiare la password.</p>

                <div class="space-y-4">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password attuale</label>
                        <input type="password" id="current_password" name="current_password"
                            class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @error('current_password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nuova password</label>
                        <input type="password" id="new_password" name="new_password"
                            class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                        @error('new_password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Conferma nuova password</label>
                        <input type="password" id="new_password_confirmation" name="new_password_confirmation"
                            class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div>
                <button type="submit" class="btn-primary rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm">
                    Salva profilo
                </button>
            </div>
        </form>
    </div>

    {{-- Notifiche --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm"
         x-data="{ channel: '{{ old('notification_channel', $preferences->notification_channel) }}' }">
        <h2 class="mb-6 text-lg font-semibold text-gray-950 dark:text-gray-50">Notifiche</h2>

        @if (session('notifications_updated'))
            <div class="mb-4 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                {{ session('notifications_updated') }}
            </div>
        @endif

        <form method="POST" action="{{ route('portal.settings.notifications') }}" class="max-w-md space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Canale di notifica</label>
                <div class="space-y-2">
                    @foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="radio" name="notification_channel" value="{{ $value }}"
                                x-model="channel"
                                class="h-4 w-4 border-gray-300 text-blue-600 dark:border-gray-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('notification_channel')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div x-show="channel === 'sms' || channel === 'whatsapp'">
                <label for="phone_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Numero di telefono <span class="text-red-500">*</span>
                </label>
                <input type="tel" id="phone_number" name="phone_number"
                    value="{{ old('phone_number', $preferences->phone_number) }}"
                    placeholder="+39 334 1234567"
                    class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Formato internazionale, es. +39 334 1234567</p>
                @error('phone_number')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <button type="submit" class="btn-primary rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm">
                    Salva notifiche
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
