@php
    $salon = \App\Models\SalonProfile::current();


    $_emailThemeColors = [
        'luxury'     => '#7a5c38',
        'rosa'       => '#9e4858',
        'verde'      => '#3a5c32',
        'notte'      => '#2c4090',
        'minimal'    => '#1a1a1a',
        'viola'      => '#5a2898',
        'terracotta' => '#963a10',
        'acqua'      => '#166060',
        'cipria'     => '#7a4030',
    ];
    $_emailFamily = $salon->theme ?? 'luxury';
    $primaryColor = $salon->email_accent_color
        ?: ($_emailThemeColors[$_emailFamily] ?? '#1e293b');

    $_emailFontUrls = [
        'classic' => 'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@400;600&display=swap',
        'modern'  => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap',
        'elegant' => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Nunito:wght@400;600&display=swap',
        'minimal' => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600&display=swap',
    ];
    $_emailFontVars = [
        'classic' => ['-apple-system,BlinkMacSystemFont,Inter,sans-serif', "'DM Serif Display',Georgia,serif"],
        'modern'  => ["'Plus Jakarta Sans',sans-serif",                    "'Plus Jakarta Sans',sans-serif"],
        'elegant' => ["Nunito,sans-serif",                                  "'Cormorant Garamond',Georgia,serif"],
        'minimal' => ["'Space Grotesk',sans-serif",                        "'Space Grotesk',sans-serif"],
    ];
    $_emailRadiusMap = ['sharp' => '0', 'rounded' => '8px', 'pill' => '100px'];

    $_emailPair    = $salon->font_pair    ?? 'classic';
    $_emailBorder  = $salon->border_style ?? 'rounded';
    $_emailFontUrl = $_emailFontUrls[$_emailPair] ?? $_emailFontUrls['classic'];
    $_emailBody    = $_emailFontVars[$_emailPair][0] ?? $_emailFontVars['classic'][0];
    $_emailDisplay = $_emailFontVars[$_emailPair][1] ?? $_emailFontVars['classic'][1];
    $_emailRadius  = $_emailRadiusMap[$_emailBorder] ?? '8px';

    // Resolve customer first name from whichever variable the child view provides
    $_rawName = isset($appointment) ? ($appointment->user->name ?? '')
              : (isset($recipient) ? ($recipient->name ?? '') : '');
    $_firstName = $_rawName ? explode(' ', trim($_rawName))[0] : '';

    $_greeting = $salon->email_greeting ?: 'Ciao {nome},';
    if ($_firstName) {
        $_greeting = str_replace('{nome}', e($_firstName), $_greeting);
    } else {
        $_greeting = str_replace('{nome}', '', trim($_greeting, ' ,')) ?: '';
    }
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="{{ $_emailFontUrl }}" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: {{ $_emailBody }}, -apple-system, BlinkMacSystemFont, sans-serif; }
        .wrapper { padding: 32px 16px; }
        .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04); }
        .brand-header { padding: 22px 28px 26px; }
        .brand-top { margin-bottom: 20px; }
        .brand-logo { width: 32px; height: 32px; border-radius: 5px; object-fit: contain; vertical-align: middle; margin-right: 10px; }
        .brand-name { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.85); letter-spacing: 0.02em; text-transform: uppercase; vertical-align: middle; }
        .brand-header h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; line-height: 1.2; font-family: {{ $_emailDisplay }}; }
        .email-body { padding: 26px 28px 22px; }
        .email-body p { margin: 0 0 14px; color: #374151; font-size: 0.9375rem; line-height: 1.65; }
        .email-body p:last-of-type { margin-bottom: 0; }
        .detail-card { background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; overflow: hidden; margin: 20px 0 0; }
        .detail-row { display: table; width: 100%; table-layout: fixed; border-bottom: 1px solid #e5e7eb; font-size: 0.875rem; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { display: table-cell; color: #6b7280; padding: 11px 8px 11px 16px; white-space: nowrap; width: 30%; }
        .detail-value { display: table-cell; font-weight: 600; color: #111827; text-align: right; padding: 11px 16px 11px 8px; word-break: break-word; overflow-wrap: break-word; }
        .actions { padding: 16px 28px 26px; }
        .btn { padding: 12px 16px; border-radius: {{ $_emailRadius }}; text-decoration: none; font-weight: 600; font-size: 0.875rem; text-align: center; display: block; line-height: 1.3; }
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
                @if(empty($noGreeting) && ! $__env->hasSection('skip-greeting') && $_greeting)
                    <p style="color:#111827;font-size:1rem;font-weight:500;padding-bottom:16px;margin-bottom:16px;border-bottom:1px solid #f3f4f6;">{!! nl2br(e($_greeting)) !!}</p>
                @endif
                @yield('body')
            </div>

            @hasSection('actions')
                <div class="actions">@yield('actions')</div>
            @endif

            @hasSection('footer-note')
                <div class="footer-note">@yield('footer-note')</div>
            @endif

            @sectionMissing('footer-note')
                @if($salon->email_footer_note)
                    @php
                        $_footerNote = $salon->email_footer_note;
                        if ($_firstName) $_footerNote = str_replace('{nome}', e($_firstName), $_footerNote);
                    @endphp
                    <div class="footer-note">{!! nl2br(e($_footerNote)) !!}</div>
                @endif
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
