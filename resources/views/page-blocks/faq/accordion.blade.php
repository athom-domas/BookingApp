{{-- Variables: $content['title'], $content['items'] (array of {question, answer}), $block --}}
<section id="{{ $block->block_type }}" class="sf-section sf-section-alt">
    <div class="sf-inner" style="max-width:720px">
        @if(!empty($content['title']))
            <h2 class="sf-heading">{{ $content['title'] }}</h2>
            <div class="sf-rule"></div>
        @endif
        @foreach($content['items'] ?? [] as $item)
        <div class="sf-accordion" x-data="{ open: false }">
            <button class="sf-accordion-btn" @click="open = !open" :aria-expanded="open.toString()" type="button">
                {{ $item['question'] }}
                <svg class="sf-accordion-chevron" :class="{ open: open }" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="sf-accordion-body-grid" :style="open ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                <div><div class="sf-accordion-body">{{ $item['answer'] }}</div></div>
            </div>
        </div>
        @endforeach
        @if(!empty($settings['include_cancellation_policy']))
        <div class="sf-accordion" x-data="{ open: false }">
            <button class="sf-accordion-btn" @click="open = !open" :aria-expanded="open.toString()" type="button">
                Politica di cancellazione
                <svg class="sf-accordion-chevron" :class="{ open: open }" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="sf-accordion-body-grid" :style="open ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                <div><div class="sf-accordion-body">{!! $business->salonProfile->cancellationPolicyHtml() !!}</div></div>
            </div>
        </div>
        @endif
    </div>
</section>
