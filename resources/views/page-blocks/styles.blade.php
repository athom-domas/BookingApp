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
.sf-hero-bg {
    position: absolute; inset: 0;
    width: 100%; height: 100%;
    object-fit: cover; object-position: center;
    z-index: 0;
}
.sf-hero-overlay {
    position: absolute; inset: 0; pointer-events: none;
    background: radial-gradient(ellipse at 50% 35%,
        rgba(0,0,0,0.20) 0%,
        rgba(0,0,0,0.60) 55%,
        rgba(0,0,0,0.84) 100%);
    z-index: 0;
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

/* Editorial hero grid */
.sf-hero-editorial-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 48px;
    align-items: center;
    width: 100%;
}

@media (max-width: 640px) {
    .sf-hero { padding: 56px 20px; }
    .sf-hero-editorial-grid { grid-template-columns: 1fr; gap: 24px; }
    .sf-hero-editorial-img { order: -1; }
    .sf-hero-editorial-img img { aspect-ratio: 4/3; max-height: 220px; }
    .sf-hero--editorial .sf-hero-inner { align-items: center !important; text-align: center !important; }
}
@media (prefers-reduced-motion: reduce) {
    .sf-hero--no-img::before { display: none; }
}

/* ── SERVICES ─────────────────────────────────────────────────────────────── */
.sf-svc-category-heading {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--sf-text, #1e293b);
    margin: 32px 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--sf-border, #e2e8f0);
}
.sf-svc-category-heading:first-child { margin-top: 0; }
.sf-svc-list { list-style: none; }
.sf-svc-item {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 24px 0;
    border-bottom: 1px solid var(--sf-border);
}
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

