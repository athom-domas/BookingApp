@extends('layouts.app')

@section('title', $profile->name)

@section('content')

{{-- 1. HERO --}}
<section class="relative flex min-h-[480px] items-center justify-center overflow-hidden"
         style="background-color: var(--color-primary)">
    @if($profile->coverUrl())
        <img src="{{ $profile->coverUrl() }}"
             class="absolute inset-0 h-full w-full object-cover"
             alt="">
        <div class="absolute inset-0 bg-black/50"></div>
    @endif
    <div class="relative z-10 space-y-5 px-4 text-center">
        @if($profile->logoUrl())
            <img src="{{ $profile->logoUrl() }}"
                 class="mx-auto h-16 object-contain"
                 alt="{{ $profile->name }}">
        @endif
        <h1 class="text-4xl font-bold text-white sm:text-5xl">
            {{ $profile->name }}
        </h1>
        @if($profile->tagline)
            <p class="mx-auto max-w-xl text-lg text-white/80">
                {{ $profile->tagline }}
            </p>
        @endif
        <a href="{{ route('booking.create') }}"
           class="inline-block rounded-md border-2 border-white px-7 py-3 text-sm font-semibold text-white transition-colors hover:bg-white hover:text-gray-900">
            Prenota ora
        </a>
    </div>
</section>

{{-- 2. SERVIZI --}}
@if($services->isNotEmpty())
<section class="py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            I nostri servizi
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($services as $service)
                <article class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-50">
                            {{ $service->name }}
                        </h3>
                        <span class="shrink-0 rounded-lg px-2.5 py-1 text-sm font-semibold text-white"
                              style="background-color: var(--color-primary)">
                            {{ number_format((float) $service->price, 2, ',', '.') }} €
                        </span>
                    </div>
                    @if($service->description)
                        <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                            {{ $service->description }}
                        </p>
                    @endif
                    <p class="mt-3 text-sm text-gray-500">Durata: {{ $service->duration_minutes }} min</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 3. TEAM --}}
@if($staff->isNotEmpty())
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            Il nostro team
        </h2>
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($staff as $member)
                <div class="text-center space-y-3">
                    @php $avatarUrl = $member->getFirstMediaUrl('avatar', 'thumb'); @endphp
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}"
                             class="mx-auto h-24 w-24 rounded-full object-cover ring-2 ring-white dark:ring-gray-800"
                             alt="{{ $member->name }}">
                    @else
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full text-2xl font-bold text-white"
                             style="background-color: var(--color-primary)">
                            {{ strtoupper(mb_substr($member->name, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-gray-50">{{ $member->name }}</p>
                        @if($member->bio)
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                {{ $member->bio }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 4. GALLERIA --}}
@php $galleryItems = $profile->getMedia('gallery'); @endphp
@if($galleryItems->isNotEmpty())
<section class="py-16 px-4" x-data="{ lightbox: null }">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            Galleria
        </h2>
        <div class="columns-2 gap-3 sm:columns-3 lg:columns-4">
            @foreach($galleryItems as $item)
                @php $thumbUrl = $item->getUrl('thumb'); $webUrl = $item->getUrl('web'); @endphp
                <div class="mb-3 break-inside-avoid cursor-pointer overflow-hidden rounded-lg"
                     @click="lightbox = '{{ $webUrl }}'">
                    <img src="{{ $thumbUrl }}"
                         class="w-full object-cover transition-transform hover:scale-105"
                         alt="Galleria {{ $loop->iteration }}">
                </div>
            @endforeach
        </div>
    </div>
    <div x-show="lightbox"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
         @click="lightbox = null"
         @keydown.escape.window="lightbox = null"
         style="display:none">
        <img :src="lightbox" class="max-h-[90vh] max-w-full rounded-lg object-contain shadow-2xl">
    </div>
</section>
@endif

{{-- 5. CHI SIAMO --}}
@if($profile->description)
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4">
    <div class="mx-auto max-w-2xl text-center">
        <h2 class="mb-6 text-2xl font-bold text-gray-900 dark:text-gray-50">Chi siamo</h2>
        <div class="prose prose-gray dark:prose-invert mx-auto text-left">
            {!! $profile->description !!}
        </div>
    </div>
</section>
@endif

{{-- 6. ORARI --}}
@if($profile->opening_hours)
@php
    $days = ['mon'=>'Lunedì','tue'=>'Martedì','wed'=>'Mercoledì','thu'=>'Giovedì','fri'=>'Venerdì','sat'=>'Sabato','sun'=>'Domenica'];
    $dayMap = ['Mon'=>'mon','Tue'=>'tue','Wed'=>'wed','Thu'=>'thu','Fri'=>'fri','Sat'=>'sat','Sun'=>'sun'];
    $todayKey = $dayMap[now()->format('D')] ?? '';
@endphp
<section class="py-16 px-4">
    <div class="mx-auto max-w-md">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">Orari</h2>
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            @foreach($days as $key => $label)
                @php $day = $profile->opening_hours[$key] ?? null; @endphp
                <div class="flex items-center justify-between px-4 py-3 {{ $loop->even ? 'bg-gray-50 dark:bg-gray-900/50' : 'bg-white dark:bg-gray-900' }}">
                    <span class="text-sm {{ $key === $todayKey ? 'font-bold' : 'font-medium' }} text-gray-900 dark:text-gray-50">
                        {{ $label }}
                        @if($key === $todayKey)
                            <span class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs text-white"
                                  style="background-color: var(--color-primary)">oggi</span>
                        @endif
                    </span>
                    @if($day && ($day['open'] ?? false))
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $day['morning_open'] ?? '09:00' }}–{{ $day['morning_close'] ?? '13:00' }}
                            &nbsp;/&nbsp;
                            {{ $day['afternoon_open'] ?? '15:00' }}–{{ $day['afternoon_close'] ?? '19:30' }}
                        </span>
                    @else
                        <span class="text-sm text-gray-400">Chiuso</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 7. CONTATTI + MAPPA --}}
