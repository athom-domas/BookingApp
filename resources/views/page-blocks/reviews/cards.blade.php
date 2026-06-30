{{-- Variables: $content['title'], $content['subtitle'], $reviews (Collection), $business, $block --}}
@if($reviews->isNotEmpty())
<section class="sf-section" id="{{ $block->block_type }}">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Cosa dicono di noi' }}</h2>
        <div class="sf-rule"></div>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-reviews-grid">
            @foreach($reviews as $review)
            <div class="sf-review-card">
                <div class="sf-review-quote" aria-hidden="true">"</div>
                <div class="sf-review-stars" role="img" aria-label="Valutazione {{ $review->rating }} su 5">
                    @for($i = 1; $i <= 5; $i++)
                        <span aria-hidden="true">{{ $i <= $review->rating ? '★' : '☆' }}</span>
                    @endfor
                </div>
                <p class="sf-review-body">{{ $review->body }}</p>
                <div class="sf-review-author">{{ $review->author_name }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
