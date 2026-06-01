@php
    $salon        = \App\Models\SalonProfile::current();
    $primaryColor = '#1e293b';
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .wrapper { padding: 32px 16px; }
        .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04); }
        .brand-header { padding: 22px 28px 26px; }
        .brand-top { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .brand-logo { width: 32px; height: 32px; border-radius: 5px; object-fit: contain; }
        .brand-name { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.85); letter-spacing: 0.02em; text-transform: uppercase; }
        .brand-header h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; line-height: 1.2; }
        .email-body { padding: 26px 28px 22px; }
        .email-body p { margin: 0 0 14px; color: #374151; font-size: 0.9375rem; line-height: 1.65; }
        .email-body p:last-of-type { margin-bottom: 0; }
        .detail-card { background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; overflow: hidden; margin: 20px 0 0; }
        .detail-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 11px 16px; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; gap: 16px; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; flex-shrink: 0; padding-top: 1px; }
        .detail-value { font-weight: 600; color: #111827; text-align: right; }
        .actions { padding: 16px 28px 26px; display: flex; gap: 10px; }
        .btn { flex: 1; padding: 12px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.875rem; text-align: center; display: block; line-height: 1.3; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .footer-note { padding: 14px 28px; font-size: 0.775rem; color: #9ca3af; border-top: 1px solid #f3f4f6; line-height: 1.55; }
        .salon-info { padding: 12px 28px; font-size: 0.775rem; color: #9ca3af; border-top: 1px solid #f3f4f6; }
        .salon-info span { margin-right: 14px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 100px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; vertical-align: middle; margin-left: 8px; background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.9); }
        @media only screen and (max-width: 480px) {
            .wrapper { padding: 16px 8px; }
            .brand-header, .email-body, .actions, .footer-note, .salon-info { padding-left: 20px; padding-right: 20px; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="brand-header" style="background-color: {{ $primaryColor }};">
                <div class="brand-top">
                    @if($salon->logoUrl())
                        <img src="{{ $salon->logoUrl() }}" alt="{{ e($salon->name) }}" class="brand-logo">
                    @endif
                    <span class="brand-name">{{ e($salon->name) }}</span>
                    @hasSection('badge')
                        <span class="badge">@yield('badge')</span>
                    @endif
                </div>
                <h1>@yield('title')</h1>
            </div>

            <div class="email-body">
                @yield('body')
            </div>

            @hasSection('actions')
                <div class="actions">@yield('actions')</div>
            @endif

            @hasSection('footer-note')
                <div class="footer-note">@yield('footer-note')</div>
            @endif

            @if($salon->phone || $salon->address)
                <div class="salon-info">
                    @if($salon->phone)<span>{{ e($salon->phone) }}</span>@endif
                    @if($salon->address)<span>{{ e($salon->address) }}</span>@endif
                </div>
            @endif
        </div>
    </div>
</body>
</html>
