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
            $featuredServices = $services->where('featured', true);
            $otherServices    = $services->where('featured', false);
            $hasFeatured      = $featuredServices->isNotEmpty();
            $showPrices       = $settings['show_prices'] ?? true;
            $showDuration     = $settings['show_duration'] ?? true;
        @endphp
        <ul class="sf-svc-list">
            @foreach($hasFeatured ? $featuredServices : $services as $service)
            <li class="sf-svc-item">
                <div class="sf-svc-item-main">
                    <div class="sf-svc-item-name">{{ $service->name }}</div>
                    @if($service->description)
                        <div class="sf-svc-item-desc">{{ $service->description }}</div>
                    @endif
                </div>
                <div class="sf-svc-item-meta">
                    @if($showPrices)
                        <span class="sf-svc-item-price">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                    @endif
                    @if($showDuration)
                        <span class="sf-svc-item-dur">{{ $service->duration_minutes }}&thinsp;min</span>
                    @endif
                    <a href="{{ route('booking.create') }}?service={{ $service->id }}" class="sf-svc-book-badge" aria-label="Prenota {{ $service->name }}">Prenota &rarr;</a>
                </div>
            </li>
            @endforeach
        </ul>
        @if($hasFeatured && $otherServices->isNotEmpty())
        <div x-data="{ open: false }">
            <ul x-show="open" x-transition class="sf-svc-list sf-svc-list--more">
                @foreach($otherServices as $service)
                <li class="sf-svc-item">
                    <div class="sf-svc-item-main">
                        <div class="sf-svc-item-name">{{ $service->name }}</div>
                        @if($service->description)
                            <div class="sf-svc-item-desc">{{ $service->description }}</div>
                        @endif
                    </div>
                    <div class="sf-svc-item-meta">
                        @if($showPrices)
                            <span class="sf-svc-item-price">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                        @endif
                        @if($showDuration)
                            <span class="sf-svc-item-dur">{{ $service->duration_minutes }}&thinsp;min</span>
                        @endif
                        <a href="{{ route('booking.create') }}?service={{ $service->id }}" class="sf-svc-book-badge" aria-label="Prenota {{ $service->name }}">Prenota &rarr;</a>
                    </div>
                </li>
                @endforeach
            </ul>
            <button x-show="!open" @click="open = true" class="sf-show-more">
                Mostra tutti i servizi <span class="sf-show-more-count">({{ $otherServices->count() }})</span>
            </button>
            <button x-show="open" @click="open = false" class="sf-show-more">
                Riduci ai servizi in evidenza
            </button>
        </div>
        @endif
    </div>
</section>
@endif
