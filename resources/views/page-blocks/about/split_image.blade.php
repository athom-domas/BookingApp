{{-- Variables: $content['title'], $content['body'], $content['image'], $content['owner_signature'],
     $settings['alignment'], $block --}}
<section id="about-{{ $block->id }}" class="sf-section">
    <div class="sf-inner">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center;@media(max-width:640px){grid-template-columns:1fr}">
            @if(!empty($content['image']))
                <div>
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($content['image']) }}"
                         alt="{{ $content['title'] ?? '' }}"
                         style="width:100%;border-radius:var(--sf-radius,8px);display:block">
                </div>
            @endif
            <div style="{{ empty($content['image']) ? 'grid-column:1/-1' : '' }}">
                @if(!empty($content['title']))
                    <h2 class="sf-heading">{{ $content['title'] }}</h2>
                    <div class="sf-rule"></div>
                @endif
                @if(!empty($content['body']))
                    <p>{{ $content['body'] }}</p>
                @endif
                @if(!empty($content['owner_signature']))
                    <p class="sf-signature" style="margin-top:1.5rem;font-style:italic">{{ $content['owner_signature'] }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
