{{-- Variables: $content['title'], $content['subtitle'], $images (Collection of URLs), $business, $block --}}
@if($images->isNotEmpty())
@php $total = $images->count(); @endphp
<section class="sf-section" id="galleria" x-data="{
    idx: 0,
    total: {{ $total }},
    itemW: 316,
    dragging: false, startX: 0, startScroll: 0,
    init() {
        this.itemW = this.$refs.track.firstElementChild
            ? this.$refs.track.firstElementChild.offsetWidth + 16
            : 316;
    },
    go(i) {
        this.idx = Math.max(0, Math.min(i, this.total - 1));
        this.$refs.track.scrollTo({ left: this.idx * this.itemW, behavior: 'smooth' });
    },
    prev() { this.go(this.idx - 1); },
    next() { this.go(this.idx + 1); },
    dragStart(e) {
        this.dragging = true;
        this.startX = e.clientX;
        this.startScroll = this.$refs.track.scrollLeft;
        this.$refs.track.classList.add('is-dragging');
    },
    dragMove(e) {
        if (!this.dragging) return;
        e.preventDefault();
        this.$refs.track.scrollLeft = this.startScroll + (this.startX - e.clientX);
    },
    dragEnd(e) {
        if (!this.dragging) return;
        this.dragging = false;
        this.$refs.track.classList.remove('is-dragging');
        this.idx = Math.max(0, Math.min(Math.round(this.$refs.track.scrollLeft / this.itemW), this.total - 1));
        this.$refs.track.scrollTo({ left: this.idx * this.itemW, behavior: 'smooth' });
    },
    onScroll() {
        if (this.dragging) return;
        this.idx = Math.round(this.$refs.track.scrollLeft / this.itemW);
    },
}">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Galleria' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
    </div>

    <div style="position:relative">
        <div x-ref="track"
             class="sf-slider-track"
             style="padding:0 48px 0;gap:16px"
             @mousedown="dragStart($event)"
             @mousemove="dragMove($event)"
             @mouseup="dragEnd($event)"
             @mouseleave="dragging && dragEnd($event)"
             @scroll.passive="onScroll()">
            @foreach($images as $url)
            <div style="scroll-snap-align:start;flex:0 0 300px;overflow:hidden;border:1px solid var(--sf-border)">
                <img src="{{ $url }}" alt="Galleria {{ $loop->iteration }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                     style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block;pointer-events:none;-webkit-user-drag:none">
            </div>
            @endforeach
        </div>

        @if($total > 1)
        <button class="sf-slider-nav sf-slider-prev" @click="prev()" :disabled="idx === 0" aria-label="Precedente">&#8249;</button>
        <button class="sf-slider-nav sf-slider-next" @click="next()" :disabled="idx === total - 1" aria-label="Successiva">&#8250;</button>
        @endif
    </div>

    @if($total > 1)
    <div class="sf-slider-dots">
        @if($total <= 9)
            @foreach($images as $url)
            <button class="sf-slider-dot" :class="idx === {{ $loop->index }} ? 'is-active' : ''" @click="go({{ $loop->index }})" aria-label="Foto {{ $loop->iteration }}"></button>
            @endforeach
        @else
        <span style="font-size:13px;color:var(--sf-muted)" x-text="(idx + 1) + ' / {{ $total }}'"></span>
        @endif
    </div>
    @endif
</section>
@endif
