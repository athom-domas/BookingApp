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
                <h3 class="sf-svc-category-heading">{{ $group['category'] ? $group['category']->name : 'Altri' }}</h3>
            @endif
            @php
                $featuredInGroup = $featuredOnly ? $group['services']->where('featured', true) : collect();
                $otherInGroup    = $featuredOnly ? $group['services']->where('featured', false) : collect();
            @endphp
            <ul class="sf-svc-list">
                @foreach($featuredOnly ? $featuredInGroup : $group['services'] as $service)
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
                        <a href="{{ route('booking.create') }}?service={{ $service->id }}"
                           class="sf-svc-book-badge" aria-label="Prenota {{ $service->name }}">Prenota &rarr;</a>
                    </div>
                </li>
                @endforeach
            </ul>
            @if($featuredOnly && $otherInGroup->isNotEmpty())
                <div x-data="{ open: false }">
                    <div x-show="open" x-transition>
                        <ul class="sf-svc-list sf-svc-list--more">
                            @foreach($otherInGroup as $service)
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
                                    <a href="{{ route('booking.create') }}?service={{ $service->id }}"
                                       class="sf-svc-book-badge" aria-label="Prenota {{ $service->name }}">Prenota &rarr;</a>
                                </div>
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
    </div>
</section>
@endif
