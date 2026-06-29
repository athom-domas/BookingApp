{{-- Variables: $content['title'], $content['subtitle'], $content['cta_label'], $content['image'],
     $settings['show_cta'], $hero_preset_url, $business, $block --}}
@php
    $_editImg = !empty($content['image'])
        ? \Illuminate\Support\Facades\Storage::url($content['image'])
        : ($hero_preset_url ?? null);
@endphp
<section class="sf-hero sf-hero--editorial sf-hero--no-img">
    <div class="sf-inner" style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;width:100%">
        <div class="sf-hero-inner" style="align-items:flex-start;text-align:left;max-width:none">
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
                <p class="sf-hero-tagline" style="margin-left:0;margin-right:0">{{ $content['subtitle'] }}</p>
            @endif
            @if($settings['show_cta'] ?? true)
                <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">{{ $content['cta_label'] ?? 'Prenota ora' }}</a>
            @endif
        </div>
        @if($_editImg)
        <div style="overflow:hidden;border:1px solid var(--sf-border)">
            <img src="{{ $_editImg }}" alt="" loading="eager" fetchpriority="high" width="600" height="500" style="width:100%;height:100%;object-fit:cover;display:block;aspect-ratio:6/5">
        </div>
        @endif
    </div>
</section>
