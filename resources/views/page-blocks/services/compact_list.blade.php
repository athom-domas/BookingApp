{{-- Variables: $content['title'], $content['subtitle'], $settings['show_prices'],
     $settings['show_duration'], $services (Collection), $business, $block --}}
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
        <ul class="sf-svc-list">
            @foreach($services as $service)
            <li class="sf-svc-item" style="align-items:center">
                <div class="sf-svc-item-main">
                    <div class="sf-svc-item-name">{{ $service->name }}</div>
                </div>
                <div class="sf-svc-item-meta" style="flex-direction:row;align-items:center;gap:12px">
                    @if($showDuration)
                        <span class="sf-svc-item-dur">{{ $service->duration_minutes }}&thinsp;min</span>
                    @endif
                    @if($showPrices)
                        <span class="sf-svc-item-price" style="font-size:16px">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                    @endif
                    <a href="{{ route('booking.create') }}?service={{ $service->id }}" class="sf-svc-book-badge" aria-label="Prenota {{ $service->name }}">Prenota &rarr;</a>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
</section>
@endif
