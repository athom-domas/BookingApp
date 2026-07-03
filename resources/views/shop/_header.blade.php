@php
    $_hImg = !empty($content['image'])
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($content['image'])
        : ($hero_preset_url ?? null);
    $_hImgMobile = !empty($content['image_mobile'])
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($content['image_mobile'])
        : null;
    $_hHasImg  = (bool) $_hImg;
    $_hVariant = $variant ?? 'classic';
    // Centered variant never shows an image — solid bg only
    $_hShowImg = $_hVariant !== 'centered' && $_hHasImg;
@endphp

@if($_hShowImg)
    @push('preload')
        @if($_hImgMobile)
            <link rel="preload" as="image" href="{{ $_hImgMobile }}" media="(max-width: 640px)" fetchpriority="high">
            <link rel="preload" as="image" href="{{ $_hImg }}" media="(min-width: 641px)" fetchpriority="high">
        @else
            <link rel="preload" as="image" href="{{ $_hImg }}" fetchpriority="high">
        @endif
    @endpush
@endif

@once('sf-shop-hdr-css')
<style>
.sf-shop-hdr {
    position: relative; overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    padding: 68px 48px; min-height: 260px;
    text-align: center;
    background: var(--sf-bg-alt);
}
.sf-shop-hdr--no-img::before {
    content: '';
    position: absolute; inset: 0; pointer-events: none;
    background-image: radial-gradient(circle, var(--sf-gold) 1px, transparent 1px);
    background-size: 32px 32px; opacity: 0.07;
}
.sf-shop-hdr-bg {
    position: absolute; inset: 0; width: 100%; height: 100%;
    object-fit: cover; object-position: center; z-index: 0;
}
.sf-shop-hdr-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(160deg, rgba(0,0,0,0.28) 0%, rgba(0,0,0,0.72) 100%);
    z-index: 0;
}
.sf-shop-hdr-inner {
    position: relative; z-index: 1;
    display: flex; flex-direction: column; align-items: center;
    max-width: 640px;
}
.sf-shop-hdr-title {
    font-family: var(--sf-font-display);
    font-size: clamp(26px, 4vw, 50px);
    color: var(--sf-gold-lt); line-height: 1.06;
    letter-spacing: -0.02em; text-wrap: balance;
    margin: 0;
}
.sf-shop-hdr-ornament {
    display: flex; align-items: center; gap: 12px; margin: 12px 0;
}
.sf-shop-hdr-inner > .sf-shop-hdr-ornament:first-child { margin-top: 0; }
.sf-shop-hdr-line { width: 32px; height: 1px; background: var(--sf-gold); opacity: 0.55; }
.sf-shop-hdr-dot  { width: 4px; height: 4px; border-radius: 50%; background: var(--sf-gold); opacity: 0.8; }
.sf-shop-hdr-sub {
    font-family: var(--sf-font-body);
    font-size: 13px; color: var(--sf-body); line-height: 1.7;
    max-width: 420px; margin: 0 auto; text-wrap: pretty;
}
.sf-shop-hdr--img .sf-shop-hdr-title { color: rgba(255,255,255,0.96); }
.sf-shop-hdr--img .sf-shop-hdr-sub   { color: rgba(255,255,255,0.70); }
.sf-shop-hdr--img .sf-shop-hdr-line  { background: rgba(255,255,255,0.45); opacity: 1; }
.sf-shop-hdr--img .sf-shop-hdr-dot   { background: rgba(255,255,255,0.65); opacity: 1; }

/* Editorial */
.sf-shop-hdr--editorial {
    text-align: left; justify-content: stretch;
    padding: 0; min-height: unset;
}
.sf-shop-hdr--editorial .sf-shop-hdr-wrap {
    display: grid; grid-template-columns: 1fr 1fr;
    align-items: stretch; width: 100%;
}
.sf-shop-hdr--editorial .sf-shop-hdr-inner {
    align-items: flex-start; padding: 64px 52px;
    max-width: none; min-height: 260px; justify-content: center;
}
.sf-shop-hdr--editorial .sf-shop-hdr-ornament { justify-content: flex-start; }
.sf-shop-hdr--editorial .sf-shop-hdr-sub { margin: 0; }
.sf-shop-hdr--editorial .sf-shop-hdr-img-col { overflow: hidden; }
.sf-shop-hdr--editorial .sf-shop-hdr-img-col img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}

