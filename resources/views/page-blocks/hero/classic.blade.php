{{-- Variables: $content['title'], $content['subtitle'], $content['cta_label'], $content['image'],
     $settings['alignment'], $settings['show_cta'], $business, $block --}}
@php
    $_heroImg = !empty($content['image']) ? \Illuminate\Support\Facades\Storage::url($content['image']) : null;
@endphp
<section class="sf-hero {{ $_heroImg ? 'sf-hero--img' : 'sf-hero--no-img' }}">
    @if($_heroImg)
        <img class="sf-hero-bg" src="{{ $_heroImg }}" alt="" fetchpriority="high" loading="eager" decoding="async" width="1200" height="640">
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
