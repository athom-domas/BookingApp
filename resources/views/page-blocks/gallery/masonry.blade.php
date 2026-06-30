{{-- Variables: $content['title'], $content['subtitle'], $images (Collection of URLs), $business, $block --}}
@if($images->isNotEmpty())
@php $imageUrls = $images->values()->toArray(); @endphp
<section class="sf-section" id="galleria" x-data="{
    images: {{ \Illuminate\Support\Js::from($imageUrls) }},
    idx: -1,
    prev() { this.idx = (this.idx - 1 + this.images.length) % this.images.length; },
    next() { this.idx = (this.idx + 1) % this.images.length; },
}">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Galleria' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
        <div style="columns:3;gap:1rem">
            @foreach($images as $url)
            <div style="break-inside:avoid;margin-bottom:1rem;overflow:hidden;cursor:pointer" @click="idx = {{ $loop->index }}">
                <img src="{{ $url }}" alt="Galleria {{ $loop->iteration }}" loading="lazy"
                     style="width:100%;display:block;transition:filter 0.35s ease"
                     @mouseenter="$el.style.filter='brightness(0.82)'"
                     @mouseleave="$el.style.filter=''">
            </div>
            @endforeach
        </div>
    </div>
    <div x-show="idx >= 0"
         x-transition.opacity
         x-cloak
         class="sf-lightbox"
         @click="idx = -1"
         @keydown.escape.window="idx = -1"
         @keydown.left.window="idx >= 0 && prev()"
         @keydown.right.window="idx >= 0 && next()"
    >
        <button class="sf-lightbox-close" @click.stop="idx = -1" aria-label="Chiudi">×</button>
        <template x-if="images.length > 1">
            <button class="sf-lightbox-nav sf-lightbox-prev" @click.stop="prev()" aria-label="Precedente">&#8249;</button>
        </template>
        <img :src="idx >= 0 ? images[idx] : ''" @click.stop alt="">
        <template x-if="images.length > 1">
            <button class="sf-lightbox-nav sf-lightbox-next" @click.stop="next()" aria-label="Successiva">&#8250;</button>
        </template>
    </div>
</section>
@endif
