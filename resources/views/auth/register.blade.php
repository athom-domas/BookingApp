@extends('layouts.app')

@section('title', 'Registrazione')

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Crea account cliente</h1>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Nome</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Conferma password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Crea account
            </button>
        </form>

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            Hai gia un account?
            <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:text-blue-800">Accedi</a>
        </p>
    </section>
@endsection
