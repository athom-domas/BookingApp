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
            $featuredOnly     = $settings['featured_only'] ?? false;
            $featuredServices = $featuredOnly ? $services->where('featured', true) : collect();
            $otherServices    = $featuredOnly ? $services->where('featured', false) : collect();
            $showPrices       = $settings['show_prices'] ?? true;
            $showDuration     = $settings['show_duration'] ?? true;
        @endphp
        <ul style="list-style:none">
            @foreach($featuredOnly ? $featuredServices : $services as $service)
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
        @if($featuredOnly && $otherServices->isNotEmpty())
            <div x-data="{ open: false }">
                <div x-show="open" x-transition>
                    <ul style="list-style:none">
                        @foreach($otherServices as $service)
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
                </div>
                <button x-show="!open" @click="open = true" class="sf-show-more" style="margin-top:24px">
                    Mostra tutti i servizi <span class="sf-show-more-count">({{ $otherServices->count() }})</span>
                </button>
                <button x-show="open" @click="open = false" class="sf-show-more" style="margin-top:24px">
                    Riduci ai servizi in evidenza
                </button>
            </div>
        @endif
        <div class="sf-svc-cta" style="text-align:center">
            <a href="{{ route('booking.create') }}" class="sf-btn">{{ $content['cta_label'] ?? 'Prenota ora' }}</a>
        </div>
    </div>
</section>
@endif
