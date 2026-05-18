@extends('layouts.app')

@section('title', 'Accesso')

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Accedi</h1>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-700 focus:ring-blue-200 dark:focus:ring-blue-900">
                Ricordami
            </label>

            <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Accedi
            </button>
        </form>

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            Non hai un account?
            <a href="{{ route('register') }}{{ request()->filled('return') ? '?return='.urlencode(request('return')) : '' }}" class="font-semibold text-blue-700 hover:text-blue-800">Registrati</a>
        </p>
    </section>
@endsection
