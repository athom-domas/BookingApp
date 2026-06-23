@extends('layouts.storefront')

@section('title', $profile->name)

@push('head')
<style>
/* ── HERO ─────────────────────────────────────────────────────────────────── */
.sf-hero {
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; text-align: center;
    padding: 104px 48px; min-height: 400px;
    background: var(--sf-bg); position: relative; overflow: hidden;
}

/* No image: dot-grid texture */
.sf-hero--no-img::before {
    content: '';
    position: absolute; inset: 0; pointer-events: none;
    background-image: radial-gradient(circle, var(--sf-gold) 1px, transparent 1px);
    background-size: 32px 32px;
    opacity: 0.07;
}

/* With background image */
.sf-hero--img {
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}
.sf-hero--img::before {
    content: '';
    position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse at 50% 35%,
        rgba(0,0,0,0.20) 0%,
        rgba(0,0,0,0.60) 55%,
        rgba(0,0,0,0.84) 100%);
}

.sf-hero-inner {
    display: flex; flex-direction: column; align-items: center;
    max-width: 680px; position: relative; z-index: 1;
}

.sf-hero-ornament {
    display: flex; align-items: center; gap: 12px; margin: 20px 0;
}
.sf-hero-line { width: 48px; height: 1px; background: var(--sf-gold); opacity: 0.55; }
.sf-hero-dot  { width: 4px; height: 4px; border-radius: 50%; background: var(--sf-gold); opacity: 0.75; }

.sf-hero-name {
    font-family: var(--sf-font-display);
    font-size: clamp(44px, 6.5vw, 82px);
    color: var(--sf-gold-lt);
    line-height: 1.02; letter-spacing: -0.02em; text-wrap: balance;
}
.sf-hero-tagline {
    font-size: 13px; color: var(--sf-body); line-height: 1.75;
    max-width: 400px; margin: 0 auto 36px; text-wrap: pretty;
}

/* On image bg: always use light text regardless of theme */
.sf-hero--img .sf-hero-name    { color: rgba(255,255,255,0.96); }
.sf-hero--img .sf-hero-tagline { color: rgba(255,255,255,0.65); }
.sf-hero--img .sf-hero-line    { background: rgba(255,255,255,0.45); opacity: 1; }
.sf-hero--img .sf-hero-dot     { background: rgba(255,255,255,0.60); opacity: 1; }
.sf-hero--img .sf-btn {
    background: rgba(255,255,255,0.15);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.45);
    backdrop-filter: blur(6px);
}
.sf-hero--img .sf-btn:hover {
    background: rgba(255,255,255,0.25);
    opacity: 1;
}

@media (max-width: 640px) {
    .sf-hero { padding: 72px 28px; }
}
@media (prefers-reduced-motion: reduce) {
    .sf-hero--no-img::before { display: none; }
}

/* ── SERVICES ─────────────────────────────────────────────────────────────── */
.sf-svc-list { list-style: none; }
.sf-svc-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 24px 0;
    border-bottom: 1px solid var(--sf-border);
}
.sf-svc-item:first-child { border-top: 1px solid var(--sf-border); }
.sf-svc-item-main { flex: 1; min-width: 0; }
.sf-svc-item-name {
    font-family: var(--sf-font-display);
    font-size: 18px;
    color: var(--sf-gold-lt);
    margin-bottom: 5px;
    line-height: 1.2;
}
.sf-svc-item-desc {
    font-size: 12px;
    color: var(--sf-body);
    line-height: 1.65;
}
.sf-svc-item-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
    flex-shrink: 0;
    padding-top: 2px;
}
.sf-svc-item-price {
    font-family: var(--sf-font-display);
    font-size: 20px;
    color: var(--sf-gold);
    white-space: nowrap;
    line-height: 1.1;
}
.sf-svc-item-dur {
    font-size: 11px;
    color: var(--sf-muted);
    white-space: nowrap;
}

/* ── ABOUT ────────────────────────────────────────────────────────────────── */
.sf-about-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 72px;
    align-items: start;
}
.sf-about-text { font-size: 14px; color: var(--sf-body); line-height: 1.85; }
.sf-about-text p + p { margin-top: 16px; }
.sf-about-signature {
    font-family: var(--sf-font-display);
    font-size: 19px;
    color: var(--sf-gold);
    margin-top: 24px;
    font-style: italic;
    line-height: 1.3;
}
.sf-about-photos {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 180px 180px;
    gap: 6px;
}
.sf-about-photo {
    overflow: hidden;
    background: var(--sf-bg-card);
    border: 1px solid var(--sf-border);
}
.sf-about-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}
.sf-about-photo:hover img { transform: scale(1.04); }
.sf-about-photo:first-child { grid-row: span 2; }