@media (max-width: 768px) {
    .sf-shop-hdr { padding: 52px 24px; min-height: 210px; }
    .sf-shop-hdr--editorial .sf-shop-hdr-wrap { grid-template-columns: 1fr; }
    .sf-shop-hdr--editorial .sf-shop-hdr-img-col { display: none; }
    .sf-shop-hdr--editorial .sf-shop-hdr-inner {
        align-items: center; padding: 52px 24px; text-align: center;
    }
    .sf-shop-hdr--editorial .sf-shop-hdr-ornament { justify-content: center; }
    .sf-shop-hdr--editorial .sf-shop-hdr-sub { margin: 0 auto; }
}
@media (prefers-reduced-motion: reduce) {
    .sf-shop-hdr--no-img::before { display: none; }
}
</style>
@endonce

@if($_hVariant === 'editorial')
<section class="sf-shop-hdr sf-shop-hdr--editorial">
    <div class="sf-shop-hdr-wrap">
        <div class="sf-shop-hdr-inner">
            <div class="sf-shop-hdr-ornament">
                <span class="sf-shop-hdr-line"></span>
                <span class="sf-shop-hdr-dot"></span>
                <span class="sf-shop-hdr-line"></span>
            </div>
            <h1 class="sf-shop-hdr-title">{{ $content['title'] ?? 'Prodotti' }}</h1>
            <div class="sf-shop-hdr-ornament">
                <span class="sf-shop-hdr-line"></span>
                <span class="sf-shop-hdr-dot"></span>
                <span class="sf-shop-hdr-line"></span>
            </div>
            @if(!empty($content['subtitle']))
                <p class="sf-shop-hdr-sub">{{ $content['subtitle'] }}</p>
            @endif
        </div>
        @if($_hShowImg)
        <div class="sf-shop-hdr-img-col">
            <img src="{{ $_hImg }}" alt="" loading="eager" fetchpriority="high" decoding="async">
        </div>
        @else
        <div class="sf-shop-hdr-img-col" style="background:var(--sf-bg-alt);min-height:260px;position:relative;">
            <div style="position:absolute;inset:0;background-image:radial-gradient(circle,var(--sf-gold) 1px,transparent 1px);background-size:32px 32px;opacity:0.07;pointer-events:none"></div>
        </div>
        @endif
    </div>
</section>
@else
<section class="sf-shop-hdr {{ $_hShowImg ? 'sf-shop-hdr--img' : 'sf-shop-hdr--no-img' }}">
    @if($_hShowImg)
        <picture>
            @if($_hImgMobile)
                <source media="(max-width: 640px)" srcset="{{ $_hImgMobile }}">
            @endif
            <img class="sf-shop-hdr-bg" src="{{ $_hImg }}" alt="" fetchpriority="high" loading="eager" decoding="async">
        </picture>
        <div class="sf-shop-hdr-overlay"></div>
    @endif
    <div class="sf-shop-hdr-inner">
        <div class="sf-shop-hdr-ornament">
            <span class="sf-shop-hdr-line"></span>
            <span class="sf-shop-hdr-dot"></span>
            <span class="sf-shop-hdr-line"></span>
        </div>
        <h1 class="sf-shop-hdr-title">{{ $content['title'] ?? 'Prodotti' }}</h1>
        <div class="sf-shop-hdr-ornament">
            <span class="sf-shop-hdr-line"></span>
            <span class="sf-shop-hdr-dot"></span>
            <span class="sf-shop-hdr-line"></span>
        </div>
        @if(!empty($content['subtitle']))
            <p class="sf-shop-hdr-sub">{{ $content['subtitle'] }}</p>
        @endif
    </div>
</section>
@endif
