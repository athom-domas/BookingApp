@extends('layouts.app')

@section('title', $profile->name)

@section('main-class', '')

@section('content')

{{-- 1. HERO --}}
<section class="relative flex min-h-[640px] items-center justify-center overflow-hidden"
         style="background-color: var(--color-primary)">
    @if($profile->coverUrl())
        <img src="{{ $profile->coverUrl() }}"
             class="absolute inset-0 h-full w-full object-cover"
             alt="">
        <div class="absolute inset-0 bg-gradient-to-b from-black/65 via-black/45 to-black/65"></div>
    @else
        <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(0,0,0,0.8) 0%, rgba(40,40,40,0.85) 100%)"></div>
    @endif
    <div class="relative z-10 space-y-7 px-6 text-center max-w-3xl">
        @if($profile->logoUrl())
            <img src="{{ $profile->logoUrl() }}"
                 class="mx-auto h-14 object-contain brightness-0 invert opacity-85"
                 alt="{{ $profile->name }}">
        @endif
        <h1 class="font-display text-5xl font-semibold text-white sm:text-6xl lg:text-7xl tracking-tight"
            style="text-shadow: 0 2px 24px rgba(0,0,0,0.35)">
            {{ $profile->name }}
        </h1>
        @if($profile->tagline)
            <p class="mx-auto max-w-lg text-base text-white/70 font-light tracking-wider uppercase">
                {{ $profile->tagline }}
            </p>
        @endif
        <div class="pt-2">
            <a href="{{ route('booking.create') }}"
               class="inline-block border border-white/80 px-10 py-3.5 text-xs font-semibold tracking-[0.2em] uppercase text-white transition-all duration-200 hover:bg-white hover:text-gray-900">
                Prenota ora
            </a>
        </div>
    </div>
</section>

{{-- 2. SERVIZI --}}
@if($services->isNotEmpty())
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-[#f9f6f2] dark:bg-gray-900"
         x-data="{ showAll: false }">
    <div class="mx-auto max-w-6xl">
        <div class="text-center mb-14">
            <div class="mx-auto bg-gray-400 dark:bg-gray-500"></div>
            <h2 class="font-display text-3xl font-semibold text-gray-900 dark:text-gray-50">I nostri servizi</h2>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($services as $index => $service)
                <article
                    @if($index >= 4) x-show="showAll" x-cloak @endif
                    class="bg-white dark:bg-gray-800 p-7 shadow-sm hover:shadow transition-shadow duration-300">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <h3 class="font-display text-xl font-semibold text-gray-900 dark:text-gray-50 leading-tight">
                            {{ $service->name }}
                        </h3>
                        <span class="shrink-0 text-lg font-bold text-gray-900 dark:text-gray-50 tabular-nums">
                            {{ number_format((float) $service->price, 2, ',', '.') }} €
                        </span>
                    </div>
                    @if($service->description)
                        <p class="text-sm leading-6 text-gray-500 dark:text-gray-400 mb-5">
                            {{ $service->description }}
                        </p>
                    @endif
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold tracking-widest uppercase text-gray-400 dark:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $service->duration_minutes }} min
                    </div>
                </article>
            @endforeach
        </div>
        @if($services->count() > 4)
            <div class="mt-10 text-center">
                <button
                    x-show="!showAll"
                    @click="showAll = true"
                    class="text-sm font-semibold tracking-wide text-gray-600 dark:text-gray-400 underline underline-offset-4 hover:text-gray-900 dark:hover:text-white transition-colors">
                    Mostra tutti i servizi ({{ $services->count() }})
                </button>
                <button
                    x-show="showAll" x-cloak
                    @click="showAll = false"
                    class="text-sm font-semibold tracking-wide text-gray-600 dark:text-gray-400 underline underline-offset-4 hover:text-gray-900 dark:hover:text-white transition-colors">
                    Mostra meno
                </button>
            </div>
        @endif
    </div>
</section>
@endif

