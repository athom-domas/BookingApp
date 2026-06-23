@php
$salonProfile = \App\Models\SalonProfile::current();

$_family      = in_array($salonProfile->theme ?? '', ['luxury','rosa','verde','notte','minimal','viola','terracotta','acqua','cipria'])
    ? $salonProfile->theme
    : 'luxury';
$_themeClass  = 'sf-' . $_family;
$_defaultMode = $salonProfile->theme_mode ?? 'light';

$_fontUrls = [
    'classic' => 'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&display=swap',
    'modern'  => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap',
    'elegant' => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Nunito:wght@300;400;500;600&display=swap',
    'minimal' => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap',
];
$_fontVars = [
    'classic' => ["'DM Serif Display', Georgia, serif", "'Inter', sans-serif"],
    'modern'  => ["'Plus Jakarta Sans', sans-serif",    "'Plus Jakarta Sans', sans-serif"],
    'elegant' => ["'Cormorant Garamond', Georgia, serif", "'Nunito', sans-serif"],
    'minimal' => ["'Space Grotesk', sans-serif",        "'Space Grotesk', sans-serif"],
];
$_radiusMap = ['sharp' => '0', 'rounded' => '6px', 'pill' => '100px'];

$_pair        = $salonProfile->font_pair    ?? 'classic';
$_border      = $salonProfile->border_style ?? 'sharp';
$_fontUrl     = $_fontUrls[$_pair]    ?? $_fontUrls['classic'];
$_fontDisplay = $_fontVars[$_pair][0] ?? $_fontVars['classic'][0];
$_fontBody    = $_fontVars[$_pair][1] ?? $_fontVars['classic'][1];
$_radius      = $_radiusMap[$_border] ?? '0';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $_themeClass }}" data-default-mode="{{ $_defaultMode }}" style="--sf-font-display:{{ $_fontDisplay }};--sf-font-body:{{ $_fontBody }};--sf-radius:{{ $_radius }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', e($salonProfile->name))</title>

    @php
        $_ogDesc  = $salonProfile->metaDescription();
        $_ogImage = $salonProfile->heroImageUrl() ?: $salonProfile->logoUrl();
    @endphp
    @if($_ogDesc)
        <meta name="description" content="{{ $_ogDesc }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', e($salonProfile->name))">
    <meta property="og:site_name" content="{{ e($salonProfile->name) }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if($_ogDesc)
        <meta property="og:description" content="{{ $_ogDesc }}">
    @endif
    @if($_ogImage)
        <meta property="og:image" content="{{ $_ogImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $_ogImage }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="@yield('title', e($salonProfile->name))">
    @if($_ogDesc)
        <meta name="twitter:description" content="{{ $_ogDesc }}">
    @endif

    @if($salonProfile->faviconUrl())
        <link rel="icon" href="{{ $salonProfile->faviconUrl() }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="{{ $_fontUrl }}" rel="stylesheet">

    @vite('resources/scss/storefront.scss')

    @fonts
    @filamentStyles
    @vite('resources/scss/filament/admin/theme.scss')
    @vite('resources/css/app.css')
    @stack('head')
    <script>
        (function(){
            var root = document.documentElement;
            var def  = root.getAttribute('data-default-mode') || 'light';
            var mode = localStorage.getItem('sf-mode') || def;
            if (mode === 'dark') root.classList.add('mode-dark');
        })();
    </script>
</head>
<body>

    @if($salonProfile->announcement_active && $salonProfile->announcement_text)
        <div class="sf-announcement" role="status">{{ $salonProfile->announcement_text }}</div>
    @endif

    {{-- NAV --}}
    <nav id="sf-nav">
        @php $logoDarkUrl = $salonProfile->logoDarkUrl(); @endphp
        <a href="{{ route('booking.index') }}" class="sf-logo">
            <img src="{{ $salonProfile->logoUrl() ?? asset('img/logo.png') }}" alt="{{ $salonProfile->name }}"
                 @if($logoDarkUrl) class="sf-logo-light" @endif>
            @if($logoDarkUrl)
                <img src="{{ $logoDarkUrl }}" alt="{{ $salonProfile->name }}" class="sf-logo-dark">
            @endif
        </a>

        <div class="sf-nav-right">
            {{-- Portal / auth links (desktop) --}}
            @auth
                <a href="{{ route('portal.appointments.index') }}" class="sf-nav-link">Area personale</a>
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

            <button id="sf-mode-toggle" class="sf-mode-toggle" aria-label="Cambia modalità">
                {{-- moon = currently in light mode, click to go dark --}}
                <svg id="sf-icon-moon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
                {{-- sun = currently in dark mode, click to go light --}}
                <svg id="sf-icon-sun" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </button>
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

    @stack('scripts')
    @vite(['resources/js/app.js', 'resources/js/storefront.js'])
</body>
</html>
