{{-- Variables: $content['title'], $content['subtitle'], $content['cta_label'],
     $settings['show_cta'], $business, $block --}}
<section class="sf-hero sf-hero--no-img sf-hero--centered" style="background: var(--sf-primary, var(--sf-bg))">
    <div class="sf-hero-inner">
        <div class="sf-hero-ornament">
            <span class="sf-hero-line"></span>
            <span class="sf-hero-dot"></span>
            <span class="sf-hero-line"></span>
        </div>
        <h1 class="sf-hero-name">{{ $content['title'] ?? $business->name }}</h1>
        <div class="sf-hero-ornament">
            <span class="sf-hero-line"></span>
            <span class="sf-hero-dot"></span>
            <span class="sf-hero-line"></span>
        </div>
        @if(!empty($content['subtitle']))
            <p class="sf-hero-tagline">{{ $content['subtitle'] }}</p>
        @endif
        @if($settings['show_cta'] ?? true)
            <a href="{{ route('booking.create') }}" class="sf-btn sf-btn-lg">{{ $content['cta_label'] ?? 'Prenota ora' }}</a>
        @endif
    </div>
</section>