{{-- 3. TEAM --}}
@if($staff->isNotEmpty())
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-950">
    <div class="mx-auto max-w-6xl">
        <div class="text-center mb-14">
            <div class="mx-auto bg-gray-400 dark:bg-gray-500"></div>
            <h2 class="font-display text-3xl font-semibold text-gray-900 dark:text-gray-50">Il nostro team</h2>
        </div>
        <div class="grid gap-12 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($staff as $member)
                <div class="text-center">
                    @php $avatarUrl = $member->getFirstMediaUrl('avatar', 'thumb'); @endphp
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}"
                             class="mx-auto mb-5 h-28 w-28 rounded-full object-cover shadow-md"
                             alt="{{ $member->name }}">
                    @else
                        <div class="mx-auto mb-5 flex h-28 w-28 items-center justify-center rounded-full text-2xl font-bold text-white shadow-md"
                             style="background-color: var(--color-primary)">
                            {{ strtoupper(mb_substr($member->name, 0, 1)) }}
                        </div>
                    @endif
                    <p class="font-display text-xl font-semibold text-gray-900 dark:text-gray-50 mb-2">{{ $member->name }}</p>
                    @if($member->bio)
                        <p class="text-sm leading-7 text-gray-500 dark:text-gray-400 max-w-xs mx-auto">
                            {{ $member->bio }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 4. GALLERIA --}}
@php $galleryItems = $profile->getMedia('gallery'); @endphp
@if($galleryItems->isNotEmpty())
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-[#f9f6f2] dark:bg-gray-900" x-data="{ lightbox: null }">
    <div class="mx-auto max-w-6xl">
        <div class="text-center mb-14">
            <div class="mx-auto bg-gray-400 dark:bg-gray-500"></div>
            <h2 class="font-display text-3xl font-semibold text-gray-900 dark:text-gray-50">Galleria</h2>
        </div>
        <div class="columns-2 gap-3 sm:columns-3 lg:columns-4">
            @foreach($galleryItems as $item)
                @php $thumbUrl = $item->getUrl('thumb'); $webUrl = $item->getUrl('web'); @endphp
                <div class="mb-3 break-inside-avoid cursor-pointer overflow-hidden"
                     @click="lightbox = '{{ $webUrl }}'">
                    <img src="{{ $thumbUrl }}"
                         class="w-full object-cover transition-all duration-300 hover:scale-105 hover:brightness-90"
                         alt="Galleria {{ $loop->iteration }}">
                </div>
            @endforeach
        </div>
    </div>
    <div x-show="lightbox"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
         @click="lightbox = null"
         @keydown.escape.window="lightbox = null"
         style="display:none">
        <img :src="lightbox" class="max-h-[90vh] max-w-full object-contain">
    </div>
</section>
@endif

{{-- 5. CHI SIAMO --}}
@if($profile->description)
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-950">
    <div class="mx-auto max-w-2xl">
        <div class="text-center mb-14">
            <div class="mx-auto bg-gray-400 dark:bg-gray-500"></div>
            <h2 class="font-display text-3xl font-semibold text-gray-900 dark:text-gray-50">Chi siamo</h2>
        </div>
        <div class="prose prose-gray dark:prose-invert mx-auto prose-p:leading-8 prose-p:text-gray-600 dark:prose-p:text-gray-400">
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
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-[#f9f6f2] dark:bg-gray-900">
    <div class="mx-auto max-w-sm">
        <div class="text-center mb-14">
            <div class="mx-auto bg-gray-400 dark:bg-gray-500"></div>
            <h2 class="font-display text-3xl font-semibold text-gray-900 dark:text-gray-50">Orari</h2>
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($days as $key => $label)
                @php $day = $profile->opening_hours[$key] ?? null; @endphp
                <div class="flex items-center justify-between py-3.5">
                    <span class="text-sm flex items-center gap-2.5 @if($key === $todayKey) font-bold text-gray-900 dark:text-gray-50 @else font-medium text-gray-500 dark:text-gray-400 @endif">
                        {{ $label }}
                        @if($key === $todayKey)
                            <span class="rounded-sm px-1.5 py-0.5 text-[10px] font-bold text-white uppercase tracking-wider"
                                  style="background-color: var(--color-primary)">oggi</span>
                        @endif
                    </span>
                    @if($day && ($day['open'] ?? false))
                        <span class="text-sm tabular-nums @if($key === $todayKey) font-semibold text-gray-900 dark:text-gray-50 @else text-gray-600 dark:text-gray-400 @endif">
                            {{ $day['morning_open'] ?? '09:00' }}–{{ $day['morning_close'] ?? '13:00' }}&ensp;<span class="text-gray-300 dark:text-gray-600">/</span>&ensp;{{ $day['afternoon_open'] ?? '15:00' }}–{{ $day['afternoon_close'] ?? '19:30' }}
                        </span>
                    @else
                        <span class="text-sm italic text-gray-400 dark:text-gray-600">Chiuso</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 7. CONTATTI --}}
