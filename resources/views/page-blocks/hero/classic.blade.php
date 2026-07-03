{{-- Variables: $content['title'], $content['subtitle'], $content['cta_label'], $content['image'],
     $settings['show_cta'], $hero_preset_url, $business, $block --}}
@php
    $_heroImg       = !empty($content['image'])
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($content['image'])
        : ($hero_preset_url ?? null);
    $_heroImgMobile = !empty($content['image_mobile'])
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($content['image_mobile'])
        : null;
@endphp
@if($_heroImg)
    @push('preload')
        @if($_heroImgMobile)
            <link rel="preload" as="image" href="{{ $_heroImgMobile }}" media="(max-width: 640px)" fetchpriority="high">
            <link rel="preload" as="image" href="{{ $_heroImg }}" media="(min-width: 641px)" fetchpriority="high">
        @else
            <link rel="preload" as="image" href="{{ $_heroImg }}" fetchpriority="high">
        @endif
    @endpush
@endif
<section class="sf-hero {{ $_heroImg ? 'sf-hero--img' : 'sf-hero--no-img' }}">
    @if($_heroImg)
        <picture>
            @if($_heroImgMobile)
                <source media="(max-width: 640px)" srcset="{{ $_heroImgMobile }}">
            @endif
            <img class="sf-hero-bg" src="{{ $_heroImg }}" alt="" fetchpriority="high" loading="eager" decoding="async" width="1200" height="640">
        </picture>
        <div class="sf-hero-overlay"></div>
    @endif
    <div class="sf-hero-inner">
        <div class="sf-hero-ornament">
            <span class="sf-hero-line"></span>
            <span class="sf-hero-dot"></span>
            <span class="sf-hero-line"></span>
        </div>
        <h1 class="sf-hero-name">{{ $content['title'] ?? $business->name }}</h1>
        <div class="sf-hero-ornament">
            <span class="sf-hero-line"></span>
            <span class="sf-hero-dot"></span>
            <span class="sf-hero-line"></span>
        </div>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline">{{ $content['subtitle'] }}</p>
        @endif
        @if($settings['show_cta'] ?? true)
            <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">{{ $content['cta_label'] ?? 'Prenota ora' }}</a>
        @endif
    </div>
</section>