@media (max-width: 820px) {
    .sf-about-grid { grid-template-columns: 1fr; gap: 40px; }
    .sf-about-photos { display: none; }
}

/* ── TEAM ─────────────────────────────────────────────────────────────────── */
.sf-team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 36px 40px;
}
.sf-team-card { display: flex; gap: 20px; align-items: flex-start; }
.sf-team-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    border: 1px solid var(--sf-border);
    background: var(--sf-bg-card);
    display: flex; align-items: center; justify-content: center;
}
.sf-team-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sf-team-initial { font-family: var(--sf-font-display); font-size: 28px; color: var(--sf-gold); }
.sf-team-name {
    font-family: var(--sf-font-display);
    font-size: 17px;
    color: var(--sf-gold-lt);
    margin-bottom: 7px;
    line-height: 1.2;
}
.sf-team-bio { font-size: 12px; color: var(--sf-body); line-height: 1.65; }

@media (max-width: 480px) { .sf-team-grid { grid-template-columns: 1fr; } }

/* ── GALLERY ──────────────────────────────────────────────────────────────── */
.sf-gallery-grid { columns: 2; gap: 6px; }
@media (min-width: 640px) { .sf-gallery-grid { columns: 3; } }
@media (min-width: 900px) { .sf-gallery-grid { columns: 4; } }
.sf-gallery-item { break-inside: avoid; margin-bottom: 6px; overflow: hidden; cursor: pointer; }
.sf-gallery-item img {
    width: 100%; object-fit: cover; display: block;
    transition: transform 0.35s ease, filter 0.35s ease;
}
.sf-gallery-item:hover img { transform: scale(1.04); filter: brightness(0.82); }
.sf-lightbox {
    position: fixed; inset: 0; z-index: 200;
    background: rgba(0,0,0,0.92);
    display: flex; align-items: center; justify-content: center; padding: 16px;
}
.sf-lightbox img { max-height: 90vh; max-width: 100%; object-fit: contain; }
.sf-lightbox-close {
    position: absolute; top: 16px; right: 20px;
    color: #e8d5a3; font-size: 28px; cursor: pointer;
    background: none; border: none; line-height: 1; padding: 4px;
}

/* ── HOURS + CONTACTS ─────────────────────────────────────────────────────── */
.sf-info-grid { display: grid; gap: 72px; }
.sf-info-grid--dual   { grid-template-columns: 1fr 1fr; }
.sf-info-grid--single { grid-template-columns: 1fr; max-width: 520px; }

.sf-hours-list { list-style: none; }
.sf-hours-item { padding: 13px 0; border-bottom: 1px solid var(--sf-border); }
.sf-hours-item:last-child { border-bottom: none; }
.sf-hours-day {
    font-size: 9px; letter-spacing: 2.5px; text-transform: uppercase;
    color: var(--sf-muted); margin-bottom: 3px;
    display: flex; align-items: center; gap: 8px;
}
.sf-today-badge {
    background: var(--sf-gold); color: var(--sf-btn-fg);
    font-size: 7px; letter-spacing: 1.5px; font-weight: 700;
    padding: 2px 6px; text-transform: uppercase; border-radius: var(--sf-radius);
}
.sf-hours-time { font-size: 13px; color: var(--sf-body); }
.sf-hours-item.is-today .sf-hours-day { color: var(--sf-gold); }
.sf-hours-item.is-today .sf-hours-time { color: var(--sf-gold-lt); font-weight: 500; }
.sf-hours-item.is-closed .sf-hours-time { color: var(--sf-muted); font-style: italic; }

.sf-contact-list { list-style: none; }
.sf-contact-item {
    display: flex; gap: 16px; padding: 13px 0;
    border-bottom: 1px solid var(--sf-border);
    font-size: 13px; color: var(--sf-body); align-items: flex-start;
}
.sf-contact-item:last-child { border-bottom: none; }
.sf-contact-ico { color: var(--sf-gold); flex-shrink: 0; margin-top: 2px; opacity: 0.65; width: 16px; }
.sf-contact-item a { color: inherit; text-decoration: none; }
.sf-contact-item a:hover { color: var(--sf-gold); }
.sf-map-wrap { margin-top: 24px; overflow: hidden; border: 1px solid var(--sf-border); height: 220px; }
.sf-map-wrap iframe { width: 100%; height: 100%; border: 0; display: block; }

