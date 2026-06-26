{{-- Variables: $content['title'], $content['subtitle'], $reviews (Collection), $business, $block --}}
@if($reviews->isNotEmpty())
<section class="sf-section" id="recensioni">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Cosa dicono di noi' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div x-data="{
            current: 0,
            total: {{ $reviews->count() }},
            prev() { this.current = (this.current - 1 + this.total) % this.total; },
            next() { this.current = (this.current + 1) % this.total; },
        }" style="position:relative">
            <div style="overflow:hidden">
                <div :style="`display:flex;transition:transform 0.4s ease;transform:translateX(-${current * 100}%)`">
                    @foreach($reviews as $review)
                    <div style="flex:0 0 100%;padding:0 4px">
                        <div class="sf-review-card" style="max-width:640px;margin:0 auto">
                            <div class="sf-review-quote" aria-hidden="true">"</div>
                            <div class="sf-review-stars" role="img" aria-label="Valutazione {{ $review->rating }} su 5">
                                @for($i = 1; $i <= 5; $i++)
                                    <span aria-hidden="true">{{ $i <= $review->rating ? '★' : '☆' }}</span>
                                @endfor
                            </div>
                            <p class="sf-review-body">{{ $review->body }}</p>
                            <div class="sf-review-author">{{ $review->author_name }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @if($reviews->count() > 1)
            <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin-top:24px">
                <button @click="prev()" class="sf-lightbox-nav" style="position:static;transform:none;background:var(--sf-bg-card);border:1px solid var(--sf-border);color:var(--sf-gold)" aria-label="Precedente">&#8249;</button>
                <div style="display:flex;gap:6px">
                    @foreach($reviews as $review)
                    <button @click="current = {{ $loop->index }}"
                            :style="`width:6px;height:6px;border-radius:50%;border:none;cursor:pointer;background:${current === {{ $loop->index }} ? 'var(--sf-gold)' : 'var(--sf-border)'};transition:background 0.2s`"
                            aria-label="Recensione {{ $loop->iteration }}"></button>
                    @endforeach
                </div>
                <button @click="next()" class="sf-lightbox-nav" style="position:static;transform:none;background:var(--sf-bg-card);border:1px solid var(--sf-border);color:var(--sf-gold)" aria-label="Successiva">&#8250;</button>
            </div>
            @endif
        </div>
    </div>
</section>
@endif