@if($profile->phone || $profile->address)
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">Contatti</h2>
        <div class="grid gap-8 lg:grid-cols-2">
            <div class="space-y-4">
                @if($profile->phone)
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <a href="tel:{{ $profile->phone }}" class="text-gray-700 dark:text-gray-300 hover:underline">{{ $profile->phone }}</a>
                    </div>
                @endif
                @if($profile->address)
                    <div class="flex items-start gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-gray-700 dark:text-gray-300">{{ $profile->address }}</span>
                    </div>
                @endif
                @if($profile->website)
                    <div class="flex items-center gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                        <a href="{{ $profile->website }}" target="_blank" class="text-gray-700 dark:text-gray-300 hover:underline">{{ $profile->website }}</a>
                    </div>
                @endif
                <div class="pt-4">
                    <a href="{{ route('booking.create') }}"
                       class="inline-block rounded-md px-6 py-3 text-sm font-semibold text-white shadow-sm"
                       style="background-color: var(--color-primary)">
                        Prenota un appuntamento
                    </a>
                </div>
            </div>
            @if($profile->google_maps_embed)
                <div class="overflow-hidden rounded-xl">
                    <iframe src="{{ $profile->google_maps_embed }}"
                            class="h-64 w-full lg:h-full"
                            style="border:0"
                            allowfullscreen
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- 8. RECENSIONI --}}
@if($reviews->isNotEmpty())
<section class="py-16 px-4">
    <div class="mx-auto max-w-6xl">
        <h2 class="mb-8 text-center text-2xl font-bold text-gray-900 dark:text-gray-50">
            Cosa dicono di noi
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($reviews as $review)
                <article class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm space-y-3">
                    <div class="flex gap-0.5" style="color: var(--color-primary)">
                        @for($i = 0; $i < $review->rating; $i++)
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        @endfor
                    </div>
                    <p class="text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $review->body }}</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-50">— {{ $review->author_name }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 9. POLICY DI CANCELLAZIONE --}}
@if($profile->cancellation_policy)
<section class="bg-gray-50 dark:bg-gray-900/50 py-16 px-4" x-data="{ open: false }">
    <div class="mx-auto max-w-2xl">
        <button @click="open = !open"
                class="flex w-full items-center justify-between rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-left text-base font-semibold text-gray-900 dark:text-gray-50 shadow-sm">
            Politica di cancellazione
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="h-5 w-5 text-gray-400 transition-transform"
                 :class="open && 'rotate-180'"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" x-transition class="mt-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4" style="display:none">
            {{-- {!! !!} accettabile: cancellation_policy è editata solo da admin autenticati --}}
            <div class="prose prose-sm prose-gray dark:prose-invert">
                {!! $profile->cancellation_policy !!}
            </div>
        </div>
    </div>
</section>
@endif

@endsection
