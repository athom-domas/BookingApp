@extends('layouts.storefront')

@section('title', $profile->name)

@push('head')
<style>
    /* ── HERO ── */
    .sf-hero {
        position: relative; height: 100svh; min-height: 560px;
        display: flex; align-items: center; justify-content: center; text-align: center;
        overflow: hidden;
    }
    .sf-hero-bg {
        position: absolute; inset: 0;
        background: linear-gradient(160deg, #1f160a 0%, #2a1c0c 40%, #1a1208 70%, #0d0b08 100%);
    }
    html.sf-light .sf-hero-bg {
        background: linear-gradient(160deg, #e8ddd0 0%, #ddd0c0 40%, #e8ddd0 70%, #f7f3ec 100%);
    }
    @if($profile->coverUrl())
    .sf-hero-cover {
        position: absolute; inset: 0; background-size: cover; background-position: center;
        background-image: url('{{ $profile->coverUrl() }}');
    }
    @endif
    .sf-hero-overlay {
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 50% 40%, rgba(10,8,6,0.25) 0%, rgba(10,8,6,0.65) 60%, rgba(10,8,6,0.88) 100%);
    }
    html.sf-light .sf-hero-content { color: #ffffff; }
    html.sf-light .sf-hero-content .sf-hero-eyebrow { color: rgba(255,255,255,0.7); }
    html.sf-light .sf-hero-content .sf-hero-title { color: #ffffff; }
    html.sf-light .sf-hero-content .sf-hero-title em { color: rgba(255,255,255,0.8); }
    html.sf-minimal .sf-hero-content { color: #ffffff; }
    html.sf-minimal .sf-hero-content .sf-hero-eyebrow { color: rgba(255,255,255,0.6); }
    html.sf-minimal .sf-hero-content .sf-hero-title { color: #ffffff; }
    html.sf-minimal .sf-hero-content .sf-hero-title em { color: rgba(255,255,255,0.75); }
    .sf-hero-content { position: relative; z-index: 2; max-width: 600px; padding: 0 24px; }
    .sf-hero-eyebrow {
        font-size: 9px; letter-spacing: 5px; color: var(--sf-gold);
        text-transform: uppercase; margin-bottom: 24px;
    }
    .sf-hero-title {
        font-family: var(--sf-font-display);
        font-size: clamp(44px, 9vw, 80px);
        line-height: 1.0; color: var(--sf-gold-lt); margin-bottom: 8px;
    }
    .sf-hero-title em { color: var(--sf-gold); display: block; font-style: italic; }
    .sf-hero-rule { width: 36px; height: 1px; background: var(--sf-gold); margin: 24px auto; opacity: 0.5; }
    .sf-hero-scroll {
        position: absolute; bottom: 28px; left: 50%; transform: translateX(-50%);
        font-size: 9px; letter-spacing: 3px; color: var(--sf-muted); text-transform: uppercase;
    }
    .sf-hero-scroll::before {
        content: ''; display: block; width: 1px; height: 28px;
        background: var(--sf-gold); opacity: 0.35; margin: 0 auto 10px;
    }

    /* ── SERVICES CAROUSEL ── */
    .sf-svc-header {
        display: flex; align-items: flex-end; justify-content: space-between;
    }
    .sf-svc-nav {
        display: flex; gap: 8px; align-items: center;
        padding-bottom: 44px; flex-shrink: 0;
    }
    .sf-svc-nav button {
        width: 38px; height: 38px;
        border: 1px solid var(--sf-border); background: var(--sf-bg-card);
        color: var(--sf-gold); cursor: pointer; font-size: 15px;
        display: flex; align-items: center; justify-content: center;
        transition: border-color 0.2s, background 0.2s; flex-shrink: 0;
    }
    .sf-svc-nav button:hover:not(:disabled) { border-color: var(--sf-gold); background: rgba(201,169,110,0.06); }
    .sf-svc-nav button:disabled { opacity: 0.3; cursor: default; }
    .sf-svc-dots { display: flex; gap: 6px; align-items: center; padding: 0 6px; }
    .sf-svc-dots span {
        width: 5px; height: 5px; border-radius: 50%;
        background: var(--sf-border); transition: background 0.25s; cursor: pointer;
    }
    .sf-svc-dots span.active { background: var(--sf-gold); }
    .sf-svc-wrap { overflow: hidden; }
    .sf-svc-track {
        display: flex;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .sf-svc-card {
        flex: 0 0 25%; min-width: 0;
        background: var(--sf-bg-card);
        border-right: 1px solid var(--sf-border);
        border-bottom: 1px solid var(--sf-border);
        padding: 28px 22px;
        transition: background 0.2s;
    }
    .sf-svc-card:first-child { border-left: 1px solid var(--sf-border); border-top: 1px solid var(--sf-border); }
    .sf-svc-card:nth-child(n+2) { border-top: 1px solid var(--sf-border); }
    .sf-svc-card:hover { background: rgba(201,169,110,0.04); }
    .sf-svc-name {
        font-family: 'DM Serif Display', serif;
        font-size: 18px; color: var(--sf-gold-lt); margin-bottom: 8px;
    }
    .sf-svc-desc { font-size: 12px; color: var(--sf-body); line-height: 1.65; margin-bottom: 20px; }
    .sf-svc-footer {
        display: flex; justify-content: space-between; align-items: center;
        padding-top: 14px; border-top: 1px solid var(--sf-border);
    }
    .sf-svc-dur { font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--sf-muted); }
    .sf-svc-price { font-family: 'DM Serif Display', serif; font-size: 20px; color: var(--sf-gold); }

    @media (max-width: 820px) {
        .sf-svc-card { flex: 0 0 50%; }
    }
    @media (max-width: 540px) {
        .sf-svc-card { flex: 0 0 88%; }
    }

    /* ── ABOUT ── */
    .sf-about-grid {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 64px; align-items: center;
    }
    .sf-about-text { font-size: 14px; color: var(--sf-body); line-height: 1.85; }
    .sf-about-text p + p { margin-top: 16px; }
    .sf-about-photos {
        display: grid; grid-template-columns: 1fr 1fr;
        grid-template-rows: 170px 170px; gap: 6px;
    }
    .sf-about-photo {
        overflow: hidden; background: var(--sf-bg-card);
        border: 1px solid var(--sf-border);
    }
    .sf-about-photo img { width: 100%; height: 100%; object-fit: cover; }
    .sf-about-photo:first-child { grid-row: span 2; }

    @media (max-width: 768px) {
        .sf-about-grid { grid-template-columns: 1fr; gap: 40px; }
        .sf-about-photos { display: none; }
    }

    /* ── TEAM ── */
    .sf-team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 40px 32px;
    }
    .sf-team-card { text-align: center; }
    .sf-team-avatar {
        width: 120px; height: 120px; border-radius: 50%;
        background: var(--sf-bg-card); border: 1px solid var(--sf-border);
        margin: 0 auto 18px; overflow: hidden;
        display: flex; align-items: center; justify-content: center;
    }
    .sf-team-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .sf-team-initial {
        font-family: 'DM Serif Display', serif;
        font-size: 38px; color: var(--sf-gold);
    }
    .sf-team-name {
        font-family: 'DM Serif Display', serif;
        font-size: 18px; color: var(--sf-gold-lt); margin-bottom: 4px;
    }
    .sf-team-role {
        font-size: 9px; letter-spacing: 2px; text-transform: uppercase;
        color: var(--sf-gold); opacity: 0.7; margin-bottom: 10px;
    }
    .sf-team-bio { font-size: 12px; color: var(--sf-body); line-height: 1.65; max-width: 180px; margin: 0 auto; }

    @media (max-width: 540px) {
        .sf-team-grid { grid-template-columns: 1fr 1fr; gap: 32px 16px; }
        .sf-team-avatar { width: 90px; height: 90px; }
        .sf-team-initial { font-size: 28px; }
    }

    /* ── GALLERY ── */
    .sf-gallery-grid { columns: 2; gap: 6px; }
    @media (min-width: 640px) { .sf-gallery-grid { columns: 3; } }
    @media (min-width: 900px) { .sf-gallery-grid { columns: 4; } }
    .sf-gallery-item {
        break-inside: avoid; margin-bottom: 6px; overflow: hidden; cursor: pointer;
    }
    .sf-gallery-item img {
        width: 100%; object-fit: cover; display: block;
        transition: transform 0.35s, brightness 0.35s;
    }
    .sf-gallery-item:hover img { transform: scale(1.03); filter: brightness(0.85); }
    .sf-lightbox {
        position: fixed; inset: 0; z-index: 200;
        background: rgba(0,0,0,0.92); display: flex;
        align-items: center; justify-content: center; padding: 16px;
    }
    .sf-lightbox img { max-height: 90vh; max-width: 100%; object-fit: contain; }
    .sf-lightbox-close {
        position: absolute; top: 16px; right: 20px;
        color: var(--sf-gold-lt); font-size: 28px; cursor: pointer;
        background: none; border: none; line-height: 1; padding: 4px;
    }

    /* ── INFO (orari + contatti) ── */
    .sf-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; }

    .sf-hours-list { list-style: none; }
    .sf-hours-item { padding: 12px 0; border-bottom: 1px solid var(--sf-border); }
    .sf-hours-item:last-child { border-bottom: none; }
    .sf-hours-day {
        font-size: 9px; letter-spacing: 2.5px; text-transform: uppercase;
        color: var(--sf-muted); margin-bottom: 3px;
        display: flex; align-items: center; gap: 8px;
    }
    .sf-today-badge {
        background: var(--sf-gold); color: var(--sf-btn-fg);
        font-size: 7px; letter-spacing: 1.5px; font-weight: 700;
        padding: 2px 6px; text-transform: uppercase;
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

    @media (max-width: 768px) {
        .sf-info-grid { grid-template-columns: 1fr; gap: 48px; }
    }

    /* ── REVIEWS ── */
    .sf-reviews-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
    }
    .sf-review-card {
        background: var(--sf-bg-card); border: 1px solid var(--sf-border);
        padding: 28px 24px; position: relative; overflow: hidden;
    }
    .sf-review-quote {
        position: absolute; top: 10px; left: 14px;
        font-family: 'DM Serif Display', serif;
        font-size: 80px; line-height: 1; color: rgba(201,169,110,0.07);
        pointer-events: none; font-style: italic; user-select: none;
    }
    .sf-review-stars { display: flex; gap: 3px; margin-bottom: 14px; }
    .sf-review-stars span { color: var(--sf-gold); font-size: 11px; }
    .sf-review-body { font-size: 13px; color: var(--sf-body); line-height: 1.75; margin-bottom: 20px; position: relative; }
    .sf-review-author { font-size: 10px; letter-spacing: 2px; text-transform: uppercase; color: var(--sf-gold-lt); position: relative; }

    @media (max-width: 820px) { .sf-reviews-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 540px) { .sf-reviews-grid { grid-template-columns: 1fr; } }

    /* ── CANCELLATION POLICY ── */
    .sf-accordion { border: 1px solid var(--sf-border); }
    .sf-accordion-btn {
        width: 100%; display: flex; align-items: center; justify-content: space-between;
        padding: 18px 24px; background: var(--sf-bg-card); border: none;
        font-size: 10px; letter-spacing: 2px; text-transform: uppercase;
        color: var(--sf-gold-lt); cursor: pointer; text-align: left;
        transition: background 0.2s;
    }
    .sf-accordion-btn:hover { background: rgba(201,169,110,0.04); }
    .sf-accordion-icon { transition: transform 0.3s; flex-shrink: 0; color: var(--sf-gold); }
    .sf-accordion-icon.open { transform: rotate(180deg); }
    .sf-accordion-body {
        display: none; padding: 20px 24px; border-top: 1px solid var(--sf-border);
        background: var(--sf-bg-card); font-size: 13px; color: var(--sf-body);
        line-height: 1.8;
    }
    .sf-accordion-body.open { display: block; }
    .sf-accordion-body a { color: var(--sf-gold); }

    /* ── CTA ── */
    .sf-cta { text-align: center; border-top: 1px solid var(--sf-border); }
    .sf-cta .sf-rule { margin: 24px auto 44px; }
    .sf-cta p { font-size: 13px; color: var(--sf-body); max-width: 380px; margin: 0 auto 40px; line-height: 1.75; }
</style>
@endpush

@section('content')

{{-- 1. HERO --}}
<section class="sf-hero">
    <div class="sf-hero-bg"></div>
    @if($profile->coverUrl())
        <div class="sf-hero-cover"></div>
    @endif
    <div class="sf-hero-overlay"></div>
    <div class="sf-hero-content">
        @if($profile->tagline || $profile->address)
            <div class="sf-hero-eyebrow">
                {{ $profile->tagline ?: $profile->address }}
            </div>
        @endif
        <h1 class="sf-hero-title">
            {{ $profile->name }}
            @if(str_contains(strtolower($profile->name ?? ''), 'barbershop') === false)
                <em>Barbershop</em>
            @endif
        </h1>
        <div class="sf-hero-rule"></div>
        <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">Prenota un appuntamento</a>
    </div>
    <div class="sf-hero-scroll">Scorri</div>
</section>

{{-- 2. SERVIZI --}}
@if($services->isNotEmpty())
<section class="sf-section-alt" id="servizi">
    <div class="sf-inner">
        @php $totalServices = $services->count(); $hasMore = $totalServices > 4; @endphp
        <div class="sf-svc-header">
            <div>
                <div class="sf-eyebrow">I nostri servizi</div>
                <h2 class="sf-heading">Ogni taglio, <em>un'opera.</em></h2>
                <div class="sf-rule"></div>
            </div>
            @if($hasMore)
            <div class="sf-svc-nav" id="svc-nav">
                <button id="svc-prev" aria-label="Precedente" disabled>&#8592;</button>
                <div class="sf-svc-dots" id="svc-dots"></div>
                <button id="svc-next" aria-label="Successivo">&#8594;</button>
            </div>
            @endif
        </div>
        <div class="sf-svc-wrap">
            <div class="sf-svc-track" id="svc-track">
                @foreach($services as $service)
                <div class="sf-svc-card">
                    <div class="sf-svc-name">{{ $service->name }}</div>
                    @if($service->description)
                        <div class="sf-svc-desc">{{ $service->description }}</div>
                    @endif
                    <div class="sf-svc-footer">
                        <span class="sf-svc-dur">{{ $service->duration_minutes }} min</span>
                        <span class="sf-svc-price">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

@if($hasMore)
@push('scripts')
<script>
(function() {
    const track   = document.getElementById('svc-track');
    const dotsEl  = document.getElementById('svc-dots');
    const prevBtn = document.getElementById('svc-prev');
    const nextBtn = document.getElementById('svc-next');
    if (!track) return;

    const cards = track.children;
    let perPage = window.innerWidth <= 540 ? 1 : window.innerWidth <= 820 ? 2 : 4;
    const total = cards.length;
    const pages = Math.ceil(total / perPage);
    let current = 0;

    // build dots
    for (let i = 0; i < pages; i++) {
        const d = document.createElement('span');
        if (i === 0) d.classList.add('active');
        d.addEventListener('click', () => goTo(i));
        dotsEl.appendChild(d);
    }

    function goTo(p) {
        current = Math.max(0, Math.min(p, pages - 1));
        const cardW = track.parentElement.offsetWidth / perPage;
        track.style.transform = `translateX(-${current * perPage * cardW}px)`;
        prevBtn.disabled = current === 0;
        nextBtn.disabled = current === pages - 1;
        dotsEl.querySelectorAll('span').forEach((d, i) => d.classList.toggle('active', i === current));
    }

    prevBtn.addEventListener('click', () => goTo(current - 1));
    nextBtn.addEventListener('click', () => goTo(current + 1));
    goTo(0);
})();
</script>
@endpush
@endif
@endif

{{-- 3. CHI SIAMO --}}
@if($profile->description)
<section class="sf-section" id="salone">
    <div class="sf-inner">
        @php $galleryItems = $profile->getMedia('gallery'); @endphp
        <div class="sf-about-grid">
            <div>
                <div class="sf-eyebrow">Il salone</div>
                <h2 class="sf-heading">Un'esperienza,<br><em>non solo un taglio.</em></h2>
                <div class="sf-rule"></div>
                <div class="sf-about-text">
                    {!! $profile->description !!}
                </div>
            </div>
            @if($galleryItems->isNotEmpty())
            <div class="sf-about-photos">
                @foreach($galleryItems->take(3) as $item)
                    <div class="sf-about-photo">
                        <img src="{{ $item->getUrl('thumb') }}" alt="Foto salone">
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
        <h2 class="sf-heading">Il nostro <em>team.</em></h2>
        <div class="sf-rule"></div>
        <div class="sf-team-grid">
            @foreach($staff as $member)
            @php $avatarUrl = $member->getFirstMediaUrl('avatar', 'thumb'); @endphp
            <div class="sf-team-card">
                <div class="sf-team-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $member->name }}">
                    @else
                        <span class="sf-team-initial">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="sf-team-name">{{ $member->name }}</div>
                @if($member->specialization ?? false)
                    <div class="sf-team-role">{{ $member->specialization }}</div>
                @endif
                @if($member->bio ?? false)
                    <div class="sf-team-bio">{{ $member->bio }}</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 5. GALLERIA (standalone, se non usata nell'about) --}}
@php $galleryItems = $galleryItems ?? $profile->getMedia('gallery'); @endphp
@if($galleryItems->count() > 3 || ($galleryItems->isNotEmpty() && !$profile->description))
<section class="sf-section" id="galleria" x-data="{ lightbox: null }">
    <div class="sf-inner">
        <div class="sf-eyebrow">Galleria</div>
        <h2 class="sf-heading">Il nostro <em>lavoro.</em></h2>
        <div class="sf-rule"></div>
        <div class="sf-gallery-grid">
            @foreach($galleryItems as $item)
            @php $thumbUrl = $item->getUrl('thumb'); $webUrl = $item->getUrl('web'); @endphp
            <div class="sf-gallery-item" @click="lightbox = '{{ $webUrl }}'">
                <img src="{{ $thumbUrl }}" alt="Galleria {{ $loop->iteration }}">
            </div>
            @endforeach
        </div>
    </div>
    <div x-show="lightbox" x-transition.opacity class="sf-lightbox" @click="lightbox = null" @keydown.escape.window="lightbox = null" style="display:none">
        <button class="sf-lightbox-close" @click.stop="lightbox = null">×</button>
        <img :src="lightbox" @click.stop>
    </div>
</section>
@endif

{{-- 6. ORARI + CONTATTI --}}
@if($profile->opening_hours || $profile->phone || $profile->address)
@php
    $days = ['mon'=>'Lunedì','tue'=>'Martedì','wed'=>'Mercoledì','thu'=>'Giovedì','fri'=>'Venerdì','sat'=>'Sabato','sun'=>'Domenica'];
    $dayMap = ['Mon'=>'mon','Tue'=>'tue','Wed'=>'wed','Thu'=>'thu','Fri'=>'fri','Sat'=>'sat','Sun'=>'sun'];
    $todayKey = $dayMap[now()->format('D')] ?? '';
@endphp
<section class="sf-section-alt" id="contatti">
    <div class="sf-inner">
        <div class="sf-info-grid">
            @if($profile->opening_hours)
            <div>
                <div class="sf-eyebrow">Orari di apertura</div>
                <h2 class="sf-heading" style="font-size:clamp(22px,3vw,30px)">Quando <em>trovarci.</em></h2>
                <div class="sf-rule"></div>
                <ul class="sf-hours-list">
                    @foreach($days as $key => $label)
                    @php $day = $profile->opening_hours[$key] ?? null; $isOpen = $day && ($day['open'] ?? false); @endphp
                    <li class="sf-hours-item {{ $key === $todayKey ? 'is-today' : '' }} {{ !$isOpen ? 'is-closed' : '' }}">
                        <div class="sf-hours-day">
                            {{ $label }}
                            @if($key === $todayKey)
                                <span class="sf-today-badge">oggi</span>
                            @endif
                        </div>
                        <div class="sf-hours-time">
                            @if($isOpen)
                                {{ $day['morning_open'] ?? '09:00' }}–{{ $day['morning_close'] ?? '13:00' }}
                                @if(!empty($day['afternoon_open']) && !empty($day['afternoon_close']))
                                    &thinsp;/&thinsp;{{ $day['afternoon_open'] }}–{{ $day['afternoon_close'] }}
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
                <div class="sf-eyebrow">Dove siamo</div>
                <h2 class="sf-heading" style="font-size:clamp(22px,3vw,30px)">Vieni a <em>trovarci.</em></h2>
                <div class="sf-rule"></div>
                <ul class="sf-contact-list">
                    @if($profile->address)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        </span>
                        <span>{{ $profile->address }}</span>
                    </li>
                    @endif
                    @if($profile->phone)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                        </span>
                        <a href="tel:{{ $profile->phone }}">{{ $profile->phone }}</a>
                    </li>
                    @endif
                    @if($profile->whatsapp_number)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884"/></svg>
                        </span>
                        <a href="https://wa.me/{{ $profile->whatsapp_number }}" target="_blank" rel="noopener">WhatsApp</a>
                    </li>
                    @endif
                    @if($profile->instagram_url)
                    <li class="sf-contact-item">
                        <span class="sf-contact-ico">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </span>
                        <a href="{{ $profile->instagram_url }}" target="_blank" rel="noopener">Instagram</a>
                    </li>
                    @endif
                </ul>
                @if($profile->google_maps_embed)
                <div class="sf-map-wrap">
                    <iframe src="{{ $profile->google_maps_embed }}"
                        loading="lazy" allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
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
        <div class="sf-eyebrow">Recensioni</div>
        <h2 class="sf-heading">Cosa dicono <em>di noi.</em></h2>
        <div class="sf-rule"></div>
        <div class="sf-reviews-grid">
            @foreach($reviews as $review)
            <div class="sf-review-card">
                <div class="sf-review-quote">"</div>
                <div class="sf-review-stars">
                    @for($i = 1; $i <= 5; $i++)
                        <span>{{ $i <= $review->rating ? '★' : '☆' }}</span>
                    @endfor
                </div>
                <p class="sf-review-body">{{ $review->body }}</p>
                <div class="sf-review-author">{{ $review->author_name }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- 8. POLITICA DI CANCELLAZIONE --}}
@if($profile->cancellation_policy)
<section class="sf-section-alt">
    <div class="sf-inner" style="max-width:680px">
        <div class="sf-accordion">
            <button class="sf-accordion-btn" id="policy-btn" onclick="
                this.querySelector('.sf-accordion-icon').classList.toggle('open');
                document.getElementById('policy-body').classList.toggle('open');
            ">
                Politica di cancellazione
                <svg class="sf-accordion-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="sf-accordion-body" id="policy-body">
                {!! $profile->cancellation_policy !!}
            </div>
        </div>
    </div>
</section>
@endif

{{-- 9. CTA PRENOTA --}}
<section class="sf-section sf-cta">
    <div class="sf-inner">
        <div class="sf-eyebrow">Prenota online</div>
        <h2 class="sf-heading">Scegli il tuo <em>momento.</em></h2>
        <div class="sf-rule"></div>
        <p>Niente code, niente attese. Scegli servizio, barbiere e orario in pochi tap.</p>
        <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">Prenota un appuntamento</a>
    </div>
</section>

@endsection
