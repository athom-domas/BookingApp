@php $salonProfile = \App\Models\SalonProfile::current(); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ ($salonProfile->theme ?? 'dark') === 'light' ? 'sf-light' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', e($salonProfile->name))</title>

    @if($salonProfile->faviconUrl())
        <link rel="icon" href="{{ $salonProfile->faviconUrl() }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ── THEME VARIABLES ── */
        :root {
            --sf-bg:       #0d0b08;
            --sf-bg-alt:   #100e0b;
            --sf-bg-card:  #131008;
            --sf-gold:     #c9a96e;
            --sf-gold-lt:  #e8d5a3;
            --sf-border:   rgba(201,169,110,0.15);
            --sf-muted:    #555;
            --sf-body:     #888;
            --sf-btn-bg:   #c9a96e;
            --sf-btn-fg:   #0a0806;
            --sf-nav-bg:   rgba(13,11,8,0.94);
        }
        html.sf-light {
            --sf-bg:       #f7f3ec;
            --sf-bg-alt:   #f2ece2;
            --sf-bg-card:  #fffdf8;
            --sf-gold:     #a08060;
            --sf-gold-lt:  #1a1008;
            --sf-border:   rgba(160,128,96,0.22);
            --sf-muted:    #9c8a6e;
            --sf-body:     #6a5a48;
            --sf-btn-bg:   #1a1008;
            --sf-btn-fg:   #f7f3ec;
            --sf-nav-bg:   rgba(247,243,236,0.96);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        [x-cloak] { display: none !important; }
        html { scroll-behavior: smooth; }

        body {
            background: var(--sf-bg);
            color: var(--sf-gold-lt);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }

        .sf-display { font-family: 'DM Serif Display', Georgia, serif; }

        /* ── NAV ── */
        #sf-nav {
            position: sticky; top: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 48px;
            background: var(--sf-nav-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--sf-border);
            transition: background 0.3s;
        }
        .sf-logo {
            font-family: 'DM Serif Display', serif;
            font-size: 20px; color: var(--sf-gold-lt);
            text-decoration: none; flex-shrink: 0;
            display: flex; align-items: center; gap: 10px;
        }
        .sf-logo img { height: 28px; object-fit: contain; }
        #sf-nav-links {
            display: flex; gap: 28px; list-style: none;
        }
        #sf-nav-links a {
            font-size: 10px; letter-spacing: 2px; text-transform: uppercase;
            color: var(--sf-body); text-decoration: none; transition: color 0.2s;
        }
        #sf-nav-links a:hover { color: var(--sf-gold); }
        .sf-nav-right { display: flex; align-items: center; gap: 12px; }

        .sf-btn {
            display: inline-block;
            background: var(--sf-btn-bg); color: var(--sf-btn-fg);
            font-size: 10px; letter-spacing: 2.5px; text-transform: uppercase;
            font-weight: 700; padding: 11px 24px; text-decoration: none;
            white-space: nowrap; transition: opacity 0.2s;
        }
        .sf-btn:hover { opacity: 0.85; }
        .sf-btn-lg { font-size: 10px; letter-spacing: 2.5px; padding: 14px 36px; }
        .sf-btn-outline {
            display: inline-block;
            border: 1px solid var(--sf-gold); color: var(--sf-gold);
            font-size: 10px; letter-spacing: 2px; text-transform: uppercase;
            padding: 12px 28px; text-decoration: none; transition: background 0.2s;
        }
        .sf-btn-outline:hover { background: rgba(201,169,110,0.08); }

        .sf-nav-link {
            font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase;
            color: var(--sf-body); text-decoration: none; transition: color 0.2s;
            white-space: nowrap;
        }
        .sf-nav-link:hover { color: var(--sf-gold); }
        .sf-nav-link--cta {
            border: 1px solid var(--sf-border); padding: 6px 14px;
            color: var(--sf-gold-lt);
        }
        .sf-nav-link--cta:hover { border-color: var(--sf-gold); color: var(--sf-gold); }

        .sf-hamburger {
            display: none; flex-direction: column; gap: 5px;
            cursor: pointer; background: none; border: none; padding: 6px;
        }
        .sf-hamburger span { display: block; width: 22px; height: 1.5px; background: var(--sf-gold-lt); }

        /* mobile menu overlay */
        #sf-mob-menu {
            display: none; position: fixed; inset: 0; z-index: 101;
            background: var(--sf-bg); padding: 0 28px 32px;
            flex-direction: column; overflow-y: auto;
        }
        #sf-mob-menu.open { display: flex; }
        .sf-mob-top {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 0 20px; border-bottom: 1px solid var(--sf-border);
            margin-bottom: 12px;
        }
        .sf-mob-close {
            background: none; border: none; cursor: pointer;
            color: var(--sf-gold-lt); font-size: 22px; line-height: 1; padding: 4px;
        }
        #sf-mob-menu a {
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            color: var(--sf-body); text-decoration: none;
            padding: 16px 0; border-bottom: 1px solid var(--sf-border); display: block;
        }
        #sf-mob-menu a:hover { color: var(--sf-gold); }
        #sf-mob-menu .sf-mob-cta {
            margin-top: 24px; display: block; text-align: center;
        }

        @media (max-width: 820px) {
            #sf-nav { padding: 14px 20px; }
            #sf-nav-links { display: none; }
            .sf-nav-right .sf-btn { display: none; }
            .sf-nav-right .sf-nav-link { display: none; }
            .sf-hamburger { display: flex; }
        }

        /* ── SHARED SECTION STYLES ── */
        .sf-section { padding: 88px 48px; background: var(--sf-bg); }
        .sf-section-alt { padding: 88px 48px; background: var(--sf-bg-alt); }
        .sf-inner { max-width: 1080px; margin: 0 auto; }
        .sf-eyebrow {
            font-size: 9px; letter-spacing: 4px; color: var(--sf-gold);
            text-transform: uppercase; margin-bottom: 14px; opacity: 0.85;
        }
        .sf-heading {
            font-family: 'DM Serif Display', serif;
            font-size: clamp(26px, 4vw, 40px);
            color: var(--sf-gold-lt); line-height: 1.1; margin-bottom: 12px;
        }
        .sf-heading em { color: var(--sf-gold); font-style: italic; }
        .sf-rule { width: 28px; height: 1px; background: var(--sf-gold); margin-bottom: 44px; opacity: 0.45; }

        @media (max-width: 768px) {
            .sf-section, .sf-section-alt { padding: 60px 20px; }
            .sf-rule { margin-bottom: 32px; }
        }

        /* ── FOOTER ── */
        #sf-footer {
            background: var(--sf-bg);
            border-top: 1px solid var(--sf-border);
            padding: 36px 48px;
        }
        .sf-footer-inner {
            max-width: 1080px; margin: 0 auto;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 16px;
        }
        .sf-footer-logo { font-family: 'DM Serif Display', serif; font-size: 16px; color: var(--sf-gold-lt); text-decoration: none; display: block; margin-bottom: 6px; }
        .sf-footer-meta { font-size: 11px; color: var(--sf-muted); margin-top: 4px; display: flex; flex-wrap: wrap; gap: 12px; }
        .sf-footer-social { display: flex; gap: 16px; align-items: center; }
        .sf-footer-social a { color: var(--sf-muted); text-decoration: none; line-height: 0; transition: color 0.2s; }
        .sf-footer-social a:hover { color: var(--sf-gold); }
        .sf-social-icon { width: 18px; height: 18px; display: block; }
        .sf-footer-copy { font-size: 10px; letter-spacing: 1px; color: var(--sf-muted); }
        .sf-footer-legal { display: flex; gap: 12px; margin-top: 4px; }
        .sf-footer-legal a { font-size: 10px; color: var(--sf-muted); text-decoration: none; }
        .sf-footer-legal a:hover { color: var(--sf-gold); }

        @media (max-width: 600px) {
            #sf-footer { padding: 28px 20px; }
            .sf-footer-inner { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
    </style>


    @fonts
    @filamentStyles
    @vite('resources/css/filament/admin/theme.css')
    @vite('resources/css/app.css')
    @stack('head')
</head>
<body>

    {{-- NAV --}}
    <nav id="sf-nav">
        <a href="{{ route('booking.index') }}" class="sf-logo">
            @if($salonProfile->logoUrl())
                <img src="{{ $salonProfile->logoUrl() }}" alt="">
            @endif
            {{ $salonProfile->name }}
        </a>

        <div class="sf-nav-right">
            {{-- Portal / auth links (desktop) --}}
            @auth
                <a href="{{ route('portal.appointments.index') }}" class="sf-nav-link">Appuntamenti</a>
                <a href="{{ route('portal.settings.index') }}" class="sf-nav-link">Impostazioni</a>
                @if(auth()->user()->isAdmin() || auth()->user()->isStaff())
                    <a href="{{ url('/admin') }}" class="sf-nav-link">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" style="margin-bottom: 3px;display:inline">
                    @csrf
                    <button type="submit" class="sf-nav-link" style="background:none;border:none;cursor:pointer;">Esci</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="sf-nav-link">Accedi</a>
                <a href="{{ route('register') }}" class="sf-nav-link sf-nav-link--cta">Registrati</a>
            @endauth

            <a href="{{ route('booking.create') }}" class="sf-btn">Prenota ora</a>
            <button class="sf-hamburger" id="sf-hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    {{-- MOBILE MENU --}}
    <div id="sf-mob-menu">
        <div class="sf-mob-top">
            <span class="sf-logo">{{ $salonProfile->name }}</span>
            <button id="sf-mob-close" class="sf-mob-close" type="button">×</button>
        </div>
        {{-- portal / auth --}}
        @auth
            <a href="{{ route('portal.appointments.index') }}">Appuntamenti</a>
            <a href="{{ route('portal.settings.index') }}">Impostazioni</a>
            @if(auth()->user()->isAdmin() || auth()->user()->isStaff())
                <a href="{{ url('/admin') }}">Admin</a>
            @endif
            <form method="POST" action="{{ route('logout') }}" style="padding:16px 0;border-bottom:1px solid var(--sf-border)">
                @csrf
                <button type="submit" style="background:none;border:none;cursor:pointer;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:var(--sf-body)">Esci</button>
            </form>
        @else
            <a href="{{ route('login') }}">Accedi</a>
            <a href="{{ route('register') }}">Registrati</a>
        @endauth
        <a href="{{ route('booking.create') }}" class="sf-btn sf-mob-cta">Prenota ora</a>
    </div>

    @yield('content')

    {{-- FOOTER --}}
    <footer id="sf-footer">
        <div class="sf-footer-inner">
            <div>
                <a href="{{ route('booking.index') }}" class="sf-footer-logo">{{ $salonProfile->name }}</a>
                @if($salonProfile->phone || $salonProfile->address)
                    <div class="sf-footer-meta">
                        @if($salonProfile->phone)
                            <span>{{ $salonProfile->phone }}</span>
                        @endif
                        @if($salonProfile->address)
                            <span>{{ $salonProfile->address }}</span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="sf-footer-social">
                @if($salonProfile->instagram_url)
                    <a href="{{ $salonProfile->instagram_url }}" target="_blank" rel="noopener" aria-label="Instagram">
                        <svg class="sf-social-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                @endif
                @if($salonProfile->facebook_url)
                    <a href="{{ $salonProfile->facebook_url }}" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg class="sf-social-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                @endif
                @if($salonProfile->tiktok_url)
                    <a href="{{ $salonProfile->tiktok_url }}" target="_blank" rel="noopener" aria-label="TikTok">
                        <svg class="sf-social-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.73a4.85 4.85 0 01-1.01-.04z"/></svg>
                    </a>
                @endif
                @if($salonProfile->whatsapp_number)
                    <a href="https://wa.me/{{ $salonProfile->whatsapp_number }}" target="_blank" rel="noopener" aria-label="WhatsApp">
                        <svg class="sf-social-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 0C5.373 0 0 5.373 0 12c0 2.117.554 4.107 1.523 5.832L.044 23.956l6.278-1.647A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 11.999 0zm.001 21.818a9.818 9.818 0 01-5.011-1.37l-.36-.213-3.726.977.997-3.634-.234-.374A9.775 9.775 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>
                    </a>
                @endif
            </div>

            <div>
                <div class="sf-footer-copy">© {{ date('Y') }} {{ $salonProfile->name }}</div>
                <div class="sf-footer-legal">
                    <a href="{{ route('legal.privacy') }}">Privacy</a>
                    <a href="{{ route('legal.terms') }}">Termini</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        (function() {
            const menu     = document.getElementById('sf-mob-menu');
            const burger   = document.getElementById('sf-hamburger');
            const closeBtn = document.getElementById('sf-mob-close');

            burger.addEventListener('click',   () => menu.classList.add('open'));
            closeBtn.addEventListener('click', () => menu.classList.remove('open'));
            menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => menu.classList.remove('open')));
        })();
    </script>

    @stack('scripts')
    @vite('resources/js/app.js')
</body>
</html>
