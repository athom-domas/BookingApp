{{-- Variables: $content['title'], $content['subtitle'], $content['cta_label'],
     $settings['show_prices'], $settings['show_duration'], $services (Collection), $business, $block --}}
@if($services->isNotEmpty())
<section class="sf-section-alt" id="servizi">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'I nostri servizi' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        @php
            $showPrices   = $settings['show_prices'] ?? true;
            $showDuration = $settings['show_duration'] ?? true;
        @endphp
        <ul style="list-style:none">
            @foreach($services as $service)
            <li style="display:flex;align-items:baseline;gap:8px;padding:16px 0;border-bottom:1px solid var(--sf-border)">
                <span style="font-family:var(--sf-font-display);font-size:16px;color:var(--sf-gold-lt);flex-shrink:0">{{ $service->name }}</span>
                @if($showDuration)
                    <span style="font-size:11px;color:var(--sf-muted);flex-shrink:0">{{ $service->duration_minutes }}&thinsp;min</span>
                @endif
                <span style="flex:1;border-bottom:1px dotted var(--sf-border);margin-bottom:4px;min-width:24px"></span>
                @if($showPrices)
                    <span style="font-family:var(--sf-font-display);font-size:18px;color:var(--sf-gold);flex-shrink:0">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                @endif
            </li>
            @endforeach
        </ul>
        <div class="sf-svc-cta" style="text-align:center">
            <a href="{{ route('booking.create') }}" class="sf-btn">{{ $content['cta_label'] ?? 'Prenota ora' }}</a>
        </div>
    </div>
</section>
@endif
