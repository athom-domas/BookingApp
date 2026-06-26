{{-- Variables: $content['title'], $settings['height'] (sm/md/lg), $settings['show_directions_link'],
     $profile (SalonProfile, may be null), $block --}}
@if($profile?->google_maps_embed)
<section id="map-{{ $block->id }}" class="sf-section">
    <div class="sf-inner">
        <div style="max-width:900px;margin:auto">
            @if(!empty($content['title']))
                <h2 class="sf-heading" style="margin-bottom:1rem">{{ $content['title'] }}</h2>
            @endif
            @php $mapHeight = match($settings['height'] ?? 'md') { 'sm' => '240px', 'lg' => '600px', default => '400px' }; @endphp
            <div x-data="{ loaded: false }" style="width:100%;height:{{ $mapHeight }};position:relative;border-radius:var(--sf-radius,8px);overflow:hidden">
                {{-- google_maps_embed is admin-entered; stores a URL, not customer input --}}
                <template x-if="loaded">
                    <iframe src="{{ $profile->google_maps_embed }}"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                        title="Mappa {{ $profile->name }}"
                        style="width:100%;height:100%;border:0;display:block"></iframe>
                </template>
                <button x-show="!loaded" @click="loaded = true" class="sf-map-placeholder" style="width:100%;height:100%" aria-label="Carica la mappa di {{ $profile->name }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    <span>Clicca per vedere la mappa</span>
                </button>
            </div>
            @if($settings['show_directions_link'] ?? true)
                <div style="margin-top:0.75rem">
                    <a href="https://maps.google.com/?q={{ urlencode($profile->address ?? $profile->name) }}" target="_blank" rel="noopener">Ottieni indicazioni →</a>
                </div>
            @endif
        </div>
    </div>
</section>
@endif
