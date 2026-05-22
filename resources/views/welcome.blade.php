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
                    @if($day && !($day['closed'] ?? false))
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $day['open'] ?? '09:00' }} – {{ $day['close'] ?? '18:00' }}
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

{{-- 10. FOOTER --}}
<footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-8 px-4">
    <div class="mx-auto max-w-6xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                @if($profile->logoUrl())
                    <img src="{{ $profile->logoUrl() }}" class="h-7 object-contain" alt="{{ $profile->name }}">
                @endif
                <span class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $profile->name }}</span>
            </div>
            <div class="flex items-center gap-4">
                @if($profile->instagram_url)
                    <a href="{{ $profile->instagram_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Instagram">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                @endif
                @if($profile->facebook_url)
                    <a href="{{ $profile->facebook_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Facebook">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                @endif
                @if($profile->tiktok_url)
                    <a href="{{ $profile->tiktok_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="TikTok">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.73a4.85 4.85 0 01-1.01-.04z"/></svg>
                    </a>
                @endif
                @if($profile->whatsapp_number)
                    <a href="https://wa.me/{{ $profile->whatsapp_number }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="WhatsApp">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 0C5.373 0 0 5.373 0 12c0 2.117.554 4.107 1.523 5.832L.044 23.956l6.278-1.647A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 11.999 0zm.001 21.818a9.818 9.818 0 01-5.011-1.37l-.36-.213-3.726.977.997-3.634-.234-.374A9.775 9.775 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>
                    </a>
                @endif
            </div>
        </div>
        <p class="text-center text-xs text-gray-400">
            © {{ date('Y') }} {{ $profile->name }}. Tutti i diritti riservati.
        </p>
    </div>
</footer>

@endsection
