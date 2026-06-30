{{-- Variables: $content['title'], $content['subtitle'], $reviews (Collection), $business, $block --}}
@if($reviews->isNotEmpty())
<section class="sf-section" id="{{ $block->block_type }}">
    <div class="sf-inner" style="max-width:720px">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Cosa dicono di noi' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        @foreach($reviews as $review)
        <blockquote style="margin:0;padding:28px 0;border-bottom:1px solid var(--sf-border)">
            <p style="font-size:14px;color:var(--sf-body);line-height:1.8;margin:0 0 12px;font-style:italic">"{{ $review->body }}"</p>
            <footer style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:var(--sf-gold-lt)">{{ $review->author_name }}</footer>
        </blockquote>
        @endforeach
    </div>
</section>
@endif
