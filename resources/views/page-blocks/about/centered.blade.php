{{-- Variables: $content['title'], $content['body'], $content['image'], $content['owner_signature'],
     $settings['alignment'], $block --}}
<section id="about-{{ $block->id }}" class="sf-section">
    <div class="sf-inner" style="text-align:{{ $settings['alignment'] ?? 'center' }};max-width:720px">
        @if(!empty($content['title']))
            <h2 class="sf-heading">{{ $content['title'] }}</h2>
            <div class="sf-rule"></div>
        @endif
        @if(!empty($content['body']))
            <p>{{ $content['body'] }}</p>
        @endif
        @if(!empty($content['image']))
            <div style="margin-top:2rem">
                <img src="{{ \Illuminate\Support\Facades\Storage::url($content['image']) }}"
                     alt="{{ $content['title'] ?? '' }}"
                     style="max-width:100%;border-radius:var(--sf-radius,8px)">
            </div>
        @endif
        @if(!empty($content['owner_signature']))
            <p class="sf-signature" style="margin-top:1.5rem;font-style:italic">{{ $content['owner_signature'] }}</p>
        @endif
    </div>
</section>
