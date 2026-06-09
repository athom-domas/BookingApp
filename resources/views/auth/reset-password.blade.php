@extends('layouts.app')

@section('title', 'Nuova password')

@push('head')
<style>
.sf-input:focus { outline: none; border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 15%, transparent) !important; }
</style>
@endpush

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Nuova password</h1>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus
                    class="sf-input mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm">
                @error('email')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Nuova password</label>
                <input id="password" name="password" type="password" required
                    class="sf-input mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm">
                @error('password')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Conferma password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    class="sf-input mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm">
            </div>

            <button type="submit" class="btn-primary w-full px-4 py-3 text-sm font-semibold text-white shadow-sm">
                Reimposta password
            </button>
        </form>
    </section>
@endsection