@if($profile->phone || $profile->address)
<section class="py-20 px-4 sm:px-6 lg:px-8"
         style="background-color: var(--color-primary)">
    <div class="mx-auto max-w-6xl">
        <div class="text-center mb-14">
            <div class="mx-auto mb-5 h-px w-8 bg-white/30"></div>
            <h2 class="font-display text-3xl font-semibold text-white">Contatti</h2>
        </div>
        <div class="grid gap-12 lg:grid-cols-2">
            <div class="space-y-6">
                @if($profile->phone)
                    <div class="flex items-center gap-4">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <a href="tel:{{ $profile->phone }}" class="text-white/75 hover:text-white transition-colors text-sm">{{ $profile->phone }}</a>
                    </div>
                @endif
                @if($profile->address)
                    <div class="flex items-start gap-4">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <span class="text-white/75 text-sm">{{ $profile->address }}</span>
                    </div>
                @endif
                @if($profile->website)
                    <div class="flex items-center gap-4">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
                        </div>
                        <a href="{{ $profile->website }}" target="_blank" class="text-white/75 hover:text-white transition-colors text-sm">{{ $profile->website }}</a>
                    </div>
                @endif
                <div class="pt-4">
                    <a href="{{ route('booking.create') }}"
                       class="inline-block border border-white/70 px-8 py-3 text-xs font-semibold tracking-[0.18em] uppercase text-white transition-all duration-200 hover:bg-white hover:text-gray-900">
                        Prenota un appuntamento
                    </a>
                </div>
            </div>
            @if($profile->google_maps_embed)
                <div class="overflow-hidden">
                    <iframe src="{{ $profile->google_maps_embed }}"
                            class="h-72 w-full lg:h-full min-h-[240px]"
                            style="border:0; filter: grayscale(20%) brightness(0.9)"
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
<section class="py-20 px-4 sm:px-6 lg:px-8 bg-white dark:bg-gray-950">
    <div class="mx-auto max-w-6xl">
        <div class="text-center mb-14">
            <div class="mx-auto bg-gray-400 dark:bg-gray-500"></div>
            <h2 class="font-display text-3xl font-semibold text-gray-900 dark:text-gray-50">Cosa dicono di noi</h2>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($reviews as $review)
                <article class="relative p-8 bg-[#f9f6f2] dark:bg-gray-900">
                    <div class="absolute top-4 left-6 font-display text-8xl leading-none text-gray-200 dark:text-gray-700 select-none pointer-events-none"
                         aria-hidden="true" style="font-style: italic">"</div>
                    <div class="relative">
                        <div class="flex gap-0.5 mb-5">
                            @for($i = 0; $i < $review->rating; $i++)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"
                                     style="color: var(--color-primary)"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            @endfor
                        </div>
                        <p class="text-sm leading-7 text-gray-600 dark:text-gray-400 mb-6">{{ $review->body }}</p>
                        <p class="text-xs font-bold tracking-widest uppercase text-gray-900 dark:text-gray-50">{{ $review->author_name }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 9. POLICY DI CANCELLAZIONE --}}
@if($profile->cancellation_policy)
<section class="py-16 px-4 sm:px-6 lg:px-8 bg-[#f9f6f2] dark:bg-gray-900" x-data="{ open: false }">
    <div class="mx-auto max-w-2xl">
        <button @click="open = !open"
                class="flex w-full items-center justify-between bg-white dark:bg-gray-800 px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-gray-50 shadow-sm hover:shadow transition-shadow duration-200 tracking-wide">
            Politica di cancellazione
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="h-5 w-5 text-gray-400 transition-transform duration-200"
                 :class="open && 'rotate-180'"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div x-show="open" x-transition class="bg-white dark:bg-gray-800 px-6 py-5 shadow-sm mt-px" style="display:none">
            {{-- {!! !!} accettabile: cancellation_policy è editata solo da admin autenticati --}}
            <div class="prose prose-sm prose-gray dark:prose-invert">
                {!! $profile->cancellation_policy !!}
            </div>
        </div>
    </div>
</section>
@endif

@endsection
