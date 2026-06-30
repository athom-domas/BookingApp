{{-- Variables: $content['title'], $content['subtitle'], $content['button_label'], $content['image'],
     $settings['alignment'], $block --}}
<section id="cta-{{ $block->id }}" class="sf-section sf-section-cta {{ !empty($content['image']) ? 'sf-section-cta--img' : '' }}"
    style="text-align:{{ $settings['alignment'] ?? 'center' }};position:relative;overflow:hidden;{{ !empty($content['image']) ? 'background-image:url(' . \Illuminate\Support\Facades\Storage::disk('public')->url($content['image']) . ');background-size:cover;background-position:center' : '' }}">
    @if(!empty($content['image']))
        <div style="position:absolute;inset:0;background:rgba(0,0,0,0.5)"></div>
    @endif
    <div class="sf-inner" style="position:relative;z-index:1;">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Prenota ora' }}</h2>
        @if(!empty($content['subtitle']))
            <p>{{ $content['subtitle'] }}</p>
        @endif
        <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">{{ $content['button_label'] ?? 'Prenota' }}</a>
    </div>
</section>
