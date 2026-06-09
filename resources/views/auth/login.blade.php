@extends('layouts.app')

@section('title', 'Accesso')

@push('head')
<style>
.sf-input:focus { outline: none; border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 15%, transparent) !important; }
</style>
@endpush

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Accedi</h1>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="sf-input mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
                    <a href="{{ route('password.request') }}" class="sf-accent-link text-sm font-semibold">Hai dimenticato la password?</a>
                </div>
                <input id="password" name="password" type="password" required class="sf-input mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm">
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 dark:border-gray-600" style="accent-color: var(--color-primary)">
                Ricordami
            </label>

            <button type="submit" class="btn-primary w-full px-4 py-3 text-sm font-semibold text-white shadow-sm">
                Accedi
            </button>
        </form>

        <div class="relative mt-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="bg-white dark:bg-gray-900 px-3 text-gray-500 dark:text-gray-400">oppure</span>
            </div>
        </div>

        <a href="{{ rtrim(request()->getSchemeAndHttpHost(), '/') . '/auth/google' }}" class="mt-4 flex w-full items-center justify-center gap-3 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Accedi con Google
        </a>

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            Non hai un account?
            <a href="{{ route('register') }}{{ request()->filled('return') ? '?return='.urlencode(request('return')) : '' }}" class="sf-accent-link font-semibold">Registrati</a>
        </p>
    </section>
@endsection