@media (max-width: 768px) { .sf-info-grid { grid-template-columns: 1fr; gap: 48px; } }

/* ── REVIEWS ──────────────────────────────────────────────────────────────── */
.sf-reviews-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.sf-review-card {
    background: var(--sf-bg-card); border: 1px solid var(--sf-border);
    padding: 28px 24px; position: relative; overflow: hidden;
}
.sf-review-quote {
    position: absolute; top: 10px; left: 14px;
    font-family: var(--sf-font-display);
    font-size: 80px; line-height: 1; color: var(--sf-gold);
    opacity: 0.07; pointer-events: none; font-style: italic; user-select: none;
}
.sf-review-stars { display: flex; gap: 3px; margin-bottom: 14px; }
.sf-review-stars span { color: var(--sf-gold); font-size: 11px; }
.sf-review-body { font-size: 13px; color: var(--sf-body); line-height: 1.75; margin-bottom: 20px; position: relative; }
.sf-review-author { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--sf-gold-lt); position: relative; }
.sf-review-cta { text-align: center; margin-top: 40px; }

@media (max-width: 820px) { .sf-reviews-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 540px) { .sf-reviews-grid { grid-template-columns: 1fr; } }

/* ── ACCORDION ────────────────────────────────────────────────────────────── */
.sf-accordion { border: 1px solid var(--sf-border); }
.sf-accordion-btn {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    padding: 18px 24px; background: var(--sf-bg-card); border: none;
    font-size: 10px; letter-spacing: 2px; text-transform: uppercase;
    color: var(--sf-gold-lt); cursor: pointer; text-align: left;
    transition: background 0.2s;
}
.sf-accordion-btn:hover { background: rgba(201,169,110,0.04); }
.sf-accordion-chevron {
    flex-shrink: 0; color: var(--sf-gold);
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.sf-accordion-chevron.open { transform: rotate(180deg); }
.sf-accordion-body-grid {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.35s cubic-bezier(0.22, 1, 0.36, 1);
}
.sf-accordion-body-grid > div { overflow: hidden; }
.sf-accordion-body {
    padding: 20px 24px; border-top: 1px solid var(--sf-border);
    background: var(--sf-bg-card); font-size: 13px; color: var(--sf-body); line-height: 1.8;
}
.sf-accordion-body a { color: var(--sf-gold); }

/* ── CTA ──────────────────────────────────────────────────────────────────── */
.sf-cta { text-align: center; border-top: 1px solid var(--sf-border); }
.sf-cta .sf-rule { margin-left: auto; margin-right: auto; }
.sf-cta p { font-size: 14px; color: var(--sf-body); max-width: 400px; margin: 0 auto 40px; line-height: 1.75; }

/* ── HEADING UTILITIES ────────────────────────────────────────────────────── */
.sf-heading { text-wrap: balance; }
.sf-heading--sm { font-size: clamp(22px, 3vw, 30px); }
.sf-subheading { font-size: clamp(16px, 2vw, 20px); font-weight: 600; margin-bottom: 20px; opacity: .75; }
.sf-subheading--spaced { margin-top: 48px; }
.sf-svc-cta { margin-top: 44px; }

/* ── PAGE NAV ─────────────────────────────────────────────────────────────── */
.sf-page-nav {
    background: var(--sf-bg-alt);
    border-bottom: 1px solid var(--sf-border);
    padding: 0 48px;
}
.sf-page-nav-list {
    list-style: none;
    display: flex;
    gap: 0;
    overflow-x: auto;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
    max-width: 1080px;
    margin: 0 auto;
}
.sf-page-nav-list::-webkit-scrollbar { display: none; }
.sf-page-nav-link {
    display: block;
    padding: 13px 18px;
    font-size: 10px;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    color: var(--sf-body);
    text-decoration: none;
    white-space: nowrap;
    border-bottom: 1.5px solid transparent;
    transition: color 0.2s, border-color 0.2s;
}
.sf-page-nav-link:hover,
.sf-page-nav-link.active {
    color: var(--sf-gold);
    border-bottom-color: var(--sf-gold);
}
@media (max-width: 768px) { .sf-page-nav { padding: 0 20px; } }

/* ── REDUCED MOTION ───────────────────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .sf-about-photo img,
    .sf-gallery-item img { transition: none; }
    .sf-accordion-body-grid,
    .sf-accordion-chevron { transition: none; }
}
</style>
@endpush

@section('content')

@php
    $galleryItems   = $profile->getMedia('gallery');
    $portfolioItems = $profile->getMedia('portfolio');
    $days   = ['mon'=>'Lunedì','tue'=>'Martedì','wed'=>'Mercoledì','thu'=>'Giovedì','fri'=>'Venerdì','sat'=>'Sabato','sun'=>'Domenica'];
    $dayMap = ['Mon'=>'mon','Tue'=>'tue','Wed'=>'wed','Thu'=>'thu','Fri'=>'fri','Sat'=>'sat','Sun'=>'sun'];
    $todayKey = $dayMap[now()->format('D')] ?? '';
    $hasGallerySection = $portfolioItems->isNotEmpty();
    $navLinks = array_filter([
        $services->isNotEmpty()                                  ? ['href' => '#servizi',   'label' => 'Servizi']        : null,
        $profile->description                                    ? ['href' => '#salone',    'label' => 'Il salone']      : null,
        $staff->isNotEmpty()                                     ? ['href' => '#team',      'label' => 'Team']           : null,
        $hasGallerySection                                       ? ['href' => '#galleria',  'label' => 'Galleria']       : null,
        ($profile->opening_hours || $profile->phone || $profile->address) ? ['href' => '#contatti', 'label' => 'Orari & contatti'] : null,
        $reviews->isNotEmpty()                                    ? ['href' => '#recensioni','label' => 'Recensioni']     : null,
    ]);
@endphp

{{-- 1. HERO --}}
@php $_heroImg = $profile->heroImageUrl(); @endphp
<section class="sf-hero {{ $_heroImg ? 'sf-hero--img' : 'sf-hero--no-img' }}"
    @if($_heroImg) style="background-image:url('{{ $_heroImg }}')" @endif>
    <div class="sf-hero-inner">
        <div class="sf-hero-ornament">
            <span class="sf-hero-line"></span>
            <span class="sf-hero-dot"></span>
            <span class="sf-hero-line"></span>
        </div>
        <h1 class="sf-hero-name">{{ $profile->name }}</h1>
        <div class="sf-hero-ornament">
            <span class="sf-hero-line"></span>
            <span class="sf-hero-dot"></span>
            <span class="sf-hero-line"></span>
        </div>
        @if($profile->tagline)
            <p class="sf-hero-tagline">{{ $profile->tagline }}</p>
        @endif
        <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">{{ $profile->bookingButtonLabel() }}</a>
    </div>
</section>

{{-- PAGE NAV --}}
@if(count($navLinks) > 2)
<nav class="sf-page-nav" aria-label="Sezioni della pagina">
    <ul class="sf-page-nav-list">
        @foreach($navLinks as $link)
            <li><a href="{{ $link['href'] }}" class="sf-page-nav-link">{{ $link['label'] }}</a></li>
        @endforeach
    </ul>
</nav>
@endif

{{-- 2. SERVIZI --}}
@if($services->isNotEmpty())
<section class="sf-section-alt" id="servizi">
    <div class="sf-inner">
        <h2 class="sf-heading">I <em>servizi</em></h2>
        <div class="sf-rule"></div>
        <ul class="sf-svc-list">
            @foreach($services as $service)
            <li class="sf-svc-item">
                <div class="sf-svc-item-main">
                    <div class="sf-svc-item-name">{{ $service->name }}</div>
                    @if($service->description)
                        <div class="sf-svc-item-desc">{{ $service->description }}</div>
                    @endif
                </div>
                <div class="sf-svc-item-meta">
                    <span class="sf-svc-item-price">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                    <span class="sf-svc-item-dur">{{ $service->duration_minutes }}&thinsp;min</span>
                </div>
            </li>
            @endforeach
        </ul>
        <div class="sf-svc-cta">
            <a href="{{ route('booking.create') }}" class="sf-btn">Prenota ora</a>
        </div>
    </div>
</section>
@endif

{{-- 3. CHI SIAMO --}}
@if($profile->description)
<section class="sf-section" id="salone">
    <div class="sf-inner">
        <div class="sf-about-grid">
            <div>
                <h2 class="sf-heading">Il <em>salone</em></h2>
                <div class="sf-rule"></div>
                <div class="sf-about-text">
                    {!! $profile->description !!}
                </div>
                @if($profile->owner_signature)
                    <p class="sf-about-signature">{{ $profile->owner_signature }}</p>
                @endif
            </div>
            @if($galleryItems->isNotEmpty())
            <div class="sf-about-photos">
                @foreach($galleryItems->take(3) as $item)
                    <div class="sf-about-photo">
                        <img src="{{ $item->getUrl('web') }}" alt="" loading="lazy">
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- 4. TEAM --}}
@if($staff->isNotEmpty())
<section class="sf-section-alt" id="team">
    <div class="sf-inner">
        <h2 class="sf-heading">Il team</h2>
        <div class="sf-rule"></div>
        <div class="sf-team-grid">
            @foreach($staff as $member)
            @php $avatarUrl = $member->getFirstMediaUrl('avatar', 'thumb'); @endphp
            <div class="sf-team-card">
                <div class="sf-team-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $member->name }}" loading="lazy">
                    @else
                        <span class="sf-team-initial" aria-hidden="true">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div>
                    <div class="sf-team-name">{{ $member->name }}</div>
                    @if($member->bio)
                        <div class="sf-team-bio">{{ $member->bio }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 5. GALLERIA --}}
@if($hasGallerySection)
<section class="sf-section" id="galleria" x-data="{ lightbox: null }">
    <div class="sf-inner">
        <h2 class="sf-heading">Galleria</h2>
        <div class="sf-rule"></div>
        <div class="sf-gallery-grid">
            @foreach($portfolioItems as $item)
            @php $webUrl = $item->getUrl('web'); @endphp
            <div class="sf-gallery-item" @click="lightbox = '{{ $webUrl }}'">
                <img src="{{ $webUrl }}" alt="Galleria {{ $loop->iteration }}" loading="lazy">
            </div>
            @endforeach
        </div>
    </div>
    <div x-show="lightbox"
         x-transition.opacity
         x-cloak
         class="sf-lightbox"
         @click="lightbox = null"
         @keydown.escape.window="lightbox = null"
    >
        <button class="sf-lightbox-close" @click.stop="lightbox = null" aria-label="Chiudi">×</button>
        <img :src="lightbox" @click.stop alt="">
    </div>
</section>
@endif

{{-- 6. ORARI + CONTATTI --}}
@if($profile->opening_hours || $profile->phone || $profile->address)
<section class="sf-section-alt" id="contatti">
    <div class="sf-inner">
        <div class="sf-info-grid {{ $profile->opening_hours ? 'sf-info-grid--dual' : 'sf-info-grid--single' }}">

            @if($profile->opening_hours)
            <div>
                <h2 class="sf-heading sf-heading--sm">Orari di <em>apertura</em></h2>
                <div class="sf-rule"></div>
                <ul class="sf-hours-list">
                    @foreach($days as $key => $label)
                    @php
                        $day     = $profile->opening_hours[$key] ?? null;
                        $dayType = $day['type'] ?? null;
                        $isOpen  = $day && in_array($dayType, ['split', 'continuous']);
                    @endphp
                    <li class="sf-hours-item {{ $key === $todayKey ? 'is-today' : '' }} {{ !$isOpen ? 'is-closed' : '' }}">
                        <div class="sf-hours-day">
                            {{ $label }}
                            @if($key === $todayKey)
                                <span class="sf-today-badge">oggi</span>
                            @endif
                        </div>
                        <div class="sf-hours-time">
                            @if($isOpen)
                                @if($dayType === 'continuous')
                                    {{ $day['open_time'] }}–{{ $day['close_time'] }}
                                @else
                                    {{ $day['morning_open'] ?? '09:00' }}–{{ $day['morning_close'] ?? '13:00' }}
                                    @if(!empty($day['afternoon_open']) && !empty($day['afternoon_close']))
                                        &thinsp;/&thinsp;{{ $day['afternoon_open'] }}–{{ $day['afternoon_close'] }}
                                    @endif
                                @endif
                            @else
                                Chiuso
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div>
                <h2 class="sf-heading sf-heading--sm">Dove <em>trovarci</em></h2>
                <div class="sf-rule"></div>
                <ul class="sf-contact-list">
                    @if($profile->address)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico" aria-hidden="true">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        </span>
                        <span>{{ $profile->address }}</span>
                    </li>
                    @endif
                    @if($profile->phone)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico" aria-hidden="true">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                        </span>
                        <a href="tel:{{ $profile->phone }}">{{ $profile->phone }}</a>
                    </li>
                    @endif
                    @if($profile->whatsapp_number)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico" aria-hidden="true">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/></svg>
                        </span>
                        <a href="https://wa.me/{{ $profile->whatsapp_number }}" target="_blank" rel="noopener">WhatsApp</a>
                    </li>
                    @endif
                    @if($profile->instagram_url)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico" aria-hidden="true">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </span>
                        <a href="{{ $profile->instagram_url }}" target="_blank" rel="noopener">Instagram</a>
                    </li>
                    @endif
                    @if($profile->facebook_url)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico" aria-hidden="true">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </span>
                        <a href="{{ $profile->facebook_url }}" target="_blank" rel="noopener">Facebook</a>
                    </li>
                    @endif
                </ul>
                @if($profile->google_maps_embed)
                <div class="sf-map-wrap">
                    <iframe src="{{ $profile->google_maps_embed }}"
                        loading="lazy"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Mappa {{ $profile->name }}"></iframe>
                </div>
                @endif
            </div>

        </div>
    </div>
</section>
@endif

{{-- 7. RECENSIONI --}}
@if($reviews->isNotEmpty())
<section class="sf-section" id="recensioni">
    <div class="sf-inner">
        <h2 class="sf-heading">Recensioni</h2>
        @if($reviews->isNotEmpty())
        <div class="sf-reviews-grid">
            @foreach($reviews as $review)
            <div class="sf-review-card">
                <div class="sf-review-quote" aria-hidden="true">"</div>
                <div class="sf-review-stars" aria-label="Valutazione {{ $review->rating }} su 5">
                    @for($i = 1; $i <= 5; $i++)
                        <span aria-hidden="true">{{ $i <= $review->rating ? '★' : '☆' }}</span>
                    @endfor
                </div>
                <p class="sf-review-body">{{ $review->body }}</p>
                <div class="sf-review-author">{{ $review->author_name }}</div>
            </div>
            @endforeach
        </div>
        @endif

    </div>
</section>
@endif

{{-- 8. POLITICA DI CANCELLAZIONE --}}
@if(true)
<section class="sf-section-alt">
    <div class="sf-inner" style="max-width:720px">
        <div class="sf-accordion" x-data="{ open: false }">
            <button
                class="sf-accordion-btn"
                @click="open = !open"
                :aria-expanded="open.toString()"
                type="button"
            >
                Politica di cancellazione
                <svg class="sf-accordion-chevron"
                     :class="{ open: open }"
                     width="16" height="16"
                     fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.5"
                     aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="sf-accordion-body-grid"
                 :style="open ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                <div>
                    <div class="sf-accordion-body">
                        {!! $profile->cancellationPolicyHtml() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- 9. CTA --}}
<section class="sf-section sf-cta">
    <div class="sf-inner">
        <h2 class="sf-heading">Prenota il tuo <em>appuntamento</em></h2>
        <div class="sf-rule"></div>
        <p>Scegli il servizio, il giorno e l'orario che preferisci. La conferma arriva subito.</p>
        <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">Prenota ora</a>
    </div>
</section>

@push('scripts')
<script>
(function () {
    var links = document.querySelectorAll('.sf-page-nav-link');
    if (!links.length) return;

    var linkMap = {};
    links.forEach(function (a) {
        var href = a.getAttribute('href');
        if (href && href.charAt(0) === '#') linkMap[href.slice(1)] = a;
    });

    var sections = Array.from(document.querySelectorAll('[id]')).filter(function (el) {
        return linkMap[el.id];
    });
    if (!sections.length) return;

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                links.forEach(function (a) { a.classList.remove('active'); });
                var link = linkMap[entry.target.id];
                if (link) link.classList.add('active');
            }
        });
    }, { rootMargin: '-15% 0px -75% 0px', threshold: 0 });

    sections.forEach(function (s) { observer.observe(s); });
})();
</script>
@endpush

@endsection
