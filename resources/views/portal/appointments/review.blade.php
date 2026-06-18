@extends('layouts.app')

@section('title', 'Lascia una recensione')

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            <h1 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50 mb-1">Come è andata?</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ $appointment->services_label }} — {{ $appointment->scheduled_date->format('d/m/Y') }} con {{ $appointment->staff->name }}
            </p>

            <form method="POST" action="{{ route('portal.reviews.store') }}" x-data="{ rating: 0, hovered: 0 }">
                @csrf
                <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">

                <div class="mb-5">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Valutazione</p>
                    <div class="flex gap-1">
                        @for ($i = 1; $i <= 5; $i++)
                            <label class="cursor-pointer">
                                <input type="radio" name="rating" value="{{ $i }}"
                                    style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0"
                                    x-on:change="rating = {{ $i }}" required>
                                <svg @mouseenter="hovered = {{ $i }}" @mouseleave="hovered = 0"
                                    class="w-10 h-10 transition-colors"
                                    :class="(hovered || rating) >= {{ $i }} ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </label>
                        @endfor
                    </div>
                    @error('rating')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block" for="body">La tua esperienza</label>
                    <textarea id="body" name="body" rows="5" required maxlength="1000"
                        placeholder="Raccontaci com'è andata..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('portal.appointments.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">← Torna agli appuntamenti</a>
                    <button type="submit" class="btn-primary rounded-lg px-6 py-2.5 text-sm font-semibold text-white">Invia recensione</button>
                </div>
            </form>
        </div>
    </div>
@endsection
