{{-- Variables: $content['title'], $content['subtitle'], $images (Spatie MediaLibrary Collection), $business, $block --}}
@if($images->isNotEmpty())
<section class="sf-section" id="galleria">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Galleria' }}</h2>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline" style="margin:0 0 16px">{{ $content['subtitle'] }}</p>
        @endif
        <div class="sf-rule"></div>
    </div>
    <div style="display:flex;overflow-x:auto;gap:1rem;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;padding:0 48px 16px;scrollbar-width:none"
         x-data>
        @foreach($images as $item)
        <div style="scroll-snap-align:start;flex:0 0 300px;overflow:hidden;border:1px solid var(--sf-border)">
            <img src="{{ $item->getUrl('web') }}"
                 srcset="{{ $item->getUrl('gallery-sm') }} 576w, {{ $item->getUrl('web') }} 1200w"
                 sizes="300px"
                 alt="Galleria {{ $loop->iteration }}" loading="lazy" width="300" height="225"
                 style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block">
        </div>
        @endforeach
    </div>
</section>
@endif
