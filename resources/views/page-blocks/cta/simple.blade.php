{{-- Variables: $content['title'], $content['subtitle'], $content['button_label'],
     $settings['alignment'], $block --}}
<section id="{{ $block->block_type }}" class="sf-section sf-section-cta" style="text-align:{{ $settings['alignment'] ?? 'center' }}">
    <div class="sf-inner">
        <h2 class="sf-heading">{{ $content['title'] ?? 'Prenota ora' }}</h2>
        @if(!empty($content['subtitle']))
            <p>{{ $content['subtitle'] }}</p>
        @endif
        <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">{{ $content['button_label'] ?? 'Prenota' }}</a>
    </div>
</section>
