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
        <div class="sf-svc-grid">
            @foreach($featuredOnly ? $featuredServices : $services as $service)
                @php $imgUrl = $service->imageUrl(); @endphp
                <div class="sf-svc-card">
                    <div class="sf-svc-card-img">
                        @if($imgUrl)
                            <img src="{{ $imgUrl }}" alt="{{ $service->name }}" loading="lazy" width="640" height="480">
                        @endif
                    </div>
                    <div class="sf-svc-card-body">
                        <h3 class="sf-svc-card-name">{{ $service->name }}</h3>
                        @if($service->description)
                            <p class="sf-svc-card-desc">{{ $service->description }}</p>
                        @endif
                        <div class="sf-svc-card-foot">
                            <div class="sf-svc-card-meta">
                                @if($showPrices)
                                    <span class="sf-svc-item-price">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                                @endif
                                @if($showDuration)
                                    <span class="sf-svc-item-dur">{{ $service->duration_minutes }}&thinsp;min</span>
                                @endif
                            </div>
                            <a href="{{ route('booking.create') }}?service={{ $service->id }}"
                               class="sf-svc-book-badge" aria-label="Prenota {{ $service->name }}">Prenota &rarr;</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @if($featuredOnly && $otherServices->isNotEmpty())
            <div x-data="{ open: false }">
                <div x-show="open" x-transition class="sf-svc-grid" style="margin-top:20px">
                    @foreach($otherServices as $service)
                        @php $imgUrl = $service->imageUrl(); @endphp
                        <div class="sf-svc-card">
                            <div class="sf-svc-card-img">
                                @if($imgUrl)
                                    <img src="{{ $imgUrl }}" alt="{{ $service->name }}" loading="lazy" width="640" height="480">
                                @endif
                            </div>
                            <div class="sf-svc-card-body">
                                <h3 class="sf-svc-card-name">{{ $service->name }}</h3>
                                @if($service->description)
                                    <p class="sf-svc-card-desc">{{ $service->description }}</p>
                                @endif
                                <div class="sf-svc-card-foot">
                                    <div class="sf-svc-card-meta">
                                        @if($showPrices)
                                            <span class="sf-svc-item-price">€{{ number_format((float) $service->price, 0, ',', '.') }}</span>
                                        @endif
                                        @if($showDuration)
                                            <span class="sf-svc-item-dur">{{ $service->duration_minutes }}&thinsp;min</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('booking.create') }}?service={{ $service->id }}"
                                       class="sf-svc-book-badge" aria-label="Prenota {{ $service->name }}">Prenota &rarr;</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button x-show="!open" @click="open = true" class="sf-show-more" style="margin-top:24px">
                    Mostra tutti i servizi <span class="sf-show-more-count">({{ $otherServices->count() }})</span>
                </button>
                <button x-show="open" @click="open = false" class="sf-show-more" style="margin-top:24px">
                    Riduci ai servizi in evidenza
                </button>
            </div>
        @endif
    </div>
</section>
@endif
