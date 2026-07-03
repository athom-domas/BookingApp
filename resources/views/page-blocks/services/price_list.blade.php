{{-- Variables: $content['title'], $content['subtitle'], $content['cta_label'],
     $settings['show_prices'], $settings['show_duration'], $services (Collection), $grouped_services, $business, $block --}}
@if($services->isNotEmpty())
<section class="sf-section-alt" id="{{ $block->block_type }}">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'I nostri servizi' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        @php
            $featuredOnly = $settings['featured_only'] ?? false;
            $showPrices   = $settings['show_prices'] ?? true;
            $showDuration = $settings['show_duration'] ?? true;
            $groupCount   = count($grouped_services);
        @endphp
        @foreach($grouped_services as $group)
            @if($groupCount > 1)
            <p style="font-family:var(--sf-font-display);font-size:26px;font-weight:700;color:var(--sf-gold-lt);padding:36px 0 10px;margin:0;border-bottom:2px solid var(--sf-border)">
                {{ $group['category'] ? $group['category']->name : 'Altri' }}
            </p>
            @endif
            @php
                $featuredInGroup = $featuredOnly ? $group['services']->where('featured', true) : collect();
                $otherInGroup    = $featuredOnly ? $group['services']->where('featured', false) : collect();
            @endphp
            <ul style="list-style:none">
                @foreach($featuredOnly ? $featuredInGroup : $group['services'] as $service)
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
            @if($featuredOnly && $otherInGroup->isNotEmpty())
                <div x-data="{ open: false }">
                    <div x-show="open" x-transition>
                        <ul style="list-style:none">
                            @foreach($otherInGroup as $service)
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
                        Mostra tutti i servizi <span class="sf-show-more-count">({{ $otherInGroup->count() }})</span>
                    </button>
                    <button x-show="open" @click="open = false" class="sf-show-more" style="margin-top:24px">
                        Riduci ai servizi in evidenza
                    </button>
                </div>
            @endif
        @endforeach
        <div class="sf-svc-cta" style="text-align:center">
            <a href="{{ route('booking.create') }}" class="sf-btn">{{ $content['cta_label'] ?? 'Prenota ora' }}</a>
        </div>
    </div>
</section>
@endif
