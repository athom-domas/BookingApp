{{-- Variables: $content['title'], $content['items'] (array of {question, answer}), $block --}}
<section id="faq-{{ $block->id }}" class="sf-section sf-section-alt">
    <div class="sf-inner" style="max-width:720px">
        @if(!empty($content['title']))
            <h2 class="sf-heading">{{ $content['title'] }}</h2>
            <div class="sf-rule"></div>
        @endif
        @foreach($content['items'] ?? [] as $item)
        <div style="margin-bottom:1.5rem">
            <h3 style="margin-bottom:0.5rem;font-size:1.05rem">{{ $item['question'] }}</h3>
            <p>{{ $item['answer'] }}</p>
        </div>
        @endforeach
    </div>
</section>