/* ── SERVICE CARDS ────────────────────────────────────────────────────────── */
.sf-svc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
}
.sf-svc-card {
    background: var(--sf-bg-card, #fff);
    border: 1px solid var(--sf-border);
    border-radius: min(var(--sf-radius, 8px), 16px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.sf-svc-card-img {
    aspect-ratio: 4 / 3;
    overflow: hidden;
    background: var(--sf-surface, #f5f0ea);
    flex-shrink: 0;
}
.sf-svc-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}
.sf-svc-card:hover .sf-svc-card-img img { transform: scale(1.04); }
.sf-svc-card-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.sf-svc-card-name {
    font-family: var(--sf-font-display);
    font-size: 17px;
    color: var(--sf-gold-lt);
    margin-bottom: 6px;
    line-height: 1.2;
}
.sf-svc-card-desc {
    font-size: 12px;
    color: var(--sf-body);
    line-height: 1.65;
    flex: 1;
    margin-bottom: 0;
}
.sf-svc-card-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid var(--sf-border);
    gap: 8px;
}
.sf-svc-card-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.sf-svc-card-meta .sf-svc-item-price { font-size: 18px; }

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
    cursor: pointer;
}
.sf-about-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.sf-about-photo:first-child { grid-row: span 2; }

@media (max-width: 820px) {
    .sf-about-grid { grid-template-columns: 1fr; gap: 24px; }
    .sf-about-photos { grid-template-columns: 1fr 1fr 1fr; grid-template-rows: 110px; }
    .sf-about-photo:first-child { grid-row: span 1; }
}
@media (max-width: 640px) { .sf-about-split-grid { grid-template-columns: 1fr; } }

/* ── TEAM ─────────────────────────────────────────────────────────────────── */
.sf-team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 24px;
}
.sf-team-card {
    background: var(--sf-bg-card, #fff);
    border: 1px solid var(--sf-border);
    border-radius: min(var(--sf-radius, 8px), 12px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.sf-team-avatar {
    aspect-ratio: 1;
    overflow: hidden;
    background: var(--sf-surface, #f5f0ea);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sf-team-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sf-team-initial { font-family: var(--sf-font-display); font-size: 48px; color: var(--sf-gold); }
.sf-team-card-body { padding: 16px; }
.sf-team-name {
    font-family: var(--sf-font-display);
    font-size: 17px;
    color: var(--sf-gold-lt);
    margin-bottom: 7px;
    line-height: 1.2;
}
.sf-team-bio { font-size: 12px; color: var(--sf-body); line-height: 1.65; }
.sf-team-editorial-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 40px 56px;
}
.sf-team-editorial-item { display: flex; gap: 20px; align-items: flex-start; }
.sf-team-editorial-avatar {
    width: 100px; height: 100px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--sf-surface, #f5f0ea);
    border: 1px solid var(--sf-border);
    display: flex; align-items: center; justify-content: center;
}
.sf-team-editorial-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
.sf-team-editorial-name {
    font-family: var(--sf-font-display);
    font-size: 18px;
    color: var(--sf-gold-lt);
    margin-bottom: 10px;
    line-height: 1.2;
}
@media (max-width: 820px) { .sf-team-editorial-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px)  { .sf-team-editorial-grid { grid-template-columns: 1fr; } }

/* ── GALLERY ──────────────────────────────────────────────────────────────── */
.sf-gallery-grid { columns: 2; gap: 6px; }
@media (min-width: 640px) { .sf-gallery-grid { columns: 3; } }
@media (min-width: 900px) { .sf-gallery-grid { columns: 4; } }
.sf-gallery-item { break-inside: avoid; margin-bottom: 6px; overflow: hidden; cursor: pointer; }
.sf-gallery-item img {
    width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block;
    transition: transform 0.35s ease, filter 0.35s ease;
}
.sf-gallery-item:hover img { transform: scale(1.04); filter: brightness(0.82); }
.sf-lightbox {
    position: fixed; inset: 0; z-index: 200;
    background: rgba(0,0,0,0.92);
    display: flex; align-items: center; justify-content: center; padding: 16px;
}
.sf-lightbox img { max-height: 90vh; max-width: 90vw; object-fit: contain; }
.sf-lightbox-close {
    position: absolute; top: 16px; right: 20px;
    color: #e8d5a3; font-size: 28px; cursor: pointer;
    background: none; border: none; line-height: 1; padding: 4px;
}
.sf-lightbox-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,0.1); border: none;
    color: #e8d5a3; font-size: 36px; line-height: 1;
    width: 48px; height: 48px; border-radius: 50%;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.sf-lightbox-nav:hover { background: rgba(255,255,255,0.22); }
.sf-lightbox-prev { left: 16px; }
.sf-lightbox-next { right: 16px; }

/* ── GALLERY SLIDER ───────────────────────────────────────────────────────── */
.sf-slider-track {
    display: flex; overflow-x: scroll; scroll-snap-type: x mandatory;
    scrollbar-width: none; cursor: grab; -webkit-overflow-scrolling: touch;
}
.sf-slider-track::-webkit-scrollbar { display: none; }
.sf-slider-track.is-dragging { cursor: grabbing; scroll-snap-type: none; }
.sf-slider-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.35); backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.18); border-radius: 50%;
    color: #fff; font-size: 28px; line-height: 1;
    width: 44px; height: 44px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s, opacity 0.15s;
    padding: 0;
}
.sf-slider-nav:hover { background: rgba(0,0,0,0.6); }
.sf-slider-nav:disabled { opacity: 0.25; cursor: default; }
.sf-slider-prev { left: 14px; }
.sf-slider-next { right: 14px; }
.sf-slider-dots { display: none; justify-content: center; align-items: center; gap: 6px; margin-top: 14px; }
@media (max-width: 767px) { .sf-slider-dots { display: flex; } }
.sf-slider-dot {
    height: 8px; border: none; border-radius: 4px; padding: 0; cursor: pointer;
    background: var(--sf-border); width: 8px;
    transition: width 0.25s ease, background 0.25s ease;
}
.sf-slider-dot.is-active { background: var(--sf-gold); width: 22px; }
@media (max-width: 640px) {
    .sf-slider-nav { width: 36px; height: 36px; font-size: 22px; }
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
.sf-map-wrap { overflow: hidden; border: 1px solid var(--sf-border); height: 240px; position: relative; }
.sf-map-wrap iframe { width: 100%; height: 100%; border: 0; display: block; }
.sf-map-placeholder { width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; background: var(--sf-surface, #f5f0ea); cursor: pointer; border: 0; color: var(--sf-ink, #1C1410); font-size: 13px; }
.sf-map-placeholder svg { color: var(--sf-ink, #1C1410); opacity: .55; }
.sf-map-placeholder span { color: var(--sf-ink, #1C1410); }

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

.sf-section-cta .sf-inner {
    display: flex; flex-direction: column; align-items: center; gap: 20px;
    max-width: 640px; margin: 0 auto;
}
.sf-section-cta .sf-heading { margin: 0; }
.sf-section-cta p { margin: 0; opacity: .75; }
.sf-section-cta--img .sf-heading,
.sf-section-cta--img p { color: #fff; opacity: 1; }

/* ── HEADING UTILITIES ────────────────────────────────────────────────────── */
.sf-heading { text-wrap: balance; }
.sf-heading--sm { font-size: clamp(22px, 3vw, 30px); }
.sf-subheading { font-size: clamp(16px, 2vw, 20px); font-weight: 600; margin-bottom: 20px; opacity: .75; }
.sf-subheading--spaced { margin-top: 48px; }
.sf-svc-cta { margin-top: 44px; }

/* ── PAGE NAV ─────────────────────────────────────────────────────────────── */
.sf-page-nav {
    position: sticky;
    top: var(--sf-nav-h, 0px);
    z-index: 90;
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

/* ── SERVICE ITEM BADGE ───────────────────────────────────────────────────── */
.sf-svc-book-badge {
    display: inline-block;
    font-size: 10px;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--sf-gold);
    border: 1px solid var(--sf-gold);
    padding: 3px 8px;
    border-radius: var(--sf-radius);
    white-space: nowrap;
    margin-top: 6px;
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
}
a.sf-svc-book-badge:hover {
    background: var(--sf-gold);
    color: var(--sf-bg);
    text-decoration: none;
}
.sf-show-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 20px;
    background: none;
    border: 1px solid var(--sf-border);
    color: var(--sf-body);
    font-size: 13px;
    padding: 8px 18px;
    border-radius: var(--sf-radius);
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
    font-family: inherit;
}
.sf-show-more:hover { border-color: var(--sf-gold); color: var(--sf-gold); }
.sf-show-more-count { color: var(--sf-muted); }
.sf-svc-list--more .sf-svc-item:first-child { border-top: none; }

/* ── STICKY PRENOTA BUTTON (mobile only) ──────────────────────────────────── */
.sf-sticky-book { display: none; }
@media (max-width: 768px) {
    .sf-sticky-book {
        display: block;
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 95;
        opacity: 0;
        pointer-events: none;
        transform: translateY(10px);
        transition: opacity 0.2s, transform 0.2s;
        box-shadow: 0 4px 24px rgba(0,0,0,0.2);
    }
    .sf-sticky-book.is-visible {
        opacity: 1;
        pointer-events: auto;
        transform: translateY(0);
    }
}

/* ── AUTO-ALTERNATING SECTION BACKGROUNDS ────────────────────────────────── */
.sf-blocks > section:nth-child(even)  { background: var(--sf-bg); }
.sf-blocks > section:nth-child(odd) { background: var(--sf-bg-alt); }

/* ── REDUCED MOTION ───────────────────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
    .sf-about-photo img,
    .sf-gallery-item img { transition: none; }
    .sf-accordion-body-grid,
    .sf-accordion-chevron { transition: none; }
}
</style>
