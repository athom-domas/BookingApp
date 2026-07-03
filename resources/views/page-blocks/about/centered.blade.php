@php
    $images = array_values(array_filter((array) ($content['images'] ?? $content['image'] ?? [])));
    $imageUrls = array_map(fn($img) => \Illuminate\Support\Facades\Storage::disk('public')->url($img), $images);
@endphp
<section id="{{ $block->block_type }}" class="sf-section" x-data="{
    images: {{ \Illuminate\Support\Js::from($imageUrls) }},
    idx: -1,
    prev() { this.idx = (this.idx - 1 + this.images.length) % this.images.length; },
    next() { this.idx = (this.idx + 1) % this.images.length; },
}">
    <div class="sf-inner" style="text-align:center;max-width:720px">
        @if(!empty($content['title']))
            <h2 class="sf-heading">{{ $content['title'] }}</h2>
            <div class="sf-rule" style="margin-left:auto;margin-right:auto"></div>
        @endif
        @if(!empty($content['body']))
            <div class="sf-about-text">{!! $content['body'] !!}</div>
        @endif
        @if(!empty($content['owner_signature']))
            <p class="sf-about-signature">{{ $content['owner_signature'] }}</p>
        @endif
        @if(!empty($images))
            <div style="margin-top:2rem;display:flex;gap:6px;justify-content:center">
                @foreach($images as $i => $img)
                    <div class="sf-about-photo" style="flex:1;height:260px" @click="idx = {{ $i }}">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($img) }}"
                             alt="{{ $content['title'] ?? '' }}" loading="lazy">
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @if(!empty($images))
        <div x-show="idx >= 0" x-transition.opacity x-cloak class="sf-lightbox"
             @click="idx = -1"
             @keydown.escape.window="idx = -1"
             @keydown.left.window="idx >= 0 && prev()"
             @keydown.right.window="idx >= 0 && next()">
            <button class="sf-lightbox-close" @click.stop="idx = -1" aria-label="Chiudi">×</button>
            <template x-if="images.length > 1">
                <button class="sf-lightbox-nav sf-lightbox-prev" @click.stop="prev()" aria-label="Precedente">&#8249;</button>
            </template>
            <img :src="idx >= 0 ? images[idx] : ''" @click.stop alt="">
            <template x-if="images.length > 1">
                <button class="sf-lightbox-nav sf-lightbox-next" @click.stop="next()" aria-label="Successiva">&#8250;</button>
            </template>
        </div>
    @endif
</section>
